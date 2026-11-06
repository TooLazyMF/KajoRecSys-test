<x-layouts::app :title="__('Received Referrals')">
    <main class="min-h-screen bg-[#f6f5f1] p-5 text-slate-900 dark:bg-[#111712] dark:text-zinc-100 sm:p-6 lg:p-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <a href="{{ route('guidance.dashboard') }}" class="text-sm font-medium text-green-700 hover:underline dark:text-green-400">← Back to dashboard</a>
                    <p class="mt-5 text-xs font-bold uppercase tracking-wide text-purple-700 dark:text-purple-400">Slip management</p>
                    <h1 class="mt-1 text-3xl font-bold">Received Student Referral Slips</h1>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Manage referral documents, document-control details, and follow-up forms.</p>
                </div>
            </div>

            <section class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                @if ($referrals->isEmpty())
                    <p class="p-8 text-center text-sm text-neutral-500">There are no received referrals needing follow-up forms.</p>
                @else
                    <div class="w-full space-y-6">
                                @foreach ($referrals as $row)
                                    @php($referral = $row['assignment'])
                                    @php($appointment = $row['appointment'])
                                    @php($isOldReferral = old('referral_id') == $referral->id)
                                    <article class="grid w-full grid-cols-1 items-stretch gap-4 md:grid-cols-2">
                                        <div class="flex h-full min-h-[250px] w-full flex-col rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                                            <h3 class="text-base font-semibold text-neutral-900 dark:text-white">Student Referral Details</h3>
                                            <div class="my-3 border-t border-neutral-200 dark:border-neutral-700"></div>
                                            <div class="space-y-1">
                                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Student Name:</p>
                                            <p class="font-semibold">{{ $referral->student->name }}</p>
                                            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $referral->student->id_number }}</p>
                                            </div>
                                            <div class="mt-3 space-y-1">
                                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Referral Received:</p>
                                            {{ $referral->completed_at?->format('M j, Y g:i A') ?? 'Received' }}
                                            </div>
                                            <div class="mt-auto pt-4">
                                                @if ($appointment)
                                                    <div class="mb-3 rounded-lg border border-green-200 bg-green-50/70 p-3 text-sm dark:border-green-900 dark:bg-green-950/20">
                                                        <p class="font-semibold text-green-900 dark:text-green-200">Appointment scheduled</p>
                                                        <p class="mt-1 text-green-800 dark:text-green-300">
                                                            {{ $appointment->scheduled_at?->format('M j, Y g:i A') }} · {{ $appointment->duration_minutes }} minutes
                                                        </p>
                                                    </div>
                                                @endif
                                                <form method="POST" action="{{ $appointment ? route('guidance.referrals.appointments.update', [$referral, $appointment]) : route('guidance.referrals.appoint', $referral) }}" class="mb-4 space-y-2 rounded-lg border border-blue-200 bg-blue-50/60 p-3 dark:border-blue-900 dark:bg-blue-950/20">
                                                    @csrf
                                                    @if ($appointment)
                                                        @method('PATCH')
                                                    @endif
                                                    <p class="text-xs font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300">{{ $appointment ? 'Edit appointment' : 'Appoint student' }}</p>
                                                    <textarea name="concerns" rows="2" required class="w-full rounded-md border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-800" placeholder="Concern / reason for appointment">{{ old('concerns', $appointment?->concerns ?? $appointment?->purpose ?? 'Follow-up for teacher referral') }}</textarea>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <input type="date" name="scheduled_date" min="{{ now()->format('Y-m-d') }}" value="{{ old('scheduled_date', $appointment?->scheduled_at?->format('Y-m-d')) }}" required class="rounded-md border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-800">
                                                        <input type="time" name="scheduled_time" value="{{ old('scheduled_time', $appointment?->scheduled_at?->format('H:i')) }}" required class="rounded-md border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-800">
                                                    </div>
                                                    @error('scheduled_date') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                                    <select name="duration_minutes" required class="w-full rounded-md border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-800">
                                                        @foreach ([60 => '1 hour', 30 => '30 minutes', 90 => '1 hour 30 minutes', 120 => '2 hours'] as $minutes => $label)
                                                            <option value="{{ $minutes }}" @selected((int) old('duration_minutes', $appointment?->duration_minutes ?? 60) === $minutes)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="w-full rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">{{ $appointment ? 'Update appointment' : 'Schedule appointment' }}</button>
                                                </form>
                                                @if ($row['can_issue_class_admission'])
                                                    <form method="POST" action="{{ route('guidance.slip-review.issue', [$referral, 'class-admission-slip']) }}">
                                                        @csrf
                                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">Issue Class Admission Slip</button>
                                                    </form>
                                                @else
                                                    <p class="text-center text-xs text-neutral-500 dark:text-neutral-400">Already issued for this referral</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex h-full min-h-[250px] w-full flex-col rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                                            <h3 class="text-base font-semibold text-neutral-900 dark:text-white">Document Control Details</h3>
                                            <div class="my-3 border-t border-neutral-200 dark:border-neutral-700"></div>
                                            <div class="space-y-3 text-left">
                                                <p class="text-center text-xs font-semibold {{ $row['referral_pdf_ready'] ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">
                                                    {{ $row['referral_pdf_ready'] ? 'Referral PDF ready' : 'Referral PDF locked until all four values are entered' }}
                                                </p>
                                                @if (! $row['referral_pdf_ready'] && $referral->slipSubmission)
                                                    <button type="button" onclick="document.getElementById('referral-preview-modal-{{ $referral->id }}').showModal()" class="block w-full py-1 text-center text-lg font-semibold text-blue-700 underline dark:text-blue-300">Preview referral form</button>
                                                    <dialog id="referral-preview-modal-{{ $referral->id }}" class="h-[90vh] w-[min(72rem,calc(100%-2rem))] max-w-none rounded-2xl border border-neutral-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-neutral-700 dark:bg-neutral-900">
                                                        <div class="flex h-full flex-col">
                                                            <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-4 dark:border-neutral-700">
                                                                <h2 class="text-lg font-semibold">Referral Form Preview</h2>
                                                                <button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200" aria-label="Close preview">&times;</button>
                                                            </div>
                                                            <iframe src="{{ route('slips.submissions.preview-pdf', $referral->slipSubmission) }}#toolbar=0&amp;navpanes=0" title="Referral form preview" class="min-h-0 w-full flex-1 border-0"></iframe>
                                                        </div>
                                                    </dialog>
                                                @endif
                                                @if ($row['client_information_submission'])
                                                    <dialog id="cif-preview-modal-{{ $referral->id }}" class="h-[90vh] w-[min(72rem,calc(100%-2rem))] max-w-none rounded-2xl border border-neutral-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-neutral-700 dark:bg-neutral-900">
                                                        <div class="flex h-full flex-col">
                                                            <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-4 dark:border-neutral-700">
                                                                <h2 class="text-lg font-semibold">{{ $referral->student->name }} — Client Information Form</h2>
                                                                <div class="flex items-center gap-3">
                                                                    <a href="{{ route('slips.submissions.docx', $row['client_information_submission']) }}" class="text-xs font-semibold text-purple-700 underline dark:text-purple-300">Download Word CIF</a>
                                                                    <button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200" aria-label="Close CIF preview">&times;</button>
                                                                </div>
                                                            </div>
                                                            @if (false)
                                                            @php($cifData = $row['client_information_submission']->data?->getArrayCopy() ?? [])
                                                            <div class="min-h-0 flex-1 overflow-y-auto bg-neutral-50 p-4 dark:bg-neutral-950 sm:p-6">
                                                                <div class="mx-auto max-w-5xl space-y-5">
                                                                    <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 dark:border-purple-900/60 dark:bg-purple-950/30">
                                                                        <p class="text-xs font-bold uppercase tracking-wide text-purple-700 dark:text-purple-300">Filled form example</p>
                                                                        <p class="mt-1 text-sm text-purple-900 dark:text-purple-100">This read-only preview shows how {{ $referral->student->name }}’s submitted values align with the Client Information Form fields.</p>
                                                                        <div class="mt-3 flex flex-wrap gap-2">
                                                                            <a href="{{ route('guidance.referrals.sample-cif-docx-preview') }}" target="_blank" class="inline-flex items-center rounded-lg bg-purple-700 px-3 py-2 text-xs font-semibold text-white hover:bg-purple-800">Open DOCX form preview</a>
                                                                            <a href="{{ route('guidance.referrals.sample-cif-docx') }}" class="inline-flex items-center rounded-lg border border-purple-300 px-3 py-2 text-xs font-semibold text-purple-700 hover:bg-purple-100 dark:border-purple-700 dark:text-purple-300 dark:hover:bg-purple-950">Download Word sample</a>
                                                                            <a href="{{ route('guidance.referrals.cif-docx', $referral) }}" class="inline-flex items-center rounded-lg border border-green-300 px-3 py-2 text-xs font-semibold text-green-700 hover:bg-green-100 dark:border-green-700 dark:text-green-300 dark:hover:bg-green-950">Download CIF with session records</a>
                                                                            <button type="button" onclick="document.getElementById('cif-soft-copy-modal-{{ $referral->id }}').showModal()" class="inline-flex items-center rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100 dark:border-blue-700 dark:text-blue-300 dark:hover:bg-blue-950">Open CIF soft copy</button>
                                                                            <dialog id="cif-soft-copy-modal-{{ $referral->id }}" class="h-[95vh] w-[min(72rem,calc(100%-2rem))] max-w-none rounded-2xl border border-neutral-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-neutral-700 dark:bg-neutral-900">
                                                                                <div class="flex h-full flex-col">
                                                                                    <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-3 dark:border-neutral-700">
                                                                                        <h3 class="text-sm font-semibold">CIF soft copy</h3>
                                                                                        <button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400" aria-label="Close CIF soft copy">&times;</button>
                                                                                    </div>
                                                                                    <iframe src="{{ route('slips.submissions.pdf', $row['client_information_submission']) }}#toolbar=0&amp;navpanes=0&amp;zoom=page-width" title="CIF PDF" class="min-h-0 w-full flex-1 border-0"></iframe>
                                                                                </div>
                                                                            </dialog>
                                                                            <a href="{{ route('guidance.referrals.sample-cif-pdf') }}" target="_blank" class="inline-flex items-center rounded-lg border border-purple-300 px-3 py-2 text-xs font-semibold text-purple-700 hover:bg-purple-100 dark:border-purple-700 dark:text-purple-300 dark:hover:bg-purple-950">Open sample PDF</a>
                                                                        </div>
                                                                    </div>
                                                                    @foreach ($cifDefinition['groups'] as $group)
                                                                        <section class="rounded-xl border border-neutral-200 bg-white p-4 text-left shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-5">
                                                                            <h3 class="border-b border-neutral-200 pb-3 text-left text-sm font-bold text-neutral-900 dark:border-neutral-700 dark:text-white">{{ $group['title'] }}</h3>
                                                                            <div class="mt-4 grid items-start gap-x-5 gap-y-4 sm:grid-cols-2">
                                                                                @foreach ($group['fields'] as $field)
                                                                                    @php($fieldValue = filled($cifData[$field['key']] ?? null) ? $cifData[$field['key']] : ($cifSampleData[$field['key']] ?? null))
                                                                                    <div class="flex min-w-0 flex-col text-left {{ ($field['type'] ?? 'text') === 'textarea' ? 'sm:col-span-2' : '' }}">
                                                                                        <p class="min-h-8 text-left text-[11px] font-semibold uppercase leading-4 tracking-wide text-neutral-500 dark:text-neutral-400">{{ $field['label'] }}</p>
                                                                                        @if (($field['type'] ?? 'text') === 'checkboxes')
                                                                                            <div class="mt-1 space-y-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-left dark:border-neutral-700 dark:bg-neutral-800">
                                                                                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                                                                                    <div class="flex items-start gap-2 text-left text-sm leading-5 text-neutral-800 dark:text-neutral-200">
                                                                                                        <span class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded border {{ is_array($fieldValue) && in_array($optionValue, $fieldValue, true) ? 'border-green-600 bg-green-600 text-white' : 'border-neutral-300 dark:border-neutral-600' }}">{{ is_array($fieldValue) && in_array($optionValue, $fieldValue, true) ? '✓' : '' }}</span>
                                                                                                        <span>{{ $optionLabel }}</span>
                                                                                                    </div>
                                                                                                @endforeach
                                                                                            </div>
                                                                                        @elseif (($field['type'] ?? 'text') === 'textarea')
                                                                                            <textarea readonly rows="3" class="mt-1 w-full resize-none rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-left text-sm leading-5 text-neutral-900 outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">{{ filled($fieldValue) ? $fieldValue : '' }}</textarea>
                                                                                        @else
                                                                                            <input readonly value="{{ filled($fieldValue) ? $fieldValue : '' }}" placeholder="No response" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-left text-sm leading-5 text-neutral-900 outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                                                                        @endif
                                                                                    </div>
                                                                                @endforeach
                                                                            </div>
                                                                        </section>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                            @endif
                                                            <iframe src="{{ route('slips.submissions.pdf', $row['client_information_submission']) }}#toolbar=0&amp;navpanes=0&amp;zoom=page-width" title="{{ $referral->student->name }} Client Information Form PDF" class="min-h-0 w-full flex-1 border-0"></iframe>
                                                            <section class="shrink-0 border-t border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/20">
                                                                <div class="border-b border-blue-200 px-5 py-3 dark:border-blue-900">
                                                                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Counseling history</p>
                                                                    <h3 class="mt-1 text-sm font-bold text-blue-950 dark:text-blue-100">Appointment records</h3>
                                                                </div>
                                                                @if ($row['appointments']->isEmpty())
                                                                    <p class="px-5 py-4 text-xs text-blue-900/70 dark:text-blue-100/70">No appointment records are available for this student.</p>
                                                                @else
                                                                    <div class="max-h-64 overflow-auto bg-white dark:bg-neutral-900">
                                                                        <table class="min-w-full text-left text-xs">
                                                                            <thead class="bg-blue-100 text-blue-900 dark:bg-blue-950/50 dark:text-blue-100">
                                                                                <tr>
                                                                                    <th class="px-4 py-2 font-semibold">Date</th>
                                                                                    <th class="px-4 py-2 font-semibold">Concerns</th>
                                                                                    <th class="px-4 py-2 font-semibold">Interventions / Actions Taken</th>
                                                                                    <th class="px-4 py-2 font-semibold">Attending Counselor</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody class="divide-y divide-blue-100 dark:divide-neutral-800">
                                                                                @foreach ($row['appointments'] as $appointmentRecord)
                                                                                    <tr class="align-top text-neutral-800 dark:text-neutral-200">
                                                                                        <td class="whitespace-nowrap px-4 py-3">{{ ($appointmentRecord->scheduled_at ?? $appointmentRecord->completed_at)?->format('m/d/Y') ?? '—' }}</td>
                                                                                        <td class="min-w-40 px-4 py-3">{{ $appointmentRecord->concerns ?? $appointmentRecord->purpose ?? '—' }}</td>
                                                                                        <td class="min-w-56 px-4 py-3">{{ $appointmentRecord->action_taken ?? '—' }}</td>
                                                                                        <td class="whitespace-nowrap px-4 py-3">{{ $appointmentRecord->reviewer?->name ?? '—' }}</td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                @endif
                                                            </section>
                                                        </div>
                                                    </dialog>
                                                @endif
                                                <form method="POST" action="{{ route('guidance.referrals.document-control', $referral) }}" class="space-y-2">
                                                    @csrf
                                                    <input type="hidden" name="referral_id" value="{{ $referral->id }}">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-300">Document Code<input name="details[document_code]" value="{{ $isOldReferral ? old('details.document_code') : ($row['document_control_data']['document_code'] ?? '') }}" maxlength="30" required class="mt-1 w-full rounded-md border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-800"></label>
                                                        <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-300">Revision Number<input name="details[revision_number]" value="{{ $isOldReferral ? old('details.revision_number') : ($row['document_control_data']['revision_number'] ?? '') }}" maxlength="20" required class="mt-1 w-full rounded-md border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-800"></label>
                                                        <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-300">Effectivity Date<input type="date" name="details[effectivity_date]" value="{{ $isOldReferral ? old('details.effectivity_date') : ($row['document_control_data']['effectivity_date'] ?? '') }}" required class="mt-1 w-full rounded-md border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-800"></label>
                                                        <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-300">Control No.<input name="details[control_number]" value="{{ $isOldReferral ? old('details.control_number') : ($row['document_control_data']['control_number'] ?? '') }}" maxlength="30" required class="mt-1 w-full rounded-md border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-800"></label>
                                                    </div>
                                                    <button type="submit" class="w-full rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">{{ $row['referral_pdf_ready'] ? 'Update Details & Refresh PDF' : 'Save Details & Release PDF' }}</button>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts::app>
