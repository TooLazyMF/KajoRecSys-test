<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\EmailOtp;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('Student / Teacher ID')
            ->assertSee('Gender')
            ->assertSee('Use an email address you can access. We will send a verification code; verify it to use your account.');
    }

    public function test_welcome_page_displays_embedded_login_and_registration_panels(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-mode="login"', false)
            ->assertSee(route('login.store'), false)
            ->assertSee(route('register.store'), false)
            ->assertSee('data-auth-view="login"', false)
            ->assertSee('name="_welcome_panel" value="register"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('Use an email address you can access. We will send a verification code; verify it to use your account.')
            ->assertSee('aria-controls="login-password"', false)
            ->assertSee('aria-controls="register-password"', false)
            ->assertSee('aria-controls="register-password-confirmation"', false)
            ->assertSee('--primary: #09ba44;', false)
            ->assertSee('@media (prefers-color-scheme: dark)', false)
            ->assertSee('--primary: #20783e;', false)
            ->assertSee('name="theme-color" content="#09ba44"', false)
            ->assertSee('name="theme-color" content="#20783e"', false)
            ->assertSee('class="sticky top-0 z-50 border-b border-gray-200 bg-white"', false)
            ->assertSee('body.welcome-shell > header', false)
            ->assertSee('width: min(1040px, 100%);', false);
    }

    public function test_registration_errors_return_to_the_registration_panel_on_the_welcome_page(): void
    {
        $response = $this->from(route('home'))->post(route('register.store'), [
            '_welcome_panel' => 'register',
            'first_name' => '',
            'last_name' => '',
            'id_number' => '',
            'gender' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect(route('home'))
            ->assertSessionHasErrors(['first_name', 'last_name', 'id_number', 'gender', 'email', 'password']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-mode="register"', false);
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'id_number' => 'STUDENT-1001',
            'gender' => 'male',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('registration.verification.notice', absolute: false));

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'id_number' => 'STUDENT-1001',
            'gender' => 'male',
            'email_verified_at' => null,
        ]);
        $user = User::where('email', 'test@example.com')->firstOrFail();
        Notification::assertSentTo($user, EmailOtp::class);
    }

    public function test_new_users_are_authenticated_only_after_email_verification(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'id_number' => 'STUDENT-1002',
            'gender' => 'female',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('registration.verification.notice', absolute: false));

        $this->assertGuest();
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $notification = Notification::sent($user, EmailOtp::class)->first();
        $this->assertNotNull($notification);
        $this->assertSame(1, preg_match('/\b\d{6}\b/', $notification->toMail($user)->introLines[1], $matches));

        $this->get(route('registration.verification.notice'))->assertOk();
        $this->from(route('registration.verification.notice'))
            ->post(route('verification.otp.verify'), ['otp' => $matches[0]])
            ->assertRedirect(route('student.dashboard', absolute: false).'?verified=1');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_registration_after_logout_does_not_restore_the_previous_account(): void
    {
        Notification::fake();

        $previousUser = User::factory()->create();
        $this->actingAs($previousUser)->post(route('logout'));
        $this->assertGuest();

        $this->post(route('register.store'), [
            'first_name' => 'New',
            'last_name' => 'Student',
            'id_number' => 'STUDENT-1003',
            'gender' => 'male',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('registration.verification.notice', absolute: false));

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'email_verified_at' => null,
        ]);
    }
}
