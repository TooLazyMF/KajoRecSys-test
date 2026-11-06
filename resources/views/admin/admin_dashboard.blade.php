<x-layouts::app :title="__('Guidance Dashboard')">
    <main class="min-h-screen bg-[#f6f5f1] p-5 text-slate-900 dark:bg-[#111712] dark:text-zinc-100 sm:p-6 lg:p-8">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <div class="grid grid-cols-1 gap-[10px] sm:grid-cols-2 xl:grid-cols-5">
                <header class="col-span-full rounded-2xl bg-[#09ba44] p-8 text-[#092010] shadow-lg dark:bg-[#20783e] dark:text-white">
                    <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80">Guidance Office</p>
                            <h1 class="mt-1 text-3xl font-bold">Welcome to KAJOREC</h1>
                            <p class="mt-2 opacity-90">Guidance Office Record Management and Appointment System</p>
                        </div>
                        <div class="text-left md:text-right">
                            <p class="text-sm opacity-80">{{ now()->format('l') }}</p>
                            <p class="text-xl font-semibold">{{ now()->format('F d, Y') }}</p>
                            <button type="button" onclick="document.getElementById('monthly-report-modal').showModal()" class="mt-4 inline-flex items-center rounded-lg bg-white/90 px-4 py-2 text-sm font-semibold text-green-800 shadow-sm transition hover:bg-white dark:bg-zinc-900/90 dark:text-green-300 dark:hover:bg-zinc-900">
                                Generate monthly report
                            </button>
                        </div>
                    </div>
                </header>

            <section class="contents" aria-label="Guidance statistics">
                @php
                    $statCardStyles = [
                        'bg-emerald-50 border-emerald-200 hover:border-emerald-400',
                        'bg-sky-50 border-sky-200 hover:border-sky-400',
                        'bg-amber-50 border-amber-200 hover:border-amber-400',
                        'bg-orange-50 border-orange-200 hover:border-orange-400',
                        'bg-violet-50 border-violet-200 hover:border-violet-400',
                    ];
                    $statIconStyles = [
                        'bg-emerald-100 text-emerald-700',
                        'bg-sky-100 text-sky-700',
                        'bg-amber-100 text-amber-700',
                        'bg-orange-100 text-orange-700',
                        'bg-violet-100 text-violet-700',
                    ];
                @endphp
                @foreach ([
                    ['Total Students', $totalStudentsCount, 'Registered students', 'bg-green-100', '♟', 'total-students'],
                    ["Today's Appointments", $todayAppointmentsCount, 'Scheduled today', 'bg-blue-100', '▦', 'todays-appointments'],
                    ['Pending Appointment Approvals', $pendingAppointmentsCount, 'Appointment requests awaiting review', 'bg-yellow-100', '◷', 'waiting-for-appointment-approval'],
                    ['Missed / Done', $missedDoneAppointmentsCount, 'Finished or missed', 'bg-orange-100', '✓', 'miss-done-appointments'],
                    ['Referrals Missing Document Details', $activeReferralsCount, 'Missing Document Code, Revision Number, or Control No.', 'bg-purple-100', '↗', 'active-referrals'],
                ] as [$label, $count, $description, $iconBg, $icon, $modalKey])
                    <button type="button" onclick="document.getElementById('{{ $modalKey }}-modal').showModal()" class="w-full rounded-2xl border p-4 text-left shadow-sm transition hover:-translate-y-1 hover:shadow-md {{ $statCardStyles[$loop->index] }} dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-green-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium leading-4 text-neutral-500 dark:text-neutral-400">{{ $label }}</p>
                                <p class="mt-1 text-2xl font-bold text-neutral-900 dark:text-white">{{ $count }}</p>
                            </div>
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $statIconStyles[$loop->index] }} text-lg font-semibold dark:bg-zinc-800 dark:text-emerald-300">{{ $icon }}</span>
                        </div>
                        <p class="mt-2 text-[11px] leading-4 text-neutral-500">{{ $description }}</p>
                    </button>
                @endforeach
            </section>
            </div>

            <dialog id="monthly-report-modal" class="h-[90vh] w-[min(72rem,calc(100%-2rem))] max-w-none rounded-2xl border border-neutral-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex h-full flex-col">
                    <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-4 dark:border-neutral-700">
                        <h2 class="text-lg font-semibold">Monthly Student Report</h2>
                        <button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200" aria-label="Close report">&times;</button>
                    </div>
                    <iframe src="{{ route('guidance.reports.monthly', ['embedded' => 1], absolute: false) }}" title="Monthly Student Report" class="min-h-0 w-full flex-1 border-0"></iframe>
                </div>
            </dialog>

            @php
                $calendarData = $calendarAppointments->map(fn ($appointment) => [
                    'date' => $appointment->scheduled_at->format('Y-m-d'),
                    'time' => $appointment->scheduled_at->format('g:i A'),
                    'student' => $appointment->requester->name,
                    'status' => ucfirst($appointment->status),
                    'duration' => $appointment->duration_minutes,
                    'purpose' => $appointment->purpose,
                ])->values();
            @endphp

            <dialog id="total-students-modal" class="w-[min(32rem,calc(100%-2rem))] rounded-2xl border border-green-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-green-900 dark:bg-zinc-900">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wide text-green-700 dark:text-green-400">Student records</p><h2 class="mt-1 text-xl font-bold">Total Students</h2></div><button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400" aria-label="Close">&times;</button></div>
                    <p class="mt-5 text-4xl font-bold text-green-700 dark:text-green-400">{{ $totalStudentsCount }}</p><p class="mt-2 text-sm text-neutral-500">Active registered student accounts.</p>
                    <a href="{{ route('admin.users.index') }}" class="mt-6 inline-flex rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Manage student records</a>
                </div>
            </dialog>

            <dialog id="todays-appointments-modal" class="w-[min(54rem,calc(100%-2rem))] max-h-[90vh] overscroll-contain overflow-y-auto rounded-2xl border border-blue-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-blue-900 dark:bg-zinc-900">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-400">Appointment calendar</p>
                            <div class="mt-2 flex items-center gap-2">
                                <select id="calendar-month-select" class="rounded-lg border border-blue-200 bg-white px-2 py-1.5 text-lg font-bold dark:border-blue-800 dark:bg-zinc-800"></select>
                                <select id="calendar-year-select" class="rounded-lg border border-blue-200 bg-white px-2 py-1.5 text-lg font-bold dark:border-blue-800 dark:bg-zinc-800"></select>
                            </div>
                        </div>
                        <button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400" aria-label="Close">&times;</button>
                    </div>
                    <div class="mt-5 flex items-center justify-between"><button id="calendar-prev-month" type="button" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-sm font-semibold hover:bg-neutral-50 dark:border-zinc-700 dark:hover:bg-zinc-800">← Previous</button><button id="calendar-next-month" type="button" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-sm font-semibold hover:bg-neutral-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Next →</button></div>
                    <div class="mt-3 grid grid-cols-7 gap-1 text-center text-xs font-semibold text-neutral-500">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)<span class="py-2">{{ $dayName }}</span>@endforeach
                    </div>
                    <div id="appointment-calendar-grid" class="grid grid-cols-7 gap-1"></div>
                    <div class="mt-5 border-t border-neutral-200 pt-4 dark:border-zinc-800"><h3 class="font-semibold">Today's appointments</h3>@forelse ($todayAppointments as $appointment)<p class="mt-2 text-sm text-neutral-600 dark:text-zinc-300"><span class="font-medium">{{ $appointment->scheduled_at->format('g:i A') }}</span> — {{ $appointment->requester->name }} <span class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-950/50 dark:text-blue-300">{{ ucfirst($appointment->status) }}</span></p>@empty<p class="mt-2 text-sm text-neutral-500">No appointments scheduled today.</p>@endforelse</div>
                    <div class="mt-5 border-t border-neutral-200 pt-4 dark:border-zinc-800"><h3 class="font-semibold">Done or missed today</h3>@forelse ($todayMissedDoneAppointments as $appointment)<p class="mt-2 text-sm text-neutral-600 dark:text-zinc-300"><span class="font-medium">{{ $appointment->scheduled_at->format('g:i A') }}</span> — {{ $appointment->requester->name }} <span class="ml-2 rounded-full {{ $appointment->status === 'missed' ? 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300' : 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' }} px-2 py-0.5 text-xs font-semibold">{{ $appointment->status === 'missed' ? 'Client failed to attend' : 'Done' }}</span></p>@empty<p class="mt-2 text-sm text-neutral-500">No done or missed appointments today.</p>@endforelse</div>
                </div>
            </dialog>

            <dialog id="appointment-day-modal" class="w-[min(34rem,calc(100%-2rem))] rounded-2xl border border-blue-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-blue-900 dark:bg-zinc-900">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-400">Scheduled appointments</p><h2 id="appointment-day-label" class="mt-1 text-xl font-bold"></h2></div><button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400" aria-label="Close appointments">&times;</button></div>
                    <div id="appointment-day-list" class="mt-5 divide-y divide-neutral-100 dark:divide-zinc-800"></div>
                </div>
            </dialog>

            <script>
                (() => {
                    const appointments = @json($calendarData);
                    const calendarModal = document.getElementById('todays-appointments-modal');
                    const dayModal = document.getElementById('appointment-day-modal');
                    const grid = document.getElementById('appointment-calendar-grid');
                    const monthSelect = document.getElementById('calendar-month-select');
                    const yearSelect = document.getElementById('calendar-year-select');
                    const monthNames = Array.from({ length: 12 }, (_, month) => new Date(2000, month, 1).toLocaleString(undefined, { month: 'long' }));
                    const today = new Date();
                    let selectedMonth = today.getMonth();
                    let selectedYear = today.getFullYear();

                    const pad = (value) => String(value).padStart(2, '0');
                    const dateKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
                    const appointmentsFor = (key) => appointments.filter((appointment) => appointment.date === key);

                    function openDay(key) {
                        const dayAppointments = appointmentsFor(key);
                        const [year, month, day] = key.split('-').map(Number);
                        document.getElementById('appointment-day-label').textContent = new Date(year, month - 1, day).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
                        document.getElementById('appointment-day-list').innerHTML = dayAppointments.map((appointment) => `
                            <div class="py-3"><div class="flex items-center justify-between gap-3"><p class="font-semibold">${appointment.time} — ${appointment.student}</p><span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-950/50 dark:text-blue-300">${appointment.status}</span></div><p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">${appointment.duration ? `${appointment.duration} minutes` : ''}</p><p class="mt-2 whitespace-pre-line text-sm text-neutral-700 dark:text-zinc-300">${appointment.purpose ?? ''}</p></div>`).join('');
                        dayModal.showModal();
                    }

                    function renderCalendar() {
                        const firstDay = new Date(selectedYear, selectedMonth, 1);
                        const start = new Date(selectedYear, selectedMonth, 1 - firstDay.getDay());
                        monthSelect.value = String(selectedMonth);
                        yearSelect.value = String(selectedYear);
                        grid.innerHTML = Array.from({ length: 42 }, (_, index) => { const day = new Date(start); day.setDate(start.getDate() + index); const key = dateKey(day); const dayAppointments = appointmentsFor(key); const outsideMonth = day.getMonth() !== selectedMonth; const isToday = key === dateKey(today); return `<button type="button" data-date="${key}" class="min-h-16 rounded-lg border p-1 text-left transition hover:border-blue-400 ${outsideMonth ? 'text-neutral-400 dark:text-zinc-600' : 'text-neutral-800 dark:text-zinc-200'} ${isToday ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40' : 'border-neutral-100 dark:border-zinc-800'}"><span class="text-xs font-semibold">${day.getDate()}</span>${dayAppointments.map((appointment) => `<span class="mt-1 block truncate rounded bg-green-100 px-1 py-0.5 text-[10px] text-green-800 dark:bg-green-950/50 dark:text-green-300">${appointment.time}</span>`).join('')}</button>`; }).join('');
                        grid.querySelectorAll('[data-date]').forEach((button) => { if (appointmentsFor(button.dataset.date).length) button.addEventListener('click', () => openDay(button.dataset.date)); });
                    }

                    monthSelect.innerHTML = monthNames.map((name, month) => `<option value="${month}">${name}</option>`).join('');
                    const calendarYears = new Set(Array.from({ length: 11 }, (_, index) => today.getFullYear() - 5 + index));
                    appointments.forEach((appointment) => calendarYears.add(Number(appointment.date.slice(0, 4))));
                    yearSelect.innerHTML = [...calendarYears].sort((a, b) => a - b).map((year) => `<option value="${year}">${year}</option>`).join('');
                    monthSelect.addEventListener('change', () => { selectedMonth = Number(monthSelect.value); renderCalendar(); });
                    yearSelect.addEventListener('change', () => { selectedYear = Number(yearSelect.value); renderCalendar(); });
                    document.getElementById('calendar-prev-month').addEventListener('click', () => { selectedMonth -= 1; if (selectedMonth < 0) { selectedMonth = 11; selectedYear -= 1; } renderCalendar(); });
                    document.getElementById('calendar-next-month').addEventListener('click', () => { selectedMonth += 1; if (selectedMonth > 11) { selectedMonth = 0; selectedYear += 1; } renderCalendar(); });
                    calendarModal.addEventListener('toggle', () => { if (calendarModal.open) renderCalendar(); });
                    renderCalendar();
                })();
            </script>

            <dialog id="waiting-for-appointment-approval-modal" class="w-[min(42rem,calc(100%-2rem))] rounded-2xl border border-yellow-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-yellow-900 dark:bg-zinc-900">
                <div class="p-6"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wide text-yellow-700 dark:text-yellow-400">Appointment requests</p><h2 class="mt-1 text-xl font-bold">Waiting for Appointment Approval</h2></div><button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400" aria-label="Close">&times;</button></div><div class="mt-5 divide-y divide-neutral-100 dark:divide-zinc-800">@forelse ($pendingAppointments as $appointment)<div class="py-3"><p class="font-semibold">{{ $appointment->requester->name }}</p><p class="text-sm text-neutral-500">{{ $appointment->purpose }}</p><p class="mt-1 text-xs text-neutral-400">Requested {{ $appointment->created_at->format('M j, Y g:i A') }}</p></div>@empty<p class="py-3 text-sm text-neutral-500">There are no waiting appointment requests.</p>@endforelse</div><a href="{{ route('appointments.index') }}" class="mt-5 inline-flex rounded-lg bg-yellow-600 px-4 py-2 text-sm font-semibold text-white hover:bg-yellow-700">Review appointment requests</a></div>
            </dialog>

            <dialog id="miss-done-appointments-modal" class="w-[min(42rem,calc(100%-2rem))] rounded-2xl border border-orange-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-orange-900 dark:bg-zinc-900">
                <div class="p-6"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wide text-orange-700 dark:text-orange-400">Appointment verification</p><h2 class="mt-1 text-xl font-bold">Miss/Done Appointments</h2></div><button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400" aria-label="Close">&times;</button></div><div class="mt-5 divide-y divide-neutral-100 dark:divide-zinc-800">@forelse ($missedDoneAppointments as $appointment)<div class="py-3"><div class="flex items-center justify-between gap-3"><p class="font-semibold">{{ $appointment->requester->name }}</p><span class="rounded-full {{ $appointment->status === 'missed' ? 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300' : 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' }} px-2.5 py-1 text-xs font-semibold">{{ $appointment->status === 'missed' ? 'Client failed to attend' : 'Done' }}</span></div><p class="mt-1 text-sm text-neutral-500">{{ $appointment->scheduled_at?->format('M j, Y g:i A') ?? 'Date not set' }}</p></div>@empty<p class="py-3 text-sm text-neutral-500">There are no done or missed appointments yet.</p>@endforelse</div><a href="{{ route('appointments.index') }}" class="mt-5 inline-flex rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">Open appointment records</a></div>
            </dialog>

            <dialog id="active-referrals-modal" class="w-[min(48rem,calc(100%-2rem))] rounded-2xl border border-purple-200 bg-white p-0 shadow-2xl backdrop:bg-black/50 dark:border-purple-900 dark:bg-zinc-900">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-purple-700 dark:text-purple-400">Slip management</p>
                            <h2 class="mt-1 text-xl font-bold">Referrals Missing Document Details</h2>
                            <p class="mt-1 text-sm text-neutral-500">The count tracks referrals missing Document Code, Revision Number, or Control No. The list may also include referrals awaiting a Class Admission Slip.</p>
                        </div>
                        <button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none text-neutral-400" aria-label="Close">&times;</button>
                    </div>
                    <div class="mt-5 divide-y divide-neutral-100 dark:divide-zinc-800">
                        @forelse ($referrals as $row)
                            @php
                                $referral = $row['assignment'];
                            @endphp
                            <div class="py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-semibold">{{ $referral->student->name }}</p>
                                        <p class="text-sm text-neutral-500">{{ $referral->completed_at?->format('M j, Y g:i A') ?? 'Received' }}</p>
                                    </div>
                                    <span class="rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-800 dark:bg-purple-950/50 dark:text-purple-300">{{ $referral->status }}</span>
                                </div>
                                <details class="mt-3 rounded-xl border border-neutral-200 p-3 dark:border-zinc-700">
                                    <summary class="cursor-pointer text-sm font-semibold text-purple-800 dark:text-purple-300">View referral process</summary>
                                    <div class="mt-4">
                                        @include('teacher.partials.referral-timeline', ['referral' => $referral])
                                    </div>
                                </details>
                            </div>
                        @empty
                            <p class="py-3 text-sm text-neutral-500">There are no referrals missing document details or needing follow-up.</p>
                        @endforelse
                    </div>
                    <a href="{{ route('guidance.referrals') }}" onclick="this.closest('dialog').close()" class="mt-5 inline-flex rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-800">View referrals</a>
                </div>
            </dialog>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section id="appointment-overview" class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900 lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-5 dark:border-neutral-700">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-green-700 dark:text-green-400">Appointment management</p>
                            <h2 class="mt-1 text-lg font-semibold">Upcoming Appointments</h2>
                            <p class="text-sm text-neutral-500">Next scheduled appointments and requests</p>
                        </div>
                        <a href="{{ route('appointments.index') }}" class="text-sm font-medium text-[#28643a] hover:underline dark:text-[#91dfa6]">View all</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-neutral-50 dark:bg-neutral-800">
                                <tr><th class="px-6 py-3 font-medium text-neutral-500">Student</th><th class="px-6 py-3 font-medium text-neutral-500">Date</th><th class="px-6 py-3 font-medium text-neutral-500">Time</th><th class="px-6 py-3 font-medium text-neutral-500">Status</th></tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                                @forelse ($upcomingAppointments as $appointment)
                                    <tr>
                                        <td class="px-6 py-4 font-medium">{{ $appointment->requester->name }}</td>
                                        <td class="px-6 py-4">{{ $appointment->scheduled_at->format('M j, Y') }}</td>
                                        <td class="px-6 py-4">{{ $appointment->scheduled_at->format('g:i A') }}</td>
                                        <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $appointment->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">{{ ucfirst($appointment->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-6 py-12 text-center text-neutral-500">No upcoming appointments.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="student-import" class="scroll-mt-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-green-700 dark:text-green-400">Registration reference</p>
                        <h2 class="mt-1 text-lg font-semibold">{{ $hasImportedStudentFile ? 'Update the imported file' : 'Import students file' }}</h2>
                        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                            Upload the complete Excel (.xlsx) roster with Code, name, Sex, Year, and Course. Missing students are deactivated while their files are preserved.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.students.import') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">
                            Student file
                            <input type="file" name="student_file" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain" required class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800">
                        </label>
                        <button type="submit" class="w-full rounded-lg bg-[#09ba44] px-4 py-2.5 text-sm font-semibold text-[#092010] hover:bg-[#079b38] dark:bg-[#54d180] dark:text-zinc-950 dark:hover:bg-[#6be495]">
                            {{ $hasImportedStudentFile ? 'Update students file' : 'Import students file' }}
                        </button>
                    </form>
                    @error('student_file')
                        <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </section>
            </div>

            <section class="rounded-2xl border border-amber-200 p-6 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-400">Student files</p>
                        <h2 class="mt-1 text-lg font-semibold">Files of Graduated/Deactivated Users</h2>
                        <p class="mt-1 text-sm text-amber-900/70 dark:text-amber-200/70">Archived student profiles and saved guidance records remain available here. Their accounts cannot log in.</p>
                    </div>
                    <a href="{{ route('guidance.records.archived') }}" class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-transparent dark:text-amber-200 dark:hover:bg-amber-900/30">
                        Open folder ({{ $archivedStudentsCount }})
                    </a>
                </div>
            </section>

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                    {{ session('status') }}
                </div>
            @endif

            @if (false)
            <section id="received-referrals" class="scroll-mt-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-400">Slip management</p>
                    <h2 class="mt-1 text-lg font-bold">Received Student Referral Slips</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                        Record the referral receipt and issue the Class Admission Slip when needed. Enter the document-control identifiers separately when available; the referral PDF becomes available once all document-control values are present. Effectivity Date is automatically recorded when the counselor issues the Class Admission Slip.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200">
                        <p class="font-semibold">Please review the referral document-control fields.</p>
                        <ul class="mt-2 list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($referrals->isEmpty())
                    <p class="mt-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-zinc-800 dark:text-zinc-300">
                        There are no received referrals needing follow-up forms.
                    </p>
                @else
                    <div class="mt-5">
                        <table class="w-full text-sm">
                            <thead class="hidden">
                                <tr>
                                    <th class="w-[22%] px-4 py-3 text-left">Student</th>
                                    <th class="w-[16%] px-4 py-3 text-center">Referral received</th>
                                    <th class="w-[38%] px-4 py-3 text-center">Document control &amp; referral PDF</th>
                                    <th class="w-[24%] px-4 py-3 text-center">Class Admission Slip</th>
                                </tr>
                            </thead>
                            <tbody class="block divide-y divide-gray-100 dark:divide-zinc-800">
                                @foreach ($referrals as $row)
                                    @php
                                        $referral = $row['assignment'];
                                    @endphp
                                    <tr class="grid grid-cols-1 gap-x-6 gap-y-0 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,2fr)]">
                                        <td class="order-1 block px-4 py-2 text-left align-middle">
                                            <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-zinc-400">Student</span>
                                            <p class="font-semibold">{{ $referral->student->name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $referral->student->id_number }}</p>
                                        </td>
                                        <td class="order-2 block px-4 py-2 text-left align-middle text-gray-600 dark:text-zinc-300 md:text-center">
                                            <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-zinc-400">Referral received</span>
                                            {{ $referral->completed_at?->format('M j, Y g:i A') ?? 'Received' }}
                                        </td>
                                        <td class="order-4 block border-t border-gray-100 px-4 py-3 text-center align-middle dark:border-zinc-800 md:col-start-3 md:row-start-1 md:row-span-3 md:border-l md:border-t-0 md:pl-6">
                                            @php
                                                $isReferralControlFormOld = old('referral_id') == $referral->id;
                                            @endphp
                                            <div class="space-y-3 text-left">
                                                <p class="text-center text-xs font-semibold {{ $row['referral_pdf_ready'] ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">
                                                    {{ $row['referral_pdf_ready'] ? 'Referral PDF ready' : 'Referral PDF locked until all four values are entered' }}
                                                </p>
                                                @if (! $row['referral_pdf_ready'] && $referral->slipSubmission)
                                                    <a href="{{ route('slips.submissions.preview-pdf', $referral->slipSubmission) }}#toolbar=0&amp;navpanes=0" target="_blank" rel="noopener" class="block text-center text-xs font-semibold text-blue-700 underline dark:text-blue-300">Preview referral form</a>
                                                @endif
                                                <form method="POST" action="{{ route('guidance.referrals.document-control', $referral) }}" class="space-y-2">
                                                    @csrf
                                                    <input type="hidden" name="referral_id" value="{{ $referral->id }}">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-zinc-300">
                                                            Document Code
                                                            <input name="details[document_code]" value="{{ $isReferralControlFormOld ? old('details.document_code') : ($row['document_control_data']['document_code'] ?? '') }}" maxlength="30" required class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-xs text-zinc-900 focus:border-green-600 focus:ring-green-600 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                                                        </label>
                                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-zinc-300">
                                                            Revision Number
                                                            <input name="details[revision_number]" value="{{ $isReferralControlFormOld ? old('details.revision_number') : ($row['document_control_data']['revision_number'] ?? '') }}" maxlength="20" required class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-xs text-zinc-900 focus:border-green-600 focus:ring-green-600 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                                                        </label>
                                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-zinc-300">
                                                            Effectivity Date
                                                            <input type="date" value="{{ $row['document_control_data']['effectivity_date'] ?? '' }}" readonly class="mt-1 w-full rounded-md border-gray-300 bg-gray-100 px-2 py-1.5 text-xs text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                                                            <span class="mt-1 block text-[10px] text-gray-500 dark:text-zinc-400">Recorded when the counselor issues the Class Admission Slip.</span>
                                                        </label>
                                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-zinc-300">
                                                            Control No.
                                                            <input name="details[control_number]" value="{{ $isReferralControlFormOld ? old('details.control_number') : ($row['document_control_data']['control_number'] ?? '') }}" maxlength="30" required class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-xs text-zinc-900 focus:border-green-600 focus:ring-green-600 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                                                        </label>
                                                    </div>
                                                    <button type="submit" class="w-full rounded-lg bg-[#09ba44] px-3 py-2 text-xs font-semibold text-[#092010] hover:bg-[#079b38] dark:bg-[#20783e] dark:text-white dark:hover:bg-[#28894a]">
                                                        {{ $row['referral_pdf_ready'] ? 'Update Details & Refresh PDF' : 'Save Details & Release PDF' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="order-3 block px-4 py-2 text-left align-middle md:col-span-2 md:text-center">
                                            @if ($row['can_issue_class_admission'])
                                                <form method="POST" action="{{ route('guidance.slip-review.issue', [$referral, 'class-admission-slip']) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-lg bg-[#09ba44] px-3 py-2 text-xs font-semibold text-[#092010] hover:bg-[#079b38] dark:bg-[#20783e] dark:text-white dark:hover:bg-[#28894a]">
                                                        Issue Class Admission Slip
                                                    </button>
                                                </form>
                                                <p class="mt-2 text-xs text-gray-500 dark:text-zinc-400">Can be issued while waiting for document-control numbers.</p>
                                            @else
                                                <span class="text-xs text-gray-500 dark:text-zinc-400">Already issued for this referral</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
            @endif
        </div>
    </main>
</x-layouts::app>
