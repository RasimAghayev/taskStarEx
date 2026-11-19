<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 9: token-based (Sanctum) authentication endpoints. The /login
 * route is also the most natural place to exercise the "login" named
 * rate limiter (AppServiceProvider::configureRateLimiting()).
 */
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // The "login" limiter is keyed by IP (not by user), and the test
        // process's CACHE_STORE=array persists across test methods within
        // one run -- without this, an earlier test's failed-login attempts
        // would count toward a later test's limiter budget.
        Cache::flush();

        parent::tearDown();
    }

    public function test_login_with_valid_credentials_returns_a_token(): void
    {
        $user = User::factory()->create(['email' => 'valid@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'valid@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'valid@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_login_with_wrong_password_returns_401_and_no_token(): void
    {
        User::factory()->create(['email' => 'wrongpass@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'not-the-password',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_after_five_attempts_per_minute(): void
    {
        User::factory()->create(['email' => 'ratelimited@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'ratelimited@example.com',
                'password' => 'wrong',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => 'ratelimited@example.com',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_authenticated_user_can_fetch_own_user_endpoint(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create(['email' => 'logout@example.com']);

        $login = $this->postJson('/api/login', [
            'email' => 'logout@example.com',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Illuminate\Auth\AuthManager caches a resolved guard per name for
        // its own lifetime, and Illuminate\Auth\RequestGuard caches the
        // user it resolves on first use ("we do not want to fetch the user
        // data on every call"). Both caches live on objects that, in real
        // production, exist for exactly one HTTP request -- PHP-FPM boots a
        // fresh container (and therefore a fresh AuthManager/RequestGuard)
        // per request, so the deleted token is correctly rejected on the
        // very next real request. Inside one PHPUnit test method, though,
        // postJson()/getJson() share this test's single $this->app
        // container, so without forgetting the cached guard here, this
        // second call would incorrectly reuse the *first* call's already-
        // resolved (pre-logout) user instead of re-resolving against the
        // now-deleted token. This line makes the simulation match reality:
        // two independent requests, each resolving auth fresh.
        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}
