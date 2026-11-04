<?php

namespace App\Services;

use App\Models\AccountSessionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AccountSessionLogger
{
    public const IDLE_TIMEOUT_MINUTES = 10;

    private const LOG_ID_SESSION_KEY = 'account_session_log_id';

    private const LAST_ACTIVITY_SESSION_KEY = 'account_last_activity_at';

    private const LAST_DATABASE_TOUCH_SESSION_KEY = 'account_last_database_touch_at';

    private const PENDING_LOCATION_SESSION_KEY = 'account_login_location';

    public function start(Request $request, User $user): AccountSessionLog
    {
        $sessionHash = hash('sha256', $request->session()->getId());
        $location = $request->session()->pull(self::PENDING_LOCATION_SESSION_KEY, []);
        $now = CarbonImmutable::now();

        $log = AccountSessionLog::query()->firstOrCreate(
            ['session_hash' => $sessionHash],
            [
                'user_id' => $user->id,
                'device_name' => $this->deviceName($request->userAgent()),
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'logged_in_at' => $now,
                'last_activity_at' => $now,
            ],
        );

        $request->session()->put(self::LOG_ID_SESSION_KEY, $log->id);
        $request->session()->put(self::LAST_ACTIVITY_SESSION_KEY, $now->timestamp);
        $request->session()->put(self::LAST_DATABASE_TOUCH_SESSION_KEY, $now->timestamp);

        return $log;
    }

    public function touch(Request $request): void
    {
        $logId = $request->session()->get(self::LOG_ID_SESSION_KEY);
        if (! is_numeric($logId)) {
            return;
        }

        $now = CarbonImmutable::now();
        $lastDatabaseTouch = $request->session()->get(self::LAST_DATABASE_TOUCH_SESSION_KEY, 0);
        if ($now->timestamp - (int) $lastDatabaseTouch < 60) {
            return;
        }

        AccountSessionLog::query()
            ->whereKey((int) $logId)
            ->whereNull('logged_out_at')
            ->update(['last_activity_at' => $now]);

        $request->session()->put(self::LAST_DATABASE_TOUCH_SESSION_KEY, $now->timestamp);
    }

    public function close(Request $request, ?User $user = null, ?CarbonImmutable $loggedOutAt = null): void
    {
        $logId = $request->session()->get(self::LOG_ID_SESSION_KEY);
        if (! is_numeric($logId)) {
            return;
        }

        $query = AccountSessionLog::query()
            ->whereKey((int) $logId)
            ->whereNull('logged_out_at');

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        $query->update(['logged_out_at' => $loggedOutAt ?? CarbonImmutable::now()]);
    }

    public function captureLoginLocation(Request $request): void
    {
        if (! $request->boolean('share_location')) {
            $request->session()->forget(self::PENDING_LOCATION_SESSION_KEY);

            return;
        }

        $latitude = $request->input('login_latitude');
        $longitude = $request->input('login_longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            $request->session()->forget(self::PENDING_LOCATION_SESSION_KEY);

            return;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            $request->session()->forget(self::PENDING_LOCATION_SESSION_KEY);

            return;
        }

        $request->session()->put(self::PENDING_LOCATION_SESSION_KEY, [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    private function deviceName(?string $userAgent): string
    {
        $userAgent = $userAgent ?? '';
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $platform = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Device',
        };

        return $browser.' on '.$platform;
    }
}
