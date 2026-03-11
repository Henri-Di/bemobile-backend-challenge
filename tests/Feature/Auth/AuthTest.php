<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $password = 'Password@123';

        $user = User::factory()->create([
            'name' => 'Matheus',
            'email' => 'matheus@example.com',
            'password' => bcrypt($password),
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->loginRequest([
            'email' => $user->email,
            'password' => $password,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_active',
                    ],
                ],
            ]);

        $this->assertIsString($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame($user->email, $response->json('data.user.email'));
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'matheus@example.com',
            'password' => bcrypt('Password@123'),
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->loginRequest([
            'email' => $user->email,
            'password' => 'SenhaErrada@123',
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $password = 'Password@123';

        $user = User::factory()->create([
            'email' => 'matheus@example.com',
            'password' => bcrypt($password),
            'role' => UserRoleEnum::USER,
            'is_active' => false,
        ]);

        $response = $this->loginRequest([
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_authenticated_user_endpoint(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_authenticated_user_endpoint(): void
    {
        $user = User::factory()->create([
            'role' => UserRoleEnum::MANAGER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/logout');

        $response->assertOk();
    }

    /**
     * Send a valid login payload structure expected by the API.
     *
     * @param array<string, mixed> $overrides
     */
    private function loginRequest(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/login', array_merge([
            'email' => 'user@example.com',
            'password' => 'Password@123',
            'device_name' => 'phpunit',
        ], $overrides));
    }
}