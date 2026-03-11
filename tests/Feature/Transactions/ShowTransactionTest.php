<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\RefundStatusEnum;
use App\Enums\TransactionAttemptStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\TransactionAttempt;
use App\Models\TransactionProduct;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\GatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ShowTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GatewaySeeder::class);
    }

    public function test_it_shows_a_transaction_for_an_authenticated_user(): void
    {
        $user = $this->authorizedUser();

        $client = Client::factory()->create([
            'name' => 'Cliente Show',
            'email' => 'cliente.show@example.com',
            'document' => '12345678901',
        ]);

        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'external_id' => 'txn_ext_123',
            'status' => TransactionStatusEnum::PAID,
            'amount' => 25990,
            'card_last_numbers' => '4242',
            'gateway_response_code' => '200',
            'gateway_message' => 'Authorized',
            'paid_at' => CarbonImmutable::parse('2026-03-10 15:30:00', 'UTC'),
            'refunded_at' => null,
            'created_by_user_id' => $user->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Produto Premium',
            'amount' => 12995,
            'is_active' => true,
        ]);

        TransactionProduct::query()->create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_amount' => 12995,
            'total_amount' => 25990,
        ]);

        TransactionAttempt::query()->create([
            'transaction_id' => $transaction->id,
            'gateway_id' => $gateway->id,
            'attempt_number' => 1,
            'status' => TransactionAttemptStatusEnum::SUCCESS,
            'external_id' => 'attempt_ext_123',
            'request_payload_json' => [],
            'response_payload_json' => ['message' => 'authorized'],
            'error_message' => null,
            'processed_at' => CarbonImmutable::parse('2026-03-10 15:29:00', 'UTC'),
        ]);

        $response = $this->authenticatedJsonGet(
            $user,
            sprintf('/api/v1/transactions/%d', $transaction->id)
        );

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $transaction->id,
                    'external_id' => 'txn_ext_123',
                    'status' => TransactionStatusEnum::PAID->value,
                    'amount' => 25990,
                    'card_last_numbers' => '4242',
                    'gateway_response_code' => '200',
                    'gateway_message' => 'Authorized',
                    'client' => [
                        'id' => $client->id,
                        'name' => 'Cliente Show',
                        'email' => 'cliente.show@example.com',
                        'document' => '12345678901',
                    ],
                    'gateway' => [
                        'id' => $gateway->id,
                        'code' => (string) $gateway->code,
                        'name' => (string) $gateway->name,
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(1, 'data.attempts')
            ->assertJsonCount(0, 'data.refunds')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_amount', 12995)
            ->assertJsonPath('data.items.0.total_amount', 25990)
            ->assertJsonPath('data.items.0.product.id', $product->id)
            ->assertJsonPath('data.items.0.product.name', 'Produto Premium')
            ->assertJsonPath('data.attempts.0.attempt_number', 1)
            ->assertJsonPath('data.attempts.0.status', TransactionAttemptStatusEnum::SUCCESS->value)
            ->assertJsonPath('data.attempts.0.external_id', 'attempt_ext_123')
            ->assertJsonPath('data.attempts.0.gateway.id', $gateway->id)
            ->assertJsonPath('data.attempts.0.gateway.code', (string) $gateway->code)
            ->assertJsonPath('data.attempts.0.gateway.name', (string) $gateway->name);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'external_id',
                'status',
                'amount',
                'card_last_numbers',
                'gateway_response_code',
                'gateway_message',
                'paid_at',
                'refunded_at',
                'client' => [
                    'id',
                    'name',
                    'email',
                    'document',
                ],
                'gateway' => [
                    'id',
                    'code',
                    'name',
                ],
                'items' => [
                    '*' => [
                        'id',
                        'quantity',
                        'unit_amount',
                        'total_amount',
                        'product' => [
                            'id',
                            'name',
                        ],
                    ],
                ],
                'attempts' => [
                    '*' => [
                        'id',
                        'attempt_number',
                        'status',
                        'external_id',
                        'error_message',
                        'processed_at',
                        'gateway' => [
                            'id',
                            'code',
                            'name',
                        ],
                    ],
                ],
                'refunds',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_it_shows_a_transaction_with_refunds(): void
    {
        $user = $this->authorizedUser();

        $client = Client::factory()->create([
            'name' => 'Cliente Refund',
            'email' => 'cliente.refund@example.com',
        ]);

        $gateway = $this->gatewayByCode('gateway_2');

        $transaction = Transaction::factory()->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::REFUNDED,
            'amount' => 10000,
            'card_last_numbers' => '1111',
            'paid_at' => CarbonImmutable::parse('2026-03-10 12:00:00', 'UTC'),
            'refunded_at' => CarbonImmutable::parse('2026-03-10 13:00:00', 'UTC'),
        ]);

        Refund::query()->create([
            'transaction_id' => $transaction->id,
            'gateway_id' => $gateway->id,
            'requested_by_user_id' => $user->id,
            'amount' => 10000,
            'status' => RefundStatusEnum::PROCESSED,
            'external_refund_id' => 'refund_ext_001',
            'processed_at' => CarbonImmutable::parse('2026-03-10 13:00:00', 'UTC'),
        ]);

        $response = $this->authenticatedJsonGet(
            $user,
            sprintf('/api/v1/transactions/%d', $transaction->id)
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.status', TransactionStatusEnum::REFUNDED->value)
            ->assertJsonPath('data.refunds.0.amount', 10000)
            ->assertJsonPath('data.refunds.0.status', RefundStatusEnum::PROCESSED->value)
            ->assertJsonPath('data.refunds.0.external_refund_id', 'refund_ext_001')
            ->assertJsonPath('data.refunds.0.gateway.id', $gateway->id)
            ->assertJsonPath('data.refunds.0.gateway.code', (string) $gateway->code)
            ->assertJsonPath('data.refunds.0.gateway.name', (string) $gateway->name);
    }

    public function test_it_returns_empty_collections_when_transaction_has_no_items_attempts_or_refunds(): void
    {
        $user = $this->authorizedUser();

        $client = Client::factory()->create();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PENDING,
            'amount' => 5000,
            'card_last_numbers' => '1234',
        ]);

        $response = $this->authenticatedJsonGet(
            $user,
            sprintf('/api/v1/transactions/%d', $transaction->id)
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.attempts', [])
            ->assertJsonPath('data.refunds', []);
    }

    public function test_it_formats_datetimes_in_america_sao_paulo_timezone(): void
    {
        $user = $this->authorizedUser();

        $client = Client::factory()->create();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PAID,
            'amount' => 1500,
            'card_last_numbers' => '9876',
            'paid_at' => CarbonImmutable::parse('2026-03-10 15:00:00', 'UTC'),
        ]);

        $transaction->timestamps = false;
        $transaction->forceFill([
            'created_at' => CarbonImmutable::parse('2026-03-10 16:00:00', 'UTC'),
            'updated_at' => CarbonImmutable::parse('2026-03-10 17:00:00', 'UTC'),
        ])->save();
        $transaction->timestamps = true;
        $transaction->refresh();

        $response = $this->authenticatedJsonGet(
            $user,
            sprintf('/api/v1/transactions/%d', $transaction->id)
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.paid_at', '2026-03-10 12:00:00')
            ->assertJsonPath('data.created_at', '2026-03-10 13:00:00')
            ->assertJsonPath('data.updated_at', '2026-03-10 14:00:00');
    }

    public function test_it_returns_not_found_when_transaction_does_not_exist(): void
    {
        $user = $this->authorizedUser();

        $response = $this->authenticatedJsonGet($user, '/api/v1/transactions/999999');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_it_requires_authentication_to_show_a_transaction(): void
    {
        $client = Client::factory()->create();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PENDING,
            'amount' => 1000,
            'card_last_numbers' => '2222',
        ]);

        $response = $this->getJson(
            sprintf('/api/v1/transactions/%d', $transaction->id)
        );

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    private function authenticatedJsonGet(User $user, string $uri)
    {
        Sanctum::actingAs($user);

        return $this->getJson($uri);
    }

    private function authorizedUser(): User
    {
        return User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);
    }

    private function gatewayByCode(string $code): Gateway
    {
        /** @var Gateway $gateway */
        $gateway = Gateway::query()
            ->where('code', $code)
            ->firstOrFail();

        return $gateway;
    }
}