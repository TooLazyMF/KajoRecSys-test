<x-layouts::app :title="__('Manage Users')">
    <main class="min-h-screen bg-[#f6f5f1] p-5 text-slate-900 dark:bg-[#111712] dark:text-zinc-100 sm:p-6 lg:p-8">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header>
                <h1 class="text-3xl font-bold">Manage Users</h1>
                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                    Activate or deactivate teacher and student accounts. Students can also be marked as graduates.
                </p>
            </header>

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
                    {{ $errors->first('action') }}
                </div>
            @endif

            <section class="space-y-5 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-6">
                <div>
                    <h2 class="text-xl font-bold">Student Accounts</h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Students arranged by course and year level.</p>
                </div>
                <label class="block">
                    <span class="mb-2 block text-base font-semibold">Search students</span>
                    <input id="manage_student_search" type="search" autocomplete="off" placeholder="Search by name, ID, or course" class="w-full rounded-lg border-neutral-300 bg-white px-4 py-3 text-base shadow-sm focus:border-green-700 focus:ring-green-700 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white">
                </label>
                <p class="text-sm text-neutral-500 dark:text-neutral-400"><span id="manage_student_count">{{ $students->count() }}</span> registered {{ str()->plural('student', $students->count()) }}</p>
                <div id="manage_student_groups" class="space-y-3">
                    @forelse ($students->groupBy(fn ($student) => $studentReferences->get(\App\Models\StudentReference::normalizeIdNumber($student->id_number))?->course ?: '')->sortKeys() as $course => $courseStudents)
                        @if (filled($course))
                            <details class="manage-course-group group overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700" data-course-group>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-neutral-50 px-5 py-4 hover:bg-emerald-50 dark:bg-neutral-800 dark:hover:bg-neutral-700">
                                    <span><span class="block text-base font-semibold">{{ $course }}</span><span class="text-sm text-neutral-500 dark:text-neutral-400"><span data-group-count>{{ $courseStudents->count() }}</span> {{ str()->plural('student', $courseStudents->count()) }}</span></span><span class="transition group-open:rotate-180" aria-hidden="true">⌄</span>
                                </summary>
                                <div class="space-y-5 border-t border-neutral-200 p-4 dark:border-neutral-700">
                        @else
                            <div class="space-y-5" data-course-group>
                                <span class="hidden" data-group-count>{{ $courseStudents->count() }}</span>
                        @endif
                                @foreach ($courseStudents->groupBy(fn ($student) => $studentReferences->get(\App\Models\StudentReference::normalizeIdNumber($student->id_number))->year)->sortKeys() as $yearLevel => $yearStudents)
                                    <section data-year-group>
                                        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-neutral-600 dark:text-neutral-300">{{ $yearLevel }}</h3>
                                        <div class="grid gap-3 md:grid-cols-2">
                                            @foreach ($yearStudents as $user)
                                                @php($reference = $studentReferences->get(\App\Models\StudentReference::normalizeIdNumber($user->id_number)))
                                                <article class="manage-student-card rounded-lg border border-neutral-200 p-4 dark:border-neutral-700" data-search="{{ strtolower($user->name.' '.$user->id_number.' '.$user->email.' '.$course.' '.$reference?->year) }}">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <h4 class="text-xl font-bold">{{ $user->name }}</h4>
                                                        <div class="flex flex-wrap gap-2">
                                                            <form id="user-status-form-{{ $user->id }}" method="POST" action="{{ route('admin.users.status', $user) }}">
                                                                @csrf<input type="hidden" name="action" value="{{ $user->is_active ? 'deactivate' : 'activate' }}">
                                                                @if ($user->is_active)
                                                                    <button type="button" data-deactivate-trigger data-form-id="user-status-form-{{ $user->id }}" data-user-name="{{ $user->name }}" class="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">Deactivate</button>
                                                                @else
                                                                    <button type="submit" class="rounded-lg border border-neutral-300 px-3 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800">Activate</button>
                                                                @endif
                                                            </form>
                                                            @if (! $user->is_graduated)
                                                                <form method="POST" action="{{ route('admin.users.status', $user) }}">@csrf<input type="hidden" name="action" value="graduate"><button type="submit" class="rounded-lg bg-[#09ba44] px-3 py-2 text-xs font-semibold text-[#092010] hover:bg-[#079b38] dark:bg-[#20783e] dark:text-white">Mark as graduate</button></form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 flex flex-wrap gap-2 text-sm">
                                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-200 text-gray-700 dark:bg-zinc-700 dark:text-zinc-300' }}">{{ $user->is_active ? 'Active' : 'Deactivated' }}</span>
                                                        @if ($user->is_graduated)<span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">Graduated</span>@endif
                                                    </div>
                                                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-neutral-600 dark:text-neutral-300">
                                                        <div class="flex flex-wrap gap-x-1"><dt class="font-semibold">Student ID:</dt><dd>{{ $user->id_number ?: 'Not provided' }}</dd></div>
                                                        <div class="flex flex-wrap gap-x-1"><dt class="font-semibold">Year Lvl:</dt><dd>{{ $reference?->year ?: 'Not listed' }}</dd></div>
                                                        <div class="flex flex-wrap gap-x-1"><dt class="font-semibold">Gender:</dt><dd>{{ $user->gender ? ucfirst($user->gender) : 'Not provided' }}</dd></div>
                                                        <div class="flex flex-wrap gap-x-1"><dt class="font-semibold">Email:</dt><dd class="break-all">{{ $user->email }}</dd></div>
                                                    </dl>
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                        @if (filled($course))
                                </div>
                            </details>
                        @else
                            </div>
                        @endif
                    @empty
                        <p class="rounded-lg bg-neutral-50 p-5 text-center text-neutral-500 dark:bg-neutral-800">No student accounts are registered.</p>
                    @endforelse
                </div>
                <p id="manage_no_student_matches" class="hidden rounded-lg bg-neutral-50 p-5 text-center text-neutral-500 dark:bg-neutral-800">No students match your search.</p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="px-6 pt-6 text-xl font-bold">Teacher Accounts</h2>
                <div class="overflow-x-auto p-4 sm:p-6">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 dark:bg-neutral-800"><tr><th class="px-6 py-3 font-medium text-neutral-500">Teacher</th><th class="px-6 py-3 font-medium text-neutral-500">Account</th><th class="px-6 py-3 font-medium text-neutral-500">Actions</th></tr></thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse ($teachers as $user)
                                <tr>
                                    <td class="px-6 py-4"><p class="font-medium">{{ $user->name }}</p><p class="text-xs text-neutral-500">{{ $user->email }}</p></td>
                                    <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-200 text-gray-700 dark:bg-zinc-700 dark:text-zinc-300' }}">{{ $user->is_active ? 'Active' : 'Deactivated' }}</span></td>
                                    <td class="px-6 py-4"><form id="user-status-form-{{ $user->id }}" method="POST" action="{{ route('admin.users.status', $user) }}">@csrf<input type="hidden" name="action" value="{{ $user->is_active ? 'deactivate' : 'activate' }}">@if ($user->is_active)<button type="button" data-deactivate-trigger data-form-id="user-status-form-{{ $user->id }}" data-user-name="{{ $user->name }}" class="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">Deactivate</button>@else<button type="submit" class="rounded-lg border border-neutral-300 px-3 py-2 text-xs font-semibold">Activate</button>@endif</form></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-6 py-8 text-center text-neutral-500">No teacher accounts are registered.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($teachers->hasPages())<div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">{{ $teachers->links() }}</div>@endif
            </section>
        </div>
    </main>

    
                    <div
                        id="deactivate-modal"
                        class="fixed inset-0 z-50 hidden"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="deactivate-modal-title"
                    >
                        {{-- Modal backdrop --}}
                        <div
                            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                            data-deactivate-cancel
                        ></div>

                        {{-- Modal content --}}
                        <div
                            class="relative mx-auto mt-24 w-[calc(100%-2rem)] max-w-md
                            rounded-2xl border border-neutral-200 bg-white p-6
                            shadow-2xl
                            dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            {{-- Modal heading --}}
                            <h2
                                id="deactivate-modal-title"
                                class="text-xl font-bold text-slate-900 dark:text-zinc-100"
                            >
                                Deactivate account?
                            </h2>

                            {{-- Confirmation message --}}
                            <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-zinc-300">
                                Are you sure you want to deactivate
                                <span
                                    id="deactivate-user-name"
                                    class="font-bold text-slate-900 dark:text-white"
                                ></span>?
                                They will no longer be able to access their account.
                            </p>

                            {{-- Modal actions --}}
                            <div class="mt-7 flex justify-end gap-3">

                                {{-- Cancel button --}}
                                <button
                                    type="button"
                                    data-deactivate-cancel
                                    class="rounded-lg border border-neutral-300
                                    bg-white px-4 py-2.5 text-sm font-semibold
                                    text-neutral-700 transition-colors
                                    hover:bg-neutral-100
                                    focus:outline-none focus:ring-2 focus:ring-neutral-400
                                    focus:ring-offset-2
                                    dark:border-zinc-600 dark:bg-zinc-800
                                    dark:text-zinc-200 dark:hover:bg-zinc-700
                                    dark:focus:ring-offset-zinc-900"
                                >
                                    Cancel
                                </button>

                                {{-- Confirm deactivation button --}}
                     <button
                        type="button"
                        id="deactivate-confirm"
                        style="background-color: #dc2626; color: #ffffff;"
                        class="rounded-lg border border-red-700 px-4 py-2.5
                            text-sm font-semibold transition-colors
                            hover:border-red-800 hover:bg-red-700
                            focus:outline-none focus:ring-2 focus:ring-red-500
                            focus:ring-offset-2
                            dark:border-red-500 dark:bg-red-600 dark:text-white
                            dark:hover:bg-red-500 dark:focus:ring-offset-zinc-900"
                    >
                        Deactivate
                    </button>

                            </div>
                        </div>
                    </div>

</x-layouts::app>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const studentSearch = document.getElementById('manage_student_search');
        if (studentSearch) {
            const courseGroups = Array.from(document.querySelectorAll('[data-course-group]'));
            const visibleCount = document.getElementById('manage_student_count');
            const noMatches = document.getElementById('manage_no_student_matches');

            studentSearch.addEventListener('input', () => {
                const query = studentSearch.value.trim().toLocaleLowerCase();
                let matchingStudents = 0;

                courseGroups.forEach((group) => {
                    const cards = Array.from(group.querySelectorAll('.manage-student-card'));
                    let groupCount = 0;
                    cards.forEach((card) => {
                        const matches = card.dataset.search.includes(query);
                        card.hidden = !matches;
                        if (matches) groupCount += 1;
                    });
                    group.hidden = groupCount === 0;
                    group.querySelector('[data-group-count]').textContent = groupCount;
                    group.querySelectorAll('[data-year-group]').forEach((yearGroup) => {
                        yearGroup.hidden = !yearGroup.querySelector('.manage-student-card:not([hidden])');
                    });
                    if (query && groupCount > 0) group.open = true;
                    matchingStudents += groupCount;
                });

                visibleCount.textContent = matchingStudents;
                noMatches.classList.toggle('hidden', matchingStudents > 0);
            });
        }

        const modal = document.getElementById('deactivate-modal');
        const userName = document.getElementById('deactivate-user-name');
        const confirmButton = document.getElementById('deactivate-confirm');
        let formId = null;

        const closeModal = () => {
            modal.classList.add('hidden');
            formId = null;
        };

        document.querySelectorAll('[data-deactivate-trigger]').forEach((button) => {
            button.addEventListener('click', () => {
                formId = button.dataset.formId;
                userName.textContent = button.dataset.userName;
                modal.classList.remove('hidden');
                confirmButton.focus();
            });
        });

        document.querySelectorAll('[data-deactivate-cancel]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        confirmButton.addEventListener('click', () => {
            if (formId) {
                document.getElementById(formId).submit();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    });
</script>
