<?php

namespace Tests\Feature\Auth;

use App\Models\AccountSessionLog;
use App\Models\User;
use App\Notifications\EmailOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));
    }

    public function test_user_can_choose_email_otp_for_two_factor_login(): void
    {
        Notification::fake();
        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertSee('Send a code to my email');

        $this->from(route('two-factor.login'))
            ->post(route('two-factor.email.send'))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('email_otp_sent', true);

        $notification = Notification::sent($user, EmailOtp::class)->first();
        $this->assertNotNull($notification);
        $this->assertSame(1, preg_match('/\b\d{6}\b/', $notification->toMail($user)->introLines[1], $matches));

        $this->from(route('two-factor.login'))
            ->post(route('two-factor.email.verify'), ['otp' => $matches[0]])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('account_session_logs', [
            'user_id' => $user->id,
            'latitude' => null,
            'longitude' => null,
            'logged_out_at' => null,
        ]);
        $this->assertSame(1, AccountSessionLog::query()->where('user_id', $user->id)->count());
    }
}
