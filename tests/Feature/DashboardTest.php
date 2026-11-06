<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk()
            ->assertSee('data-system-theme', false)
            ->assertSee('bg-[#f6f5f1]', false)
            ->assertSee('dark:bg-[#111712]', false)
            ->assertSee('bg-[#09ba44]', false)
            ->assertSee('dark:bg-[#20783e]', false);
    }

    public function test_guidance_dashboard_monthly_report_uses_same_origin_and_loads(): void
    {
        $counselor = User::factory()->create(['role' => 'guidance_counselor']);

        $this->actingAs($counselor)
            ->get(route('guidance.dashboard'))
            ->assertOk()
            ->assertSee('src="/guidance/monthly-report?embedded=1"', false);

        $this->get(route('guidance.reports.monthly'))
            ->assertOk()
            ->assertSee('Monthly Student Report')
            ->assertSee('name="month"', false)
            ->assertDontSee('Workspace')
            ->assertDontSee('Guidance Dashboard');
    }
}
