<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\TransactionStatusEnum;
use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\PaymentService;
use Database\Seeders\GatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class StoreTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GatewaySeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_guest_can_create_transaction_with_valid_payload(): void
    {
        $productA = Product::factory()->create([
            'name' => 'Produto A',
            'amount' => 1990,
            'is_active' => true,
        ]);

        $productB = Product::factory()->create([
            'name' => 'Produto B',
            'amount' => 2590,
            'is_active' => true,
        ]);

        $transaction = $this->createPersistedTransaction([
            'status' => TransactionStatusEnum::PAID,
            'amount' => (2 * $productA->amount) + $productB->amount,
            'external_id' => 'txn_test_success_001',
            'card_last_numbers' => '4242',
        ]);

        $this->mockPaymentServicePurchaseSuccess($transaction);

        $response = $this->postJson('/api/v1/transactions', [
            'customer' => [
                'name' => 'Matheus Diamantino',
                'email' => 'matheus@example.com',
                'document' => '12345678909',
            ],
            'card' => [
                'number' => '4111111111114242',
                'holder_name' => 'MATHEUS DIAMANTINO',
                'brand' => 'visa',
                'cvv' => '123',
            ],
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertCreated();

        $response->assertJsonStructure([
            'data' => [
                'id',
                'status',
                'amount',
            ],
        ]);
    }

    public function test_transaction_store_calls_payment_service_with_valid_payload(): void
    {
        $product = Product::factory()->create([
            'amount' => 9990,
            'is_active' => true,
        ]);

        $transaction = $this->createPersistedTransaction([
            'status' => TransactionStatusEnum::PAID,
            'amount' => 3 * $product->amount,
            'external_id' => 'txn_test_backend_calc',
            'card_last_numbers' => '4242',
        ]);

        $mock = Mockery::mock(PaymentService::class);

        $mock->shouldReceive('purchase')
            ->once()
            ->withArgs(function (array $payload, $user) use ($product): bool {
                return $user === null
                    && ($payload['client']['name'] ?? null) === 'Cliente Teste'
                    && ($payload['client']['email'] ?? null) === 'cliente@example.com'
                    && ($payload['client']['document'] ?? null) === '12345678909'
                    && ($payload['card']['number'] ?? null) === '5555555555554242'
                    && ($payload['card']['cvv'] ?? null) === '321'
                    && ($payload['products'][0]['product_id'] ?? null) === $product->id
                    && ($payload['products'][0]['quantity'] ?? null) === 3;
            })
            ->andReturn($transaction->fresh());

        $this->app->instance(PaymentService::class, $mock);

        $response = $this->postJson('/api/v1/transactions', [
            'customer' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
                'document' => '12345678909',
            ],
            'card' => [
                'number' => '5555555555554242',
                'holder_name' => 'CLIENTE TESTE',
                'brand' => 'mastercard',
                'cvv' => '321',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ],
            ],
        ]);

        $response->assertCreated();
    }

    public function test_transaction_fails_when_items_payload_is_missing(): void
    {
        $response = $this->postJson('/api/v1/transactions', [
            'customer' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_transaction_fails_when_product_does_not_exist(): void
    {
        $response = $this->postJson('/api/v1/transactions', [
            'customer' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
            'items' => [
                [
                    'product_id' => 999999,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_transaction_fails_when_quantity_is_invalid(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/transactions', [
            'customer' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_transaction_fails_when_card_number_is_invalid(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/transactions', [
            'customer' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'card' => [
                'number' => '123',
                'cvv' => '123',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_transaction_returns_error_when_payment_service_fails(): void
    {
        $product = Product::factory()->create([
            'amount' => 1990,
            'is_active' => true,
        ]);

        $mock = Mockery::mock(PaymentService::class);

        $mock->shouldReceive('purchase')
            ->once()
            ->andThrow(new \RuntimeException('Payment rejected', 422));

        $this->app->instance(PaymentService::class, $mock);

        $response = $this->postJson('/api/v1/transactions', [
            'customer' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
        ]);
    }

    private function createPersistedTransaction(array $attributes = []): Transaction
    {
        $gatewayId = (int) Gateway::query()->value('id');
        $client = Client::factory()->create();

        return Transaction::factory()->create(array_merge([
            'client_id' => $client->id,
            'gateway_id' => $gatewayId,
            'status' => TransactionStatusEnum::PENDING,
            'card_last_numbers' => '4242',
        ], $attributes));
    }

    private function mockPaymentServicePurchaseSuccess(Transaction $transaction): void
    {
        $mock = Mockery::mock(PaymentService::class);

        $mock->shouldReceive('purchase')
            ->once()
            ->andReturn($transaction->fresh());

        $this->app->instance(PaymentService::class, $mock);
    }
}