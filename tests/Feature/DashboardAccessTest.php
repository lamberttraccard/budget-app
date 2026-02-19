<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Story 1.1: Verify dashboard access control (AC3, AC4)
 *
 * These tests specifically cover the /dashboard route guard and redirect
 * behaviour, which complements Tests\Feature\Auth\AuthenticationTest
 * (which covers the login form mechanics and Fortify internals).
 */
class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unauthenticated_users_are_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function valid_credentials_authenticate_the_user(): void
    {
        User::factory()->create([
            'email' => 'test@budget.local',
            'password' => bcrypt('password'),
            'email_verified_at' => now(), // required: /dashboard has 'verified' middleware
        ]);

        $response = $this->post('/login', [
            'email' => 'test@budget.local',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard');
    }

    #[Test]
    public function invalid_credentials_fail_authentication(): void
    {
        User::factory()->create([
            'email' => 'test@budget.local',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@budget.local',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function login_page_is_accessible_to_unauthenticated_users(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }
}
