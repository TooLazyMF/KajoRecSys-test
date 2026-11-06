<x-layouts::app :title="$slip['title']">
    <main class="min-h-screen bg-[#f6f5f1] px-4 py-8 text-zinc-900 transition-colors dark:bg-[#111712] dark:text-zinc-100 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">{{ $slip['title'] }}</flux:heading>
                <flux:text class="mt-2 text-zinc-600 dark:text-zinc-300">
                    @if ($isTeacherSubmittedReferral)
                        Review the referral prepared by your teacher, then submit it to the Guidance Counselor.
                    @else
                        Fill out the sections intended for you. You can edit this submission until it is approved by the Guidance Office.
                    @endif
                </flux:text>
            </div>

            @if ($selectedStudent)
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200">
                    Assigned to: <strong>{{ $selectedStudent->name }}</strong>
                    <span class="ml-2">{{ $selectedStudent->id_number }}</span>
                </div>
            @endif

            @if ($isTeacherSubmittedReferral && $existingSubmission)
                <section class="mb-6 flex flex-col gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100 sm:flex-row sm:items-center sm:justify-between">
                    @if ($referralPdfReady)
                        <p>Open or print the referral to bring it to the Guidance Counselor. Submit this page after handing over the form.</p>
                        <a href="{{ route('slips.submissions.pdf', $existingSubmission) }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-blue-300 bg-white px-4 py-2 text-xs font-semibold text-blue-800 hover:bg-blue-100 dark:border-blue-800 dark:bg-zinc-900 dark:text-blue-200 dark:hover:bg-zinc-800">
                            Open teacher-issued referral PDF
                        </a>
                    @else
                        <p>The referral PDF is not printable yet. Guidance can still receive your referral and issue your Class Admission Slip; the PDF will appear here after the document-control details are issued.</p>
                    @endif
                </section>
                @unless ($referralPdfReady)
                    <iframe
                        src="{{ route('slips.submissions.preview-pdf', $existingSubmission) }}#toolbar=0&navpanes=0"
                        title="Referral form preview"
                        class="h-[80vh] w-full rounded-xl border border-blue-200 bg-white dark:border-blue-900"
                    ></iframe>
                @endunless
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200">
                    <p class="font-medium">Please correct the following:</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($isStudentRequest && $assignment->assigned_by === auth()->id() && $assignment->status === 'pending' && !$existingSubmission)
                <form
                    id="cancel-student-slip-request"
                    method="POST"
                    action="{{ route('assigned-slips.cancel-request', $assignment) }}"
                    onsubmit="return confirm('Cancel this form request? It will be removed from your pending slips.')"
                >
                    @csrf
                </form>
            @endif

            <form method="POST" action="{{ route('assigned-slips.submit', $assignment) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @if ($selectedStudent)
                    <input type="hidden" name="referred_student_id" value="{{ $selectedStudent->id }}">
                @endif

                @unless ($isTeacherSubmittedReferral)
                    @if (!empty($slip['prefilled_fields']))
                    <section class="space-y-4 rounded-2xl border border-emerald-100 bg-white p-6 shadow-[0_8px_24px_rgba(15,163,68,0.07)] dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">From your Teacher Referral Slip</flux:heading>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($slip['prefilled_fields'] as $field)
                                <div>
                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $field['label'] }}</p>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $defaults[$field['key']] ?? 'Not provided on the referral slip' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                    @endif

                    @foreach ($slip['groups'] as $group)
                    <section class="space-y-5 rounded-2xl border border-emerald-100 bg-white p-6 shadow-[0_8px_24px_rgba(15,163,68,0.07)] dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">{{ $group['title'] }}</flux:heading>

                        <div class="grid gap-5 sm:grid-cols-2">
                            @foreach ($group['fields'] as $field)
                                <div class="{{ in_array($field['type'], ['textarea', 'checkboxes'], true) ? 'sm:col-span-2' : '' }}">
                                    @if (!empty($field['readonly']))
                                        <div>
                                            <flux:input
                                                id="{{ $field['key'] }}"
                                                label="{{ $field['label'] }}"
                                                value="{{ $defaults[$field['key']] ?? '' }}"
                                                readonly
                                                class="bg-zinc-100 dark:bg-zinc-800"
                                            />
                                            <input type="hidden" name="details[{{ $field['key'] }}]" value="{{ $defaults[$field['key']] ?? '' }}">
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">From your imported student record</p>
                                        </div>
                                    @elseif ($field['type'] === 'textarea')
                                        <flux:textarea
                                            name="details[{{ $field['key'] }}]"
                                            label="{{ $field['label'] }}"
                                            rows="{{ max(3, $field['rows']) }}"
                                            :required="$field['required']"
                                        >{{ old('details.'.$field['key'], $existingSubmission?->data[$field['key']] ?? null) }}</flux:textarea>
                                    @elseif ($field['type'] === 'checkboxes')
                                        @php($selectedValues = old('details.'.$field['key'], $existingSubmission?->data[$field['key']] ?? []))
                                        @php($selectedValues = is_array($selectedValues) ? $selectedValues : (filled($selectedValues) ? [$selectedValues] : []))
                                        <fieldset class="space-y-2">
                                            <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                                {{ $field['label'] }}{{ $field['required'] ? ' *' : '' }}
                                            </legend>
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                @foreach ($field['options'] as $value => $label)
                                                    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                                        <input
                                                            type="{{ $field['key'] === 'referral_reasons' ? 'radio' : 'checkbox' }}"
                                                            name="details[{{ $field['key'] }}][]"
                                                            value="{{ $value }}"
                                                            @checked(in_array($value, $selectedValues, true))
                                                            @if ($field['key'] === 'referral_reasons' && $field['required']) required @endif
                                                            class="rounded border-zinc-300 text-emerald-700 focus:ring-emerald-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-emerald-400 dark:focus:ring-emerald-400"
                                                        >
                                                        <span>{{ $label }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @elseif ($field['type'] === 'select')
                                        <label class="block space-y-2">
                                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $field['label'] }}{{ $field['required'] ? ' *' : '' }}</span>
                                            <select
                                                name="details[{{ $field['key'] }}]"
                                                @required($field['required'])
                                                class="w-full rounded-lg border-zinc-300 bg-white text-sm text-zinc-900 shadow-sm focus:border-green-700 focus:ring-green-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                            >
                                                <option value="">Choose an option</option>
                                                @foreach ($field['options'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('details.'.$field['key'], $existingSubmission?->data[$field['key']] ?? '') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @elseif ($field['key'] === 'other_reason')
                                        <flux:input
                                            id="other_reason"
                                            name="details[{{ $field['key'] }}]"
                                            label="{{ $field['label'] }}"
                                            value="{{ old('details.'.$field['key'], $existingSubmission?->data[$field['key']] ?? null) }}"
                                            maxlength="255"
                                            disabled
                                            readonly
                                        />
                                    @else
                                        <flux:input
                                            id="{{ $field['key'] }}"
                                            name="details[{{ $field['key'] }}]"
                                            label="{{ $field['label'] }}"
                                            type="{{ $field['type'] }}"
                                            value="{{ old('details.'.$field['key'], $existingSubmission?->data[$field['key']] ?? $defaults[$field['key']] ?? null) }}"
                                            :required="$field['required']"
                                        />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                    @endforeach
                @endunless

                @if ($assignment->slip === 'class-admission-slip' && !$isTeacherSubmittedReferral)
                    <section class="space-y-3 rounded-2xl border border-emerald-100 bg-white p-6 shadow-[0_8px_24px_rgba(15,163,68,0.07)] dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
                        <div>
                            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Supporting evidence (optional)</flux:heading>
                            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-300">Attach images or documents that help explain your absence. You may select up to 5 files, up to 10 MB each.</flux:text>
                        </div>
                        <input type="file" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 text-sm text-zinc-700 file:mr-4 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-emerald-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-100">
                        @if ($existingSubmission?->data['_attachments'] ?? false)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Existing attachments are kept when you save changes without selecting new files.</p>
                        @endif
                    </section>
                @endif

                <div class="flex justify-end gap-3">
                    @if ($isStudentRequest && $assignment->assigned_by === auth()->id() && $assignment->status === 'pending' && !$existingSubmission)
                        <button
                            type="submit"
                            form="cancel-student-slip-request"
                            class="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            Cancel request
                        </button>
                    @else
                        <a
                            href="{{ route('student.dashboard') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            Cancel
                        </a>
                    @endif
                    <flux:button variant="primary" type="submit">
                        {{ $isTeacherSubmittedReferral
                            ? 'Submit to Guidance Counselor'
                            : ($existingSubmission ? 'Save Changes' : 'Submit') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </main>

    @if ($assignment->slip === 'student-referral-slip')
        <script>
            const referralReasonInputs = document.querySelectorAll('input[name="details[referral_reasons][]"]');
            const otherReasonInput = document.getElementById('other_reason');
            const updateOtherReasonRequirement = () => {
                const otherSelected = document.querySelector('input[name="details[referral_reasons][]"]:checked')?.value === 'other';
                if (otherReasonInput) {
                    otherReasonInput.disabled = !otherSelected;
                    otherReasonInput.readOnly = !otherSelected;
                    if (!otherSelected) otherReasonInput.value = '';
                    otherReasonInput.required = otherSelected;
                    otherReasonInput.setAttribute('aria-required', otherSelected ? 'true' : 'false');
                }
            };

            referralReasonInputs.forEach((input) => input.addEventListener('change', updateOtherReasonRequirement));
            updateOtherReasonRequirement();
        </script>
    @endif
</x-layouts::app>
