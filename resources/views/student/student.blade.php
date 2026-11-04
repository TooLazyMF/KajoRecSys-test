<x-layouts::app :title="__('Student Dashboard')">
    <main class="min-h-screen bg-[#f6f5f1] p-5 text-slate-900 transition-colors duration-200 dark:bg-[#111712] dark:text-zinc-100 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="rounded-3xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-green-50 to-lime-50 p-5 shadow-[0_14px_28px_rgba(15,163,68,0.08)] dark:border-emerald-800 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-950">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="mb-2 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                            Student portal
                        </p>
                        <h1 class="text-2xl font-extrabold text-[#28643a] dark:text-[#91dfa6]">
                            Student Dashboard
                        </h1>
                    </div>
                    <div class="hidden rounded-2xl border border-emerald-200 bg-white/70 px-3 py-2 text-right text-sm text-emerald-700 shadow-sm sm:block dark:border-emerald-700 dark:bg-zinc-900/60 dark:text-emerald-300">
                        Welcome, {{ auth()->user()->name }}
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-600 dark:text-zinc-300">
                    Welcome, {{ auth()->user()->name }}.
                </p>
            </header>

            <section>
                <h2 class="mb-4 text-lg font-bold text-slate-800 dark:text-zinc-100">Activities</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <article class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-[0_8px_24px_rgba(15,163,68,0.07)] transition-shadow hover:shadow-[0_12px_30px_rgba(15,163,68,0.12)] dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">
                        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Pending Assigned Slips</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $pendingAssignmentsCount }}</p>
                        <a href="{{ route('assigned-slips.index') }}" class="mt-4 inline-block text-xs font-semibold text-[#28643a] hover:underline dark:text-[#91dfa6]">
                            View assigned slips →
                        </a>
                    </article>

                    <article class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-[0_8px_24px_rgba(234,179,8,0.08)] transition-shadow hover:shadow-[0_12px_30px_rgba(234,179,8,0.14)] dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">
                        <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Awaiting Approval</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $awaitingApprovalCount }}</p>
                        <a href="{{ route('slip-history.index') }}" class="mt-4 inline-block text-xs font-semibold text-[#28643a] hover:underline dark:text-[#91dfa6]">
                            View submitted slips →
                        </a>
                    </article>

                    <article class="rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 to-white p-5 shadow-[0_8px_24px_rgba(14,165,233,0.08)] transition-shadow hover:shadow-[0_12px_30px_rgba(14,165,233,0.12)] dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">
                        <p class="text-sm font-medium text-sky-700 dark:text-sky-300">Submitted Slips</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $submittedSlipsCount }}</p>
                        <a href="{{ route('slip-history.index') }}" class="mt-4 inline-block text-xs font-semibold text-[#28643a] hover:underline dark:text-[#91dfa6]">
                            View slip history →
                        </a>
                    </article>
                </div>
            </section>



           
            <section id="request-forms" class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-6 shadow-[0_8px_24px_rgba(15,163,68,0.07)] dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">

                <h2 class="text-lg font-bold text-emerald-800 dark:text-emerald-300">
                    Request a Form from Guidance
                </h2>

                <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">
                    Request these forms directly from the Guidance Office, then complete and submit them for counselor review.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ($requestableForms as $slug => $form)

                        <article
                            class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4
                            transition-colors duration-200
                            hover:border-[#09ba44] hover:bg-emerald-100/80
                            dark:border-zinc-700 dark:bg-zinc-800/60
                            dark:hover:border-[#54d180] dark:hover:bg-zinc-800/90">

                            <h3 class="font-semibold text-slate-800 dark:text-zinc-100">
                                {{ $form['title'] }}
                            </h3>

                            @if (in_array($slug, $activeRequestSlugs, true))

                                <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                                    You already have an active request for this form.
                                </p>

                            @else

                                <form
                                    method="POST"
                                    action="{{ route('student.slip-requests.store', $slug) }}"
                                    class="mt-3">

                                    @csrf

                                    <button
                                        type="submit"
                                        class="rounded-lg bg-[#09ba44] px-4 py-2 text-sm font-semibold text-[#092010]
                                        transition-colors duration-200
                                        hover:bg-[#079b38]
                                        dark:bg-[#20783e] dark:text-white dark:hover:bg-[#28894a]">

                                        Request from Guidance

                                    </button>

                                </form>

                            @endif

                        </article>

                    @endforeach
                </div>

            </section>


            <section class="rounded-2xl border border-emerald-100 bg-white p-6 text-center shadow-[0_8px_24px_rgba(15,163,68,0.07)] dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
                <h2 class="text-lg font-bold">Appointment Guidance Counseling</h2>
                <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-zinc-400">
                    Choose the kind of appointment you need. The counselor will review your request and set the date and time.
                </p>
                <a href="{{ route('appointments.index') }}" class="mt-5 inline-flex rounded-lg bg-[#09ba44] px-4 py-2 text-sm font-semibold text-[#092010] hover:bg-[#079b38] dark:bg-[#20783e] dark:text-white dark:hover:bg-[#28894a]">
                    Request or review appointment
                </a>

                @if ($appointments->isNotEmpty())
                    <div class="mx-auto mt-6 max-w-4xl border-t border-emerald-100 pt-5 dark:border-zinc-800">
                        <h3 class="text-sm font-semibold">Recent appointment requests</h3>
                        <ul class="mt-4 space-y-3">
                            @foreach ($appointments as $appointment)
                                <li class="rounded-xl border border-emerald-100 p-4 text-center dark:border-zinc-800">
                                    <div class="flex flex-col items-center justify-center gap-3">
                                        <div>
                                            <p class="font-medium">{{ $appointment->purpose }}</p>
                                            @if ($appointment->scheduled_at)
                                                <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">Scheduled for {{ $appointment->scheduled_at->format('M j, Y g:i A') }}</p>
                                            @else
                                                <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Waiting for the counselor to set a date and time.</p>
                                            @endif
                                        </div>
                                        <span @class([
                                            'rounded-full px-2.5 py-1 text-xs font-semibold capitalize',
                                            'bg-amber-100 text-amber-800' => in_array($appointment->status, ['pending', 'reschedule_requested'], true),
                                            'bg-green-100 text-green-800' => $appointment->status === 'accepted',
                                            'bg-red-100 text-red-800' => $appointment->status === 'declined',
                                        ])>{{ $appointment->status === 'reschedule_requested' ? 'Re-appointment requested' : $appointment->status }}</span>
                                    </div>
                                            @if ($appointment->status === 'declined')
                                                <p class="mx-auto mt-3 max-w-2xl rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                                                    <span class="font-semibold">Reason:</span> {{ $appointment->decline_reason }}
                                                </p>
                                            @endif
                                            @if (in_array($appointment->status, ['accepted', 'declined'], true))
                                                <form method="POST" action="{{ route('appointments.reappoint', $appointment) }}" class="mx-auto mt-3 w-full max-w-2xl rounded-lg border border-blue-200 bg-blue-50 p-4 text-center dark:border-blue-900 dark:bg-blue-950/30">
                                                    @csrf
                                                    <label for="reschedule_reason_{{ $appointment->id }}" class="block text-center text-xs font-semibold text-blue-900 dark:text-blue-200">Need a different appointment time?</label>
                                                    <textarea id="reschedule_reason_{{ $appointment->id }}" name="reschedule_reason" rows="2" maxlength="2000" required class="mt-2 w-full rounded-lg border-blue-200 bg-white p-3 text-center text-sm leading-6 placeholder:text-center dark:border-blue-800 dark:bg-zinc-900" placeholder="Tell the counselor why you need to re-appoint.">{{ old('reschedule_reason') }}</textarea>
                                                    @error('reschedule_reason')
                                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                    <button type="submit" class="mx-auto mt-3 block rounded-lg bg-blue-700 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-800">Re-appoint</button>
                                                </form>
                                            @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts::app>
