<x-layouts::app :title="__('Slip History')">
    <main class="min-h-screen bg-[#f6f5f1] px-4 py-8 text-zinc-900 dark:bg-[#111712] dark:text-zinc-100 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <flux:heading size="xl">Slip History</flux:heading>
                <flux:text class="mt-2 text-zinc-600 dark:text-zinc-300">
                    Review the forms you have completed and submitted.
                </flux:text>
            </div>

            @if ($history->isEmpty())
                <section class="rounded-xl bg-white p-6 text-sm text-zinc-600 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">
                    You have not completed any assigned slips yet.
                </section>
            @else
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-100 text-xs uppercase text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                <tr>
                                    <th class="px-5 py-3">Form</th>
                                    <th class="px-5 py-3">Submitted</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($history as $entry)
                                    <tr>
                                        <td class="px-5 py-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $entry['title'] }}</td>
                                        <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">{{ $entry['submitted_at']->format('M j, Y g:i A') }}</td>
                                        <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">{{ ucfirst($entry['status']) }}</td>
                                        <td class="space-x-4 px-5 py-4 text-right">
                                            @if ($entry['show_url'])
                                                <a href="{{ $entry['show_url'] }}" class="font-semibold text-[#28643a] hover:underline dark:text-[#91dfa6]">View</a>
                                            @endif
                                            @if ($entry['pdf_url'])
                                                <a href="{{ $entry['pdf_url'] }}" class="font-semibold text-[#28643a] hover:underline dark:text-[#91dfa6]">PDF</a>
                                            @endif
                                            @if (! $entry['show_url'] && ! $entry['pdf_url'])
                                                <span class="text-xs text-zinc-500">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </main>
</x-layouts::app>
