<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_open_login_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Auth/Login'));
    }

    public function test_guest_is_redirected_to_login_from_protected_page(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_login_and_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['password' => Hash::make('correct-password')]);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_signer_can_login_and_is_redirected_to_signer_dashboard(): void
    {
        $signer = User::factory()->signer()->create(['password' => Hash::make('correct-password')]);

        $this->post(route('login.store'), [
            'email' => $signer->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('signer.dashboard'));

        $this->assertAuthenticatedAs($signer);
    }

    public function test_login_regenerates_the_session_identifier(): void
    {
        $admin = User::factory()->admin()->create(['password' => Hash::make('correct-password')]);

        $this->get(route('login'));
        $previousSessionId = session()->getId();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'correct-password',
        ]);

        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected_with_a_safe_error(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password tidak valid.']);

        $this->assertGuest();
    }

    public function test_unknown_email_is_rejected_with_the_same_safe_error(): void
    {
        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'unknown@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password tidak valid.']);

        $this->assertGuest();
    }

    public function test_authenticated_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Dashboard')
                ->where('area', Role::Admin->value)
                ->where('auth.user.role', Role::Admin->value));
    }

    public function test_authenticated_signer_can_access_signer_dashboard(): void
    {
        $signer = User::factory()->signer()->create();

        $this->actingAs($signer)
            ->get(route('signer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Dashboard')
                ->where('area', Role::Signer->value)
                ->where('auth.user.role', Role::Signer->value));
    }

    public function test_admin_is_forbidden_from_signer_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('signer.dashboard'))
            ->assertForbidden();
    }

    public function test_signer_is_forbidden_from_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->signer()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_logout_ends_session_and_protected_page_requires_login_again(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_public_registration_route_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }
}
