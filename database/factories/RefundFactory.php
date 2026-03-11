<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RefundStatusEnum;
use App\Models\Gateway;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Factory responsible for generating Refund entities for tests.
 *
 * Ensures:
 * - Valid relations with Transaction, Gateway and User
 * - Safe and deterministic monetary values (minor units)
 * - Consistent external refund references
 * - Optional sanitized gateway response payload
 * - Reusable states for common refund scenarios
 *
 * Intended for:
 * - Feature tests
 * - Integration tests
 * - Development seed generation
 *
 * @extends Factory<Refund>
 */
final class RefundFactory extends Factory
{
    /**
     * The model associated with the factory.
     *
     * @var class-string<Refund>
     */
    protected $model = Refund::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'gateway_id' => $this->resolveExistingGatewayId(),
            'requested_by_user_id' => User::factory(),
            'external_refund_id' => $this->generateExternalRefundReference(),
            'status' => RefundStatusEnum::PROCESSED,
            'amount' => $this->generateAmountInMinorUnits(),
            'response_payload_json' => $this->generateSafeGatewayPayload(),
            'processed_at' => now(),
        ];
    }

    /**
     * Resolve an existing gateway id.
     *
     * @throws RuntimeException
     */
    protected function resolveExistingGatewayId(): int
    {
        $gatewayId = Gateway::query()->orderedByPriority()->value('id');

        if ($gatewayId === null) {
            throw new RuntimeException(
                'No gateway found for RefundFactory. Seed gateways first or use forGateway().'
            );
        }

        return (int) $gatewayId;
    }

    /**
     * Generate a valid refund amount in minor units.
     */
    protected function generateAmountInMinorUnits(): int
    {
        return fake()->numberBetween(100, 500000);
    }

    /**
     * Generate a gateway external refund reference.
     */
    protected function generateExternalRefundReference(): string
    {
        return 'rf_' . fake()->unique()->uuid();
    }

    /**
     * Generate a simulated gateway response payload.
     *
     * Sensitive values are intentionally omitted.
     *
     * @return array<string, mixed>
     */
    protected function generateSafeGatewayPayload(): array
    {
        return [
            'status' => 'success',
            'gateway_message' => fake()->sentence(),
            'processed_at' => now()->toISOString(),
            'reference' => fake()->uuid(),
        ];
    }

    /**
     * State: pending refund request.
     */
    public function pending(): self
    {
        return $this->state(fn (): array => [
            'status' => RefundStatusEnum::PENDING,
            'processed_at' => null,
        ]);
    }

    /**
     * State: failed refund attempt.
     */
    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => RefundStatusEnum::FAILED,
            'response_payload_json' => [
                'status' => 'error',
                'message' => fake()->sentence(),
            ],
        ]);
    }

    /**
     * State: processed refund (successful).
     */
    public function processed(): self
    {
        return $this->state(fn (): array => [
            'status' => RefundStatusEnum::PROCESSED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Associate an existing transaction.
     */
    public function forTransaction(Transaction $transaction): self
    {
        return $this->state(fn (): array => [
            'transaction_id' => $transaction->id,
        ]);
    }

    /**
     * Associate an existing gateway.
     */
    public function forGateway(Gateway $gateway): self
    {
        return $this->state(fn (): array => [
            'gateway_id' => $gateway->id,
        ]);
    }

    /**
     * Associate an existing requesting user.
     */
    public function requestedBy(User $user): self
    {
        return $this->state(fn (): array => [
            'requested_by_user_id' => $user->id,
        ]);
    }

    /**
     * Deterministic refund useful for repeatable tests.
     */
    public function deterministic(): self
    {
        return $this->state(fn (): array => [
            'external_refund_id' => 'rf_test_reference',
            'status' => RefundStatusEnum::PROCESSED,
            'amount' => 1999,
            'response_payload_json' => [
                'status' => 'success',
                'reference' => 'gateway_test_reference',
            ],
            'processed_at' => now(),
        ]);
    }
}