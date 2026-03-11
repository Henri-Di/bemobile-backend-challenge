<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\GatewayPaymentInterface;
use App\DataTransferObjects\GatewayChargeResult;
use App\Enums\TransactionAttemptStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Exceptions\GatewayIntegrationException;
use App\Models\Gateway;
use App\Models\Product;
use App\Services\PaymentService;
use Database\Seeders\GatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class PaymentServiceFallbackTest extends TestCase
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

    public function test_it_uses_the_first_active_gateway_by_priority_when_it_succeeds(): void
    {
        [$gatewayOne, $gatewayTwo] = $this->orderedGateways();

        $gatewayOneService = Mockery::mock(GatewayPaymentInterface::class);
        $gatewayTwoService = Mockery::mock(GatewayPaymentInterface::class);

        $gatewayOneService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $gatewayOneService->shouldReceive('charge')
            ->once()
            ->andReturn(GatewayChargeResult::success(
                externalId: 'gw1_txn_001',
                status: 'approved',
                message: 'Approved',
                responseCode: '200',
                requestPayload: [
                    'amount' => 1990,
                ],
                responsePayload: [
                    'status' => 'success',
                ],
            ));

        $gatewayTwoService->shouldNotReceive('isAvailable');
        $gatewayTwoService->shouldNotReceive('charge');

        $service = $this->makePaymentService($gatewayOneService, $gatewayTwoService);

        $transaction = $service->purchase($this->validPayload(), null);
        $transaction->refresh();

        $this->assertSame($gatewayOne->id, $transaction->gateway_id);
        $this->assertSame('gw1_txn_001', $transaction->external_id);
        $this->assertSame(
            TransactionStatusEnum::PAID->value,
            $this->normalizeStatus($transaction->status)
        );

        $this->assertDatabaseHas('transaction_attempts', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gatewayOne->id,
            'attempt_number' => 1,
            'status' => TransactionAttemptStatusEnum::SUCCESS->value,
            'external_id' => 'gw1_txn_001',
        ]);

        $this->assertDatabaseCount('transaction_attempts', 1);
    }

    public function test_it_falls_back_to_second_gateway_when_first_fails(): void
    {
        [$gatewayOne, $gatewayTwo] = $this->orderedGateways();

        $gatewayOneService = Mockery::mock(GatewayPaymentInterface::class);
        $gatewayTwoService = Mockery::mock(GatewayPaymentInterface::class);

        $gatewayOneService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $gatewayOneService->shouldReceive('charge')
            ->once()
            ->andThrow(new GatewayIntegrationException(
                'Gateway 1 unavailable',
                'gateway_1'
            ));

        $gatewayTwoService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $gatewayTwoService->shouldReceive('charge')
            ->once()
            ->andReturn(GatewayChargeResult::success(
                externalId: 'gw2_txn_001',
                status: 'approved',
                message: 'Approved on fallback',
                responseCode: '200',
                requestPayload: [
                    'amount' => 1990,
                ],
                responsePayload: [
                    'status' => 'success',
                ],
            ));

        $service = $this->makePaymentService($gatewayOneService, $gatewayTwoService);

        $transaction = $service->purchase($this->validPayload(), null);
        $transaction->refresh();

        $this->assertSame($gatewayTwo->id, $transaction->gateway_id);
        $this->assertSame('gw2_txn_001', $transaction->external_id);
        $this->assertSame(
            TransactionStatusEnum::PAID->value,
            $this->normalizeStatus($transaction->status)
        );

        $this->assertDatabaseHas('transaction_attempts', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gatewayOne->id,
            'attempt_number' => 1,
            'status' => TransactionAttemptStatusEnum::FAILED->value,
        ]);

        $this->assertDatabaseHas('transaction_attempts', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gatewayTwo->id,
            'attempt_number' => 2,
            'status' => TransactionAttemptStatusEnum::SUCCESS->value,
            'external_id' => 'gw2_txn_001',
        ]);

        $this->assertDatabaseCount('transaction_attempts', 2);
    }

    public function test_it_marks_transaction_as_failed_when_all_gateways_fail(): void
    {
        [$gatewayOne, $gatewayTwo] = $this->orderedGateways();

        $gatewayOneService = Mockery::mock(GatewayPaymentInterface::class);
        $gatewayTwoService = Mockery::mock(GatewayPaymentInterface::class);

        $gatewayOneService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $gatewayOneService->shouldReceive('charge')
            ->once()
            ->andThrow(new GatewayIntegrationException(
                'Gateway 1 unavailable',
                'gateway_1'
            ));

        $gatewayTwoService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $gatewayTwoService->shouldReceive('charge')
            ->once()
            ->andThrow(new GatewayIntegrationException(
                'Gateway 2 unavailable',
                'gateway_2'
            ));

        $service = $this->makePaymentService($gatewayOneService, $gatewayTwoService);

        $transaction = $service->purchase($this->validPayload(), null);
        $transaction->refresh();

        $this->assertNull($transaction->gateway_id);
        $this->assertNull($transaction->external_id);

        $this->assertSame(
            TransactionStatusEnum::FAILED->value,
            $this->normalizeStatus($transaction->status)
        );

        $this->assertSame(
            'All gateways failed to process the transaction.',
            $transaction->gateway_message
        );

        $this->assertDatabaseHas('transaction_attempts', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gatewayOne->id,
            'attempt_number' => 1,
            'status' => TransactionAttemptStatusEnum::FAILED->value,
        ]);

        $this->assertDatabaseHas('transaction_attempts', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gatewayTwo->id,
            'attempt_number' => 2,
            'status' => TransactionAttemptStatusEnum::FAILED->value,
        ]);

        $this->assertDatabaseCount('transaction_attempts', 2);
    }

    public function test_it_skips_unavailable_gateway_and_uses_next_available_one(): void
    {
        [$gatewayOne, $gatewayTwo] = $this->orderedGateways();

        $gatewayOneService = Mockery::mock(GatewayPaymentInterface::class);
        $gatewayTwoService = Mockery::mock(GatewayPaymentInterface::class);

        $gatewayOneService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(false);

        $gatewayOneService->shouldNotReceive('charge');

        $gatewayTwoService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $gatewayTwoService->shouldReceive('charge')
            ->once()
            ->andReturn(GatewayChargeResult::success(
                externalId: 'gw2_txn_002',
                status: 'approved',
                message: 'Approved',
                responseCode: '200',
                requestPayload: [],
                responsePayload: [
                    'status' => 'success',
                ],
            ));

        $service = $this->makePaymentService($gatewayOneService, $gatewayTwoService);

        $transaction = $service->purchase($this->validPayload(), null);
        $transaction->refresh();

        $this->assertSame($gatewayTwo->id, $transaction->gateway_id);
        $this->assertSame('gw2_txn_002', $transaction->external_id);
        $this->assertSame(
            TransactionStatusEnum::PAID->value,
            $this->normalizeStatus($transaction->status)
        );

        $this->assertDatabaseHas('transaction_attempts', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gatewayOne->id,
            'attempt_number' => 1,
            'status' => TransactionAttemptStatusEnum::FAILED->value,
            'error_message' => 'Gateway is unavailable.',
        ]);

        $this->assertDatabaseHas('transaction_attempts', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gatewayTwo->id,
            'attempt_number' => 2,
            'status' => TransactionAttemptStatusEnum::SUCCESS->value,
            'external_id' => 'gw2_txn_002',
        ]);

        $this->assertDatabaseCount('transaction_attempts', 2);
    }

    public function test_it_respects_gateway_priority_order(): void
    {
        [$gatewayOne, $gatewayTwo] = $this->orderedGateways();

        $gatewayOne->update(['priority' => 999]);
        $gatewayTwo->update(['priority' => 1]);
        $gatewayOne->update(['priority' => 2]);

        $gatewayOne->refresh();
        $gatewayTwo->refresh();

        $gatewayOneService = Mockery::mock(GatewayPaymentInterface::class);
        $gatewayTwoService = Mockery::mock(GatewayPaymentInterface::class);

        $gatewayTwoService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $gatewayTwoService->shouldReceive('charge')
            ->once()
            ->andReturn(GatewayChargeResult::success(
                externalId: 'gw2_priority_first',
                status: 'approved',
                message: 'Approved',
                responseCode: '200',
                requestPayload: [],
                responsePayload: [
                    'status' => 'success',
                ],
            ));

        $gatewayOneService->shouldNotReceive('isAvailable');
        $gatewayOneService->shouldNotReceive('charge');

        $service = $this->makePaymentService($gatewayOneService, $gatewayTwoService);

        $transaction = $service->purchase($this->validPayload(), null);
        $transaction->refresh();

        $this->assertSame($gatewayTwo->id, $transaction->gateway_id);
        $this->assertSame('gw2_priority_first', $transaction->external_id);
        $this->assertSame(
            TransactionStatusEnum::PAID->value,
            $this->normalizeStatus($transaction->status)
        );

        $this->assertDatabaseHas('transaction_attempts', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gatewayTwo->id,
            'attempt_number' => 1,
            'status' => TransactionAttemptStatusEnum::SUCCESS->value,
            'external_id' => 'gw2_priority_first',
        ]);

        $this->assertDatabaseCount('transaction_attempts', 1);
    }

    private function makePaymentService(
        GatewayPaymentInterface $gatewayOneService,
        GatewayPaymentInterface $gatewayTwoService
    ): PaymentService {
        return new PaymentService(
            db: $this->app->make('db'),
            gatewayOneService: $gatewayOneService,
            gatewayTwoService: $gatewayTwoService,
        );
    }

    /**
     * @return array{0: Gateway, 1: Gateway}
     */
    private function orderedGateways(): array
    {
        $gateways = Gateway::query()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(2, $gateways->count());

        return [$gateways[0], $gateways[1]];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        $product = Product::factory()->create([
            'name' => 'Produto Teste',
            'amount' => 1990,
            'is_active' => true,
        ]);

        return [
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
                'document' => '12345678909',
            ],
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ];
    }

    private function normalizeStatus(mixed $status): ?string
    {
        if ($status === null) {
            return null;
        }

        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return (string) $status;
    }
}