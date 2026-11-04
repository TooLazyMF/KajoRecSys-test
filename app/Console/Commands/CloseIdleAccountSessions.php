<?php

namespace App\Console\Commands;

use App\Models\AccountSessionLog;
use App\Services\AccountSessionLogger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CloseIdleAccountSessions extends Command
{
    protected $signature = 'account-sessions:close-idle';

    protected $description = 'Record logout times for account sessions idle for ten minutes';

    public function handle(): int
    {
        $threshold = CarbonImmutable::now()->subMinutes(AccountSessionLogger::IDLE_TIMEOUT_MINUTES);

        AccountSessionLog::query()
            ->whereNull('logged_out_at')
            ->where('last_activity_at', '<=', $threshold)
            ->orderBy('id')
            ->chunkById(100, function ($logs): void {
                foreach ($logs as $log) {
                    $log->update([
                        'logged_out_at' => $log->last_activity_at
                            ->addMinutes(AccountSessionLogger::IDLE_TIMEOUT_MINUTES),
                    ]);
                }
            });

        return self::SUCCESS;
    }
}
