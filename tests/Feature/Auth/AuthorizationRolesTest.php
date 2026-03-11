<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\TransactionStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\GatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AuthorizationRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GatewaySeeder::class);
    }

    public function test_admin_can_access_gateways_index(): void
    {
        $admin = $this->createUser(UserRoleEnum::ADMIN);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/gateways');

        $response->assertOk();
    }

    public function test_manager_cannot_access_gateways_index(): void
    {
        $manager = $this->createUser(UserRoleEnum::MANAGER);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/gateways');

        $this->assertForbiddenLike($response);
    }

    public function test_finance_cannot_access_gateways_index(): void
    {
        $finance = $this->createUser(UserRoleEnum::FINANCE);

        Sanctum::actingAs($finance);

        $response = $this->getJson('/api/v1/gateways');

        $this->assertForbiddenLike($response);
    }

    public function test_user_cannot_access_gateways_index(): void
    {
        $user = $this->createUser(UserRoleEnum::USER);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/gateways');

        $this->assertForbiddenLike($response);
    }

    public function test_manager_can_access_users_index(): void
    {
        $manager = $this->createUser(UserRoleEnum::MANAGER);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk();
    }

    public function test_admin_can_access_users_index(): void
    {
        $admin = $this->createUser(UserRoleEnum::ADMIN);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk();
    }

    public function test_finance_cannot_access_users_index(): void
    {
        $finance = $this->createUser(UserRoleEnum::FINANCE);

        Sanctum::actingAs($finance);

        $response = $this->getJson('/api/v1/users');

        $this->assertForbiddenLike($response);
    }

    public function test_user_cannot_access_users_index(): void
    {
        $user = $this->createUser(UserRoleEnum::USER);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users');

        $this->assertForbiddenLike($response);
    }

    public function test_finance_can_create_product(): void
    {
        $finance = $this->createUser(UserRoleEnum::FINANCE);

        Sanctum::actingAs($finance);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produto Teste Finance',
            'amount' => 1999,
            'is_active' => true,
        ]);

        $this->assertAllowedCreation($response);

        $this->assertDatabaseHas('products', [
            'name' => 'Produto Teste Finance',
        ]);
    }

    public function test_manager_can_create_product(): void
    {
        $manager = $this->createUser(UserRoleEnum::MANAGER);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produto Teste Manager',
            'amount' => 2999,
            'is_active' => true,
        ]);

        $this->assertAllowedCreation($response);

        $this->assertDatabaseHas('products', [
            'name' => 'Produto Teste Manager',
        ]);
    }

    public function test_admin_can_create_product(): void
    {
        $admin = $this->createUser(UserRoleEnum::ADMIN);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produto Teste Admin',
            'amount' => 3999,
            'is_active' => true,
        ]);

        $this->assertAllowedCreation($response);

        $this->assertDatabaseHas('products', [
            'name' => 'Produto Teste Admin',
        ]);
    }

    public function test_regular_user_cannot_create_product(): void
    {
        $user = $this->createUser(UserRoleEnum::USER);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produto Proibido',
            'amount' => 4999,
            'is_active' => true,
        ]);

        $this->assertForbiddenLike($response);
    }

    public function test_finance_can_access_refund_route(): void
    {
        $finance = $this->createUser(UserRoleEnum::FINANCE);
        $transaction = $this->createRefundableTransaction();

        Sanctum::actingAs($finance);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'amount' => $transaction->amount,
            'reason' => 'Customer request',
        ]);

        $this->assertNotForbidden($response);
    }

    public function test_admin_can_access_refund_route(): void
    {
        $admin = $this->createUser(UserRoleEnum::ADMIN);
        $transaction = $this->createRefundableTransaction();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'amount' => $transaction->amount,
            'reason' => 'Manual review approved',
        ]);

        $this->assertNotForbidden($response);
    }

    public function test_manager_cannot_refund_transaction(): void
    {
        $manager = $this->createUser(UserRoleEnum::MANAGER);
        $transaction = $this->createRefundableTransaction();

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'amount' => $transaction->amount,
            'reason' => 'Not allowed',
        ]);

        $this->assertForbiddenLike($response);
    }

    public function test_regular_user_cannot_refund_transaction(): void
    {
        $user = $this->createUser(UserRoleEnum::USER);
        $transaction = $this->createRefundableTransaction();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'amount' => $transaction->amount,
            'reason' => 'Not allowed',
        ]);

        $this->assertForbiddenLike($response);
    }

    private function createUser(UserRoleEnum $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createRefundableTransaction(): Transaction
    {
        $gatewayId = Gateway::query()->value('id');

        $this->assertNotNull($gatewayId, 'No seeded gateway found for refund test.');

        return Transaction::factory()->create([
            'gateway_id' => (int) $gatewayId,
            'status' => TransactionStatusEnum::PAID,
            'amount' => 1999,
            'card_last_numbers' => '4242',
        ]);
    }

    private function assertForbiddenLike(TestResponse $response): void
    {
        $status = $response->getStatusCode();

        $this->assertContains(
            $status,
            [401, 403],
            "Unexpected status {$status}. Response: {$response->getContent()}"
        );
    }

    private function assertAllowedCreation(TestResponse $response): void
    {
        $status = $response->getStatusCode();

        $this->assertContains(
            $status,
            [200, 201],
            "Unexpected status {$status}. Response: {$response->getContent()}"
        );
    }

    private function assertNotForbidden(TestResponse $response): void
    {
        $status = $response->getStatusCode();

        $this->assertNotContains(
            $status,
            [401, 403],
            "Unexpected status {$status}. Response: {$response->getContent()}"
        );
    }
}