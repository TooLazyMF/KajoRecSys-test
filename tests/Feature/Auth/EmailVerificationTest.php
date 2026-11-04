<?php

namespace Tests\Feature\Auth;

use App\Models\AccountSessionLog;
use App\Models\User;
use App\Notifications\EmailOtp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk()
            ->assertSee('name="otp"', false)
            ->assertSee('Resend verification code');
    }

    public function test_unverified_users_cannot_access_the_application_until_their_email_is_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('teacher.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_can_be_verified_with_the_email_otp(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.otp.send'))
            ->assertSessionHas('status', 'A verification code has been sent to your registered email address.');

        $notification = Notification::sent($user, EmailOtp::class)->first();
        $this->assertNotNull($notification);
        $this->assertSame(1, preg_match('/\b\d{6}\b/', $notification->toMail($user)->introLines[1], $matches));

        Event::fake();

        $response = $this->actingAs($user)->post(route('verification.otp.verify'), [
            'otp' => $matches[0],
        ]);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_invalid_email_otp_does_not_verify_the_account(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.otp.send'));

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.otp.verify'), ['otp' => '000000'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('otp');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_idle_unverified_user_is_logged_out_after_ten_minutes(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.otp.send'));
        $notification = Notification::sent($user, EmailOtp::class)->first();
        preg_match('/\b\d{6}\b/', $notification->toMail($user)->introLines[1], $matches);
        $this->travel(11)->minutes();

        $this->actingAs($user)
            ->post(route('verification.otp.verify'), ['otp' => $matches[0]])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertNotNull(AccountSessionLog::query()
            ->where('user_id', $user->id)
            ->sole()
            ->logged_out_at);
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')],
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertNotDispatched(Verified::class);
    }
}
