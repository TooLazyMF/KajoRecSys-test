<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-system-theme>
    <head>
        @include('partials.head')
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>
            const colorScheme = window.matchMedia('(prefers-color-scheme: dark)');
            const applySystemTheme = () => document.documentElement.classList.toggle('dark', colorScheme.matches);

            applySystemTheme();
            colorScheme.addEventListener('change', applySystemTheme);
        </script>
    </head>
    <body class="min-h-screen bg-[#f6f5f1] text-slate-900 dark:bg-[#111712] dark:text-zinc-100">
    <main class="min-h-screen p-5 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-[#28643a] dark:text-[#91dfa6]">Guidance Office</p>
                    <h1 class="mt-1 text-3xl font-bold">Monthly Student Report</h1>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Students with scheduled appointment activity for {{ $monthLabel }}.</p>
                </div>
                <form method="GET" action="{{ route('guidance.reports.monthly') }}" class="flex items-end gap-3 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <div>
                        <label for="month" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Report month</label>
                        <input id="month" name="month" type="month" value="{{ $month }}" class="mt-1 rounded-lg border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
                    </div>
                    <button type="submit" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Generate</button>
                </form>
            </div>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Monthly report summary">
                @foreach ([
                    ['Students availed', $studentsAvailed, 'Unique students with accepted, done, or missed appointments', 'text-green-700'],
                    ['Total appointments', $totalAppointments, 'All scheduled student appointments', 'text-blue-700'],
                    ['Done appointments', $doneAppointments, 'Sessions completed by the counselor', 'text-emerald-700'],
                    ['Missed appointments', $missedAppointments, 'Clients recorded as failed to attend', 'text-red-700'],
                ] as [$label, $count, $description, $color])
                    <article class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $label }}</p>
                        <p class="mt-2 text-4xl font-bold {{ $color }} dark:text-green-300">{{ $count }}</p>
                        <p class="mt-3 text-xs text-neutral-500">{{ $description }}</p>
                    </article>
                @endforeach
            </section>

            <section class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                    <h2 class="font-semibold">Students who availed services</h2>
                    <p class="mt-1 text-sm text-neutral-500">Each student is listed once for the selected month.</p>
                </div>
                @forelse ($studentAppointments as $row)
                    <article class="flex flex-col gap-3 border-b border-neutral-100 px-6 py-4 last:border-b-0 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold">{{ $row['student']->name }}</p>
                            <p class="mt-1 text-xs text-neutral-500">{{ $row['student']->id_number ?: $row['student']->email }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($row['appointments'] as $appointment)
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $appointment->status === 'missed' ? 'bg-red-100 text-red-800' : ($appointment->status === 'done' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ $appointment->scheduled_at?->format('M j') }} · {{ $appointment->status === 'missed' ? 'Missed' : ucfirst($appointment->status) }}
                                </span>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <p class="px-6 py-12 text-center text-sm text-neutral-500">No student appointment activity was recorded for {{ $monthLabel }}.</p>
                @endforelse
            </section>
        </div>
    </main>
    @fluxScripts
    </body>
</html>
