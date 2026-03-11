<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Contracts\GatewayPaymentInterface;
use App\DataTransferObjects\GatewayChargeResult;
use App\DataTransferObjects\GatewayRefundResult;
use App\DataTransferObjects\PaymentChargeData;
use App\Enums\GatewayCodeEnum;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\GatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class RefundTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GatewaySeeder::class);
    }

    public function test_it_processes_a_refund_successfully_for_a_paid_transaction(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->authorizedRefundUser();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'gateway_id' => $gateway->id,
            'external_id' => 'txn_paid_001',
            'status' => 'paid',
            'amount' => 1990,
            'refunded_at' => null,
        ]);

        $this->bindGatewayRefundService(
            'gateway_1',
            new FakeGatewayRefundService(
                refundHandler: function (string $externalId, ?int $amount): GatewayRefundResult {
                    $this->assertSame('txn_paid_001', $externalId);
                    $this->assertSame(1990, $amount);

                    return GatewayRefundResult::success(
                        externalRefundId: 'refund_ext_001',
                        status: 'processed',
                        message: 'Refund approved',
                        responsePayload: [
                            'refunded' => true,
                        ],
                    );
                },
                gatewayCode: GatewayCodeEnum::GATEWAY_ONE,
                gatewayName: 'Fake Gateway 1',
            )
        );

        $this->bindGatewayRefundService(
            'gateway_2',
            new FakeGatewayRefundService(
                gatewayCode: GatewayCodeEnum::GATEWAY_TWO,
                gatewayName: 'Fake Gateway 2',
            )
        );

        $response = $this->authenticate($user)
            ->postJson($this->refundUrl($transaction), []);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('message', 'Refund processed successfully.')
            ->assertJsonPath('data.transaction_id', $transaction->id)
            ->assertJsonPath('data.external_refund_id', 'refund_ext_001')
            ->assertJsonPath('data.status', 'processed');

        $this->assertDatabaseHas('refunds', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gateway->id,
            'external_refund_id' => 'refund_ext_001',
            'status' => 'processed',
            'amount' => 1990,
            'requested_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'refunded',
        ]);
    }

    public function test_it_processes_a_partial_refund_successfully(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->authorizedRefundUser();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'gateway_id' => $gateway->id,
            'external_id' => 'txn_paid_partial_001',
            'status' => 'paid',
            'amount' => 1990,
            'refunded_at' => null,
        ]);

        $this->bindGatewayRefundService(
            'gateway_1',
            new FakeGatewayRefundService(
                refundHandler: function (string $externalId, ?int $amount): GatewayRefundResult {
                    $this->assertSame('txn_paid_partial_001', $externalId);
                    $this->assertSame(990, $amount);

                    return GatewayRefundResult::success(
                        externalRefundId: 'refund_partial_001',
                        status: 'processed',
                        message: 'Partial refund approved',
                        responsePayload: [
                            'refunded' => true,
                            'partial' => true,
                        ],
                    );
                },
                gatewayCode: GatewayCodeEnum::GATEWAY_ONE,
                gatewayName: 'Fake Gateway 1',
            )
        );

        $this->bindGatewayRefundService(
            'gateway_2',
            new FakeGatewayRefundService(
                gatewayCode: GatewayCodeEnum::GATEWAY_TWO,
                gatewayName: 'Fake Gateway 2',
            )
        );

        $response = $this->authenticate($user)
            ->postJson($this->refundUrl($transaction), [
                'amount' => 990,
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('message', 'Refund processed successfully.')
            ->assertJsonPath('data.transaction_id', $transaction->id)
            ->assertJsonPath('data.external_refund_id', 'refund_partial_001')
            ->assertJsonPath('data.status', 'processed');

        $this->assertDatabaseHas('refunds', [
            'transaction_id' => $transaction->id,
            'gateway_id' => $gateway->id,
            'external_refund_id' => 'refund_partial_001',
            'status' => 'processed',
            'amount' => 990,
            'requested_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'refunded',
        ]);
    }

    public function test_it_rejects_refund_for_non_paid_transaction(): void
    {
        $user = $this->authorizedRefundUser();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'gateway_id' => $gateway->id,
            'external_id' => 'txn_pending_001',
            'status' => 'pending',
            'amount' => 1990,
        ]);

        $response = $this->authenticate($user)
            ->postJson($this->refundUrl($transaction), []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('message', 'Only paid transactions can be refunded.');

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_it_rejects_refund_for_already_refunded_transaction(): void
    {
        $user = $this->authorizedRefundUser();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'gateway_id' => $gateway->id,
            'external_id' => 'txn_refunded_001',
            'status' => 'refunded',
            'amount' => 1990,
        ]);

        $response = $this->authenticate($user)
            ->postJson($this->refundUrl($transaction), []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('message', 'Transaction has already been refunded.');

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_it_rejects_refund_when_transaction_has_no_valid_gateway_reference(): void
    {
        $user = $this->authorizedRefundUser();

        $transaction = Transaction::factory()->create([
            'gateway_id' => null,
            'external_id' => null,
            'status' => 'paid',
            'amount' => 1990,
        ]);

        $response = $this->authenticate($user)
            ->postJson($this->refundUrl($transaction), []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath(
                'message',
                'Transaction does not have a valid gateway reference for refund.'
            );

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_it_rejects_refund_when_amount_exceeds_transaction_amount(): void
    {
        $user = $this->authorizedRefundUser();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'gateway_id' => $gateway->id,
            'external_id' => 'txn_paid_002',
            'status' => 'paid',
            'amount' => 1990,
        ]);

        $response = $this->authenticate($user)
            ->postJson($this->refundUrl($transaction), [
                'amount' => 2990,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath(
                'message',
                'Refund amount cannot exceed the original transaction amount.'
            );

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_it_returns_error_when_gateway_returns_unsuccessful_refund(): void
    {
        $user = $this->authorizedRefundUser();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'gateway_id' => $gateway->id,
            'external_id' => 'txn_paid_003',
            'status' => 'paid',
            'amount' => 1990,
        ]);

        $this->bindGatewayRefundService(
            'gateway_1',
            new FakeGatewayRefundService(
                refundHandler: function (string $externalId, ?int $amount): GatewayRefundResult {
                    $this->assertSame('txn_paid_003', $externalId);
                    $this->assertSame(1990, $amount);

                    return GatewayRefundResult::failure(
                        message: 'Refund denied by gateway',
                        responsePayload: [
                            'reason' => 'not_allowed',
                        ],
                        status: 'denied',
                        externalRefundId: null,
                    );
                },
                gatewayCode: GatewayCodeEnum::GATEWAY_ONE,
                gatewayName: 'Fake Gateway 1',
            )
        );

        $this->bindGatewayRefundService(
            'gateway_2',
            new FakeGatewayRefundService(
                gatewayCode: GatewayCodeEnum::GATEWAY_TWO,
                gatewayName: 'Fake Gateway 2',
            )
        );

        $response = $this->authenticate($user)
            ->postJson($this->refundUrl($transaction), []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('message', 'Refund denied by gateway')
            ->assertJsonPath('data.status', 'denied')
            ->assertJsonPath('data.response_payload.reason', 'not_allowed');

        $this->assertDatabaseCount('refunds', 0);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
        ]);
    }

    public function test_it_returns_internal_error_when_gateway_throws_exception(): void
    {
        $user = $this->authorizedRefundUser();
        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'gateway_id' => $gateway->id,
            'external_id' => 'txn_paid_004',
            'status' => 'paid',
            'amount' => 1990,
        ]);

        $this->bindGatewayRefundService(
            'gateway_1',
            new FakeGatewayRefundService(
                refundHandler: function (string $externalId, ?int $amount): GatewayRefundResult {
                    $this->assertSame('txn_paid_004', $externalId);
                    $this->assertSame(1990, $amount);

                    throw new \RuntimeException('Gateway timeout');
                },
                gatewayCode: GatewayCodeEnum::GATEWAY_ONE,
                gatewayName: 'Fake Gateway 1',
            )
        );

        $this->bindGatewayRefundService(
            'gateway_2',
            new FakeGatewayRefundService(
                gatewayCode: GatewayCodeEnum::GATEWAY_TWO,
                gatewayName: 'Fake Gateway 2',
            )
        );

        $response = $this->authenticate($user)
            ->postJson($this->refundUrl($transaction), []);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('message', 'Refund processing failed.')
            ->assertJsonPath('error.type', 'gateway_exception');

        $this->assertDatabaseCount('refunds', 0);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
        ]);
    }

    private function gatewayByCode(string $code): Gateway
    {
        /** @var Gateway $gateway */
        $gateway = Gateway::query()->where('code', $code)->firstOrFail();

        return $gateway;
    }

    private function refundUrl(Transaction $transaction): string
    {
        return route('api.v1.transactions.refund', $transaction);
    }

    private function bindGatewayRefundService(string $gatewayCode, GatewayPaymentInterface $service): void
    {
        $binding = match ($gatewayCode) {
            'gateway_1' => 'gateway.refund.service.gateway_1',
            'gateway_2' => 'gateway.refund.service.gateway_2',
            default => throw new \InvalidArgumentException("Unsupported gateway code [{$gatewayCode}]."),
        };

        $this->app->instance($binding, $service);
    }

    private function authenticate(User $user): self
    {
        return $this->actingAs($user, 'sanctum');
    }

    private function authorizedRefundUser(): User
    {
        return User::factory()->create([
            'role' => 'ADMIN',
            'is_active' => true,
        ]);
    }
}

final class FakeGatewayRefundService implements GatewayPaymentInterface
{
    /**
     * @var null|\Closure(string, ?int): GatewayRefundResult
     */
    private ?\Closure $refundHandler;

    private GatewayCodeEnum $gatewayCode;

    private string $gatewayName;

    /**
     * @param null|\Closure(string, ?int): GatewayRefundResult $refundHandler
     */
    public function __construct(
        ?\Closure $refundHandler = null,
        GatewayCodeEnum $gatewayCode = GatewayCodeEnum::GATEWAY_ONE,
        string $gatewayName = 'Fake Gateway'
    ) {
        $this->refundHandler = $refundHandler;
        $this->gatewayCode = $gatewayCode;
        $this->gatewayName = $gatewayName;
    }

    public function code(): GatewayCodeEnum
    {
        return $this->gatewayCode;
    }

    public function name(): string
    {
        return $this->gatewayName;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function charge(PaymentChargeData $data): GatewayChargeResult
    {
        throw new \BadMethodCallException('Charge should not be called in refund tests.');
    }

    public function refund(string $externalId, ?int $amount = null): GatewayRefundResult
    {
        if ($this->refundHandler instanceof \Closure) {
            return ($this->refundHandler)($externalId, $amount);
        }

        return GatewayRefundResult::success(
            externalRefundId: 'refund_default',
            status: 'processed',
            message: 'Refund processed',
            responsePayload: [],
        );
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, ['refund', 'refund_partial'], true);
    }
}


