<x-layouts::app :title="__('Account Logs')">
    <section class="w-full">
        @include('partials.settings-heading')

        <x-pages::settings.layout
            :heading="__('Account Logs')"
            :subheading="__('Review the devices and times associated with your account sessions.')"
        >
            <div class="space-y-5">
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Logs are retained by month. Location is shown only when you chose to share it and granted your browser permission.
                    Locations are stored as coordinates in this system; IP addresses and raw browser details are not stored.
                </p>

                <form method="GET" action="{{ route('account-logs.index') }}" class="flex flex-wrap items-end gap-3">
                    <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        {{ __('Month') }}
                        <input
                            type="month"
                            name="month"
                            value="{{ $month }}"
                            required
                            class="rounded-lg border-zinc-300 bg-white text-sm text-zinc-900 shadow-sm focus:border-emerald-700 focus:ring-emerald-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                    </label>
                    <flux:button type="submit" variant="primary">{{ __('View logs') }}</flux:button>
                </form>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full min-w-[760px] divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <tr>
                                <th scope="col" class="px-4 py-3">{{ __('Device Name') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Location') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Logged In') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Logged Out') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $log->device_name }}</td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                        @if ($log->latitude !== null && $log->longitude !== null)
                                            {{ number_format((float) $log->latitude, 5) }}, {{ number_format((float) $log->longitude, 5) }}
                                        @else
                                            {{ __('Not shared') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $log->logged_in_at->format('M d, Y h:i A') }}</td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                        {{ $log->logged_out_at?->format('M d, Y h:i A') ?? __('Session active') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                        {{ __('No account logs were recorded for this month.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>{{ $logs->links() }}</div>
            </div>
        </x-pages::settings.layout>
    </section>
</x-layouts::app>
