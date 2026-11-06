<?php

namespace Tests\Feature;

use App\Models\AccountSessionLog;
use App\Models\User;
use App\Services\AccountSessionLogger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSessionLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_and_logout_are_recorded_with_optional_browser_location(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36',
        ])->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
            'share_location' => '1',
            'login_latitude' => '14.5995',
            'login_longitude' => '120.9842',
        ])->assertSessionHasNoErrors();

        $log = AccountSessionLog::query()->where('user_id', $student->id)->sole();
        $this->assertSame('Chrome on Windows', $log->device_name);
        $this->assertSame('14.5995000', $log->latitude);
        $this->assertSame('120.9842000', $log->longitude);
        $this->assertNull($log->logged_out_at);

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertNotNull($log->fresh()->logged_out_at);
        $this->assertGuest();
    }

    public function test_login_location_is_not_saved_without_explicit_consent(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
            'login_latitude' => '14.5995',
            'login_longitude' => '120.9842',
        ])->assertSessionHasNoErrors();

        $log = AccountSessionLog::query()->where('user_id', $student->id)->sole();
        $this->assertNull($log->latitude);
        $this->assertNull($log->longitude);
    }

    public function test_all_account_roles_can_view_monthly_paginated_logs(): void
    {
        foreach (['student', 'teacher', 'guidance_counselor'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            for ($index = 0; $index < 51; $index++) {
                $user->accountSessionLogs()->create([
                    'session_hash' => hash('sha256', $role.'-'.$index),
                    'device_name' => 'Test Browser',
                    'logged_in_at' => now()->subMinutes($index),
                    'last_activity_at' => now()->subMinutes($index),
                    'logged_out_at' => now()->subMinutes($index - 1),
                ]);
            }

            $this->actingAs($user)
                ->get(route('account-logs.index', ['month' => now()->format('Y-m')]))
                ->assertOk()
                ->assertSee('Account Logs')
                ->assertSee('Device Name')
                ->assertSee('Location')
                ->assertSee('Logged In')
                ->assertSee('Logged Out')
                ->assertViewHas('logs', fn ($logs) => $logs->count() === 50 && $logs->total() >= 51);

            $this->get(route('account-logs.index', ['month' => now()->subMonth()->format('Y-m')]))
                ->assertOk()
                ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
        }
    }

    public function test_inactive_session_is_logged_out_after_ten_minutes(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('profile.edit'))
            ->assertOk();
        $log = AccountSessionLog::query()->where('user_id', $student->id)->sole();
        $lastActivity = $log->fresh()->last_activity_at;

        $this->travel(10)->minutes();
        $this->get(route('student.dashboard'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'You were logged out after 10 minutes of inactivity.');

        $this->assertGuest();
        $this->assertSame(
            $lastActivity->addMinutes(AccountSessionLogger::IDLE_TIMEOUT_MINUTES)->timestamp,
            $log->fresh()->logged_out_at->timestamp
        );
    }

    public function test_user_activity_extends_the_ten_minute_session_timeout(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('profile.edit'))->assertOk();

        $this->travel(9)->minutes();
        $this->post(route('account-activity.touch'))->assertNoContent();

        $this->travel(9)->minutes();
        $response = $this->get(route('profile.edit'));
        $this->assertSame(200, $response->status(), $response->headers->get('Location') ?? '');
        $this->assertAuthenticatedAs($student);
    }

    public function test_scheduled_idle_session_cleanup_records_the_ten_minute_logout_time(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $lastActivity = CarbonImmutable::now()->subMinutes(11);
        $log = $student->accountSessionLogs()->create([
            'session_hash' => hash('sha256', 'scheduled-idle-session'),
            'device_name' => 'Test Browser',
            'logged_in_at' => $lastActivity->subHour(),
            'last_activity_at' => $lastActivity,
        ]);

        $this->artisan('account-sessions:close-idle')->assertExitCode(0);

        $this->assertSame(
            $lastActivity->addMinutes(AccountSessionLogger::IDLE_TIMEOUT_MINUTES)->timestamp,
            $log->fresh()->logged_out_at->timestamp
        );
    }
}
