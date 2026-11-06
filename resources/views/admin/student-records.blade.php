<x-layouts::app :title="__('Students Records')">
    <main class="min-h-screen bg-[#f6f5f1] p-5 text-slate-900 dark:bg-[#111712] dark:text-zinc-100 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-4 rounded-2xl bg-white p-6 shadow-sm dark:bg-neutral-900 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <a href="{{ route('guidance.dashboard') }}" class="text-sm font-semibold text-green-700 hover:underline dark:text-green-300">← Guidance dashboard</a>
                    <p class="mt-5 text-sm font-bold uppercase tracking-wide text-green-700 dark:text-green-400">Guidance Office</p>
                    <h1 class="mt-1 text-3xl font-bold">Students Records</h1>
                    <p class="mt-2 text-base text-neutral-600 dark:text-neutral-300">Review approved CIFs, completed slips, and completed appointments for registered students.</p>
                </div>
                <span class="rounded-full bg-green-100 px-4 py-2 text-base font-semibold text-green-800 dark:bg-green-950/50 dark:text-green-200">{{ $students->count() }} students</span>
            </header>

            <section class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[880px] text-left text-base">
                        <thead class="bg-neutral-100 text-sm font-bold uppercase tracking-wide text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                            <tr>
                                <th class="px-5 py-4 sm:px-7">Student</th>
                                <th class="px-5 py-4">Student ID</th>
                                <th class="px-5 py-4">Account</th>
                                <th class="px-5 py-4">Records</th>
                            </tr>
                        </thead>
                        <tbody id="student-records-list" class="divide-y divide-neutral-200 dark:divide-neutral-800">
                            @forelse ($students as $student)
                                @php
                                    $cifSubmission = $student->slipSubmissions->first();
                                    $slipRecords = $student->slipAssignments
                                        ->sortByDesc('completed_at')
                                        ->map(function ($assignment) {
                                            $appointment = $assignment->slip === 'student-referral-slip'
                                                ? $assignment->appointments->first()
                                                : null;
                                            $appointmentLabel = $assignment->slip !== 'student-referral-slip'
                                                ? '—'
                                                : match ($appointment?->status) {
                                                    'accepted' => 'Scheduled by counselor',
                                                    'done' => 'Appointment completed',
                                                    'missed' => 'Appointment missed',
                                                    default => 'No appointment scheduled',
                                                };
                                            if ($appointment?->scheduled_at && $assignment->slip === 'student-referral-slip') {
                                                $appointmentLabel .= ' · '.$appointment->scheduled_at->format('M j, Y g:i A');
                                            }

                                            $submission = $assignment->slipSubmission;
                                            $submissionData = $submission?->data?->getArrayCopy() ?? [];
                                            $referralPdfReady = $assignment->slip !== 'student-referral-slip'
                                                || collect(['document_code', 'revision_number', 'effectivity_date', 'control_number'])
                                                    ->every(fn ($key) => filled($submissionData[$key] ?? null));

                                            return [
                                                'title' => $assignment->slip === 'student-referral-slip' ? 'Student Referral Slip' : 'Class Admission Slip',
                                                'date' => $assignment->completed_at?->format('M j, Y g:i A') ?? 'Date not recorded',
                                                'status' => 'Completed',
                                                'appointment' => $appointmentLabel,
                                                'url' => $submission && $referralPdfReady ? route('slips.submissions.pdf', $submission) : null,
                                            ];
                                        })
                                        ->values();
                                    $appointments = $student->appointments
                                        ->map(fn ($appointment) => [
                                            'date' => ($appointment->scheduled_at ?? $appointment->completed_at)?->format('M j, Y g:i A') ?? 'Date not recorded',
                                            'completed' => $appointment->completed_at?->format('M j, Y g:i A') ?? 'Date not recorded',
                                            'status' => $appointment->status === 'missed' ? 'Client failed to attend' : 'Done',
                                            'purpose' => $appointment->purpose,
                                            'concerns' => $appointment->concerns,
                                            'action' => $appointment->action_taken,
                                            'duration' => $appointment->duration_minutes ? $appointment->duration_minutes.' minutes' : 'Not recorded',
                                            'counselor' => $appointment->reviewer?->name ?? 'Not recorded',
                                        ])
                                        ->values();
                                @endphp
                                <tr>
                                    <td class="px-5 py-4 font-semibold sm:px-7">{{ $student->name }}</td>
                                    <td class="px-5 py-4 text-neutral-600 dark:text-neutral-300">{{ $student->id_number ?: '—' }}</td>
                                    <td class="px-5 py-4 text-neutral-600 dark:text-neutral-300">{{ $student->is_active && ! $student->is_graduated ? 'Active' : 'Archived' }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" data-cif-url="{{ $cifSubmission ? route('slips.submissions.pdf', $cifSubmission) : '' }}" data-student-name="{{ $student->name }}" @disabled(! $cifSubmission) class="rounded-lg border border-blue-300 px-3 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-blue-800 dark:text-blue-200 dark:hover:bg-blue-950/40">CIF</button>
                                            <button type="button" data-appointments="{{ json_encode($appointments->all(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}" data-student-name="{{ $student->name }}" @disabled($appointments->isEmpty()) class="rounded-lg border border-sky-300 px-3 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-sky-800 dark:text-sky-200 dark:hover:bg-sky-950/40">Appointment</button>
                                            <button type="button" data-slip-records="{{ json_encode($slipRecords->all(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}" data-student-name="{{ $student->name }}" @disabled($slipRecords->isEmpty()) class="rounded-lg border border-purple-300 px-3 py-2 text-sm font-semibold text-purple-800 hover:bg-purple-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-purple-800 dark:text-purple-200 dark:hover:bg-purple-950/40">Referrals and Class admission</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-12 text-center text-base text-neutral-500">No registered students were found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <dialog id="student-record-preview-modal" class="h-[90vh] w-[min(72rem,calc(100%-2rem))] max-w-none rounded-2xl border border-neutral-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-neutral-700 dark:bg-neutral-900">
            <div class="flex h-full flex-col">
                <div class="flex items-center justify-between gap-4 border-b border-neutral-200 px-5 py-4 dark:border-neutral-700">
                    <div>
                        <h2 id="student-record-preview-title" class="text-lg font-bold">Student record</h2>
                        <p id="student-record-preview-note" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Read-only record preview.</p>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" class="text-3xl leading-none text-neutral-400" aria-label="Close record preview">&times;</button>
                </div>
                <iframe id="student-record-preview-frame" title="Read-only student record" class="hidden min-h-0 w-full flex-1 border-0"></iframe>
                <p id="student-record-preview-unavailable" class="hidden m-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-base text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">The completed record is saved, but its document preview is not available.</p>
            </div>
        </dialog>

        <dialog id="student-appointments-modal" class="h-[85vh] w-[min(72rem,calc(100%-2rem))] max-w-none rounded-2xl border border-sky-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-sky-900 dark:bg-neutral-900">
            <div class="flex h-full flex-col">
                <div class="flex items-center justify-between gap-4 border-b border-neutral-200 px-5 py-4 dark:border-neutral-700">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wide text-sky-700 dark:text-sky-400">Completed appointments</p>
                        <h2 id="student-appointments-title" class="mt-1 text-xl font-bold">Appointments</h2>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" class="text-3xl leading-none text-neutral-400" aria-label="Close appointments">&times;</button>
                </div>
                <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-2">
                    <div id="student-appointments-list" class="min-h-0 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800"></div>
                    <section id="student-appointment-detail" class="hidden min-h-0 translate-x-4 overflow-y-auto border-l border-neutral-200 p-5 opacity-0 transition-all duration-300 dark:border-neutral-700 md:p-7" aria-live="polite">
                        <button id="student-appointment-back" type="button" class="mb-4 text-sm font-semibold text-sky-700 hover:underline dark:text-sky-300 md:hidden">← All appointments</button>
                        <h3 id="student-appointment-detail-title" class="text-xl font-bold">Appointment details</h3>
                        <div id="student-appointment-detail-content" class="mt-5 space-y-4 text-base"></div>
                    </section>
                </div>
            </div>
        </dialog>
    </main>

    <script>
        (() => {
            const recordsList = document.getElementById('student-records-list');
            const previewModal = document.getElementById('student-record-preview-modal');
            const previewFrame = document.getElementById('student-record-preview-frame');
            const previewTitle = document.getElementById('student-record-preview-title');
            const previewNote = document.getElementById('student-record-preview-note');
            const previewUnavailable = document.getElementById('student-record-preview-unavailable');
            const appointmentModal = document.getElementById('student-appointments-modal');
            const appointmentList = document.getElementById('student-appointments-list');
            const appointmentDetail = document.getElementById('student-appointment-detail');
            const appointmentDetailTitle = document.getElementById('student-appointment-detail-title');
            const appointmentDetailContent = document.getElementById('student-appointment-detail-content');

            recordsList.addEventListener('click', (event) => {
                const cifButton = event.target.closest('[data-cif-url]');
                if (cifButton && cifButton.dataset.cifUrl) {
                    previewTitle.textContent = `${cifButton.dataset.studentName} — CIF`;
                    previewNote.textContent = 'Client Information Form · read-only';
                    previewUnavailable.classList.add('hidden');
                    previewFrame.classList.remove('hidden');
                    previewFrame.src = `${cifButton.dataset.cifUrl}#toolbar=0&navpanes=0&zoom=page-width`;
                    previewModal.showModal();
                    return;
                }

                const slipButton = event.target.closest('[data-slip-records]');
                if (slipButton) {
                    const table = document.createElement('table');
                    table.className = 'w-full min-w-[48rem] text-left text-base';
                    table.innerHTML = '<thead class="bg-neutral-100 text-sm font-bold uppercase tracking-wide dark:bg-neutral-800"><tr><th class="px-4 py-3">Completed slip</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Appointment</th></tr></thead>';
                    const body = document.createElement('tbody');
                    body.className = 'divide-y divide-neutral-100 dark:divide-neutral-800';

                    JSON.parse(slipButton.dataset.slipRecords).forEach((record) => {
                        const row = document.createElement('tr');
                        const recordCell = document.createElement('td');
                        recordCell.className = 'px-4 py-4';
                        const openRecord = document.createElement('button');
                        openRecord.type = 'button';
                        openRecord.className = 'font-semibold text-purple-800 underline dark:text-purple-200';
                        openRecord.textContent = record.title;
                        openRecord.addEventListener('click', () => {
                            previewTitle.textContent = `${slipButton.dataset.studentName} — ${record.title}`;
                            previewNote.textContent = `${record.status} · ${record.date}`;
                            previewFrame.classList.toggle('hidden', !record.url);
                            previewUnavailable.classList.toggle('hidden', Boolean(record.url));
                            previewFrame.src = record.url ? `${record.url}#toolbar=0&navpanes=0&zoom=page-width` : '';
                            previewModal.showModal();
                        });
                        recordCell.append(openRecord);

                        [record.date, record.status, record.appointment].forEach((value) => {
                            const cell = document.createElement('td');
                            cell.className = 'px-4 py-4 text-neutral-600 dark:text-neutral-300';
                            cell.textContent = value;
                            row.append(cell);
                        });
                        row.prepend(recordCell);
                        body.append(row);
                    });
                    table.append(body);

                    let slipModal = document.getElementById('student-slips-modal');
                    if (!slipModal) {
                        slipModal = document.createElement('dialog');
                        slipModal.id = 'student-slips-modal';
                        slipModal.className = 'max-h-[85vh] w-[min(64rem,calc(100%-2rem))] max-w-none overflow-auto rounded-2xl border border-purple-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-purple-900 dark:bg-neutral-900';
                        slipModal.innerHTML = '<div class="flex items-center justify-between gap-4 border-b border-neutral-200 px-5 py-4 dark:border-neutral-700"><div><p class="text-sm font-bold uppercase tracking-wide text-purple-700 dark:text-purple-400">Completed slips</p><h2 id="student-slips-title" class="mt-1 text-xl font-bold"></h2></div><button type="button" class="text-3xl leading-none text-neutral-400" aria-label="Close slips">&times;</button></div><div class="overflow-x-auto p-4" id="student-slips-table"></div>';
                        slipModal.querySelector('button').addEventListener('click', () => slipModal.close());
                        document.body.append(slipModal);
                    }
                    slipModal.querySelector('#student-slips-title').textContent = `${slipButton.dataset.studentName} — Referrals and Class admission`;
                    slipModal.querySelector('#student-slips-table').replaceChildren(table);
                    slipModal.showModal();
                    return;
                }

                const appointmentsButton = event.target.closest('[data-appointments]');
                if (!appointmentsButton) {
                    return;
                }

                appointmentList.replaceChildren();
                appointmentDetail.classList.add('hidden', 'translate-x-4', 'opacity-0');
                appointmentList.classList.remove('hidden');
                appointmentDetailTitle.textContent = 'Appointment details';
                appointmentDetailContent.replaceChildren();
                document.getElementById('student-appointments-title').textContent = `${appointmentsButton.dataset.studentName} — Appointments`;

                JSON.parse(appointmentsButton.dataset.appointments).forEach((appointment) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'w-full px-5 py-4 text-left transition hover:bg-sky-50 dark:hover:bg-sky-950/30';
                    const date = document.createElement('span');
                    date.className = 'block text-base font-semibold';
                    date.textContent = appointment.date;
                    const status = document.createElement('span');
                    status.className = 'mt-1 block text-sm text-neutral-500 dark:text-neutral-400';
                    status.textContent = appointment.status;
                    button.append(date, status);
                    button.addEventListener('click', () => {
                        appointmentDetailTitle.textContent = appointment.status;
                        appointmentDetailContent.replaceChildren();
                        [
                            ['Scheduled', appointment.date],
                            ['Completed', appointment.completed],
                            ['Purpose', appointment.purpose],
                            ['Concerns', appointment.concerns],
                            ['Interventions / actions taken', appointment.action],
                            ['Duration', appointment.duration],
                            ['Attending counselor', appointment.counselor],
                        ].forEach(([label, value]) => {
                            const item = document.createElement('div');
                            const itemLabel = document.createElement('p');
                            itemLabel.className = 'text-sm font-semibold text-neutral-500 dark:text-neutral-400';
                            itemLabel.textContent = label;
                            const itemValue = document.createElement('p');
                            itemValue.className = 'mt-1 whitespace-pre-wrap text-base';
                            itemValue.textContent = value || '—';
                            item.append(itemLabel, itemValue);
                            appointmentDetailContent.append(item);
                        });
                        appointmentList.classList.add('hidden', 'md:block');
                        appointmentDetail.classList.remove('hidden');
                        window.requestAnimationFrame(() => appointmentDetail.classList.remove('translate-x-4', 'opacity-0'));
                    });
                    appointmentList.append(button);
                });
                appointmentModal.showModal();
            });

            document.getElementById('student-appointment-back').addEventListener('click', () => {
                appointmentList.classList.remove('hidden');
                appointmentDetail.classList.add('hidden', 'translate-x-4', 'opacity-0');
            });
            previewModal.addEventListener('close', () => {
                previewFrame.src = '';
            });
        })();
    </script>
</x-layouts::app>
