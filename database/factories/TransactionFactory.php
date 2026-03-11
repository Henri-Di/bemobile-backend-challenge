<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransactionStatusEnum;
use App\Models\Client;
use App\Models\Gateway;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Factory responsible for generating Transaction entities for tests.
 *
 * This factory ensures:
 * - Valid relations with Client and Gateway
 * - Consistent monetary values stored in minor units
 * - Safe default status
 * - Required card metadata for persistence
 * - Reusable states for testing different transaction flows
 *
 * Intended for:
 * - Feature tests
 * - Integration tests
 * - Development seeding
 *
 * @extends Factory<Transaction>
 */
final class TransactionFactory extends Factory
{
    /**
     * The model associated with the factory.
     *
     * @var class-string<Transaction>
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'gateway_id' => $this->resolveExistingGatewayId(),
            'status' => TransactionStatusEnum::PENDING,
            'amount' => $this->generateAmountInMinorUnits(),
            'external_id' => $this->generateExternalReference(),
            'card_last_numbers' => $this->generateCardLastNumbers(),
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
                'No gateway found for TransactionFactory. Seed gateways first or use forGateway().'
            );
        }

        return (int) $gatewayId;
    }

    /**
     * Generate a valid transaction amount in minor units.
     */
    protected function generateAmountInMinorUnits(): int
    {
        return fake()->numberBetween(1000, 500000);
    }

    /**
     * Generate a gateway external reference.
     */
    protected function generateExternalReference(): string
    {
        return 'txn_' . fake()->unique()->uuid();
    }

    /**
     * Generate the last four card digits.
     */
    protected function generateCardLastNumbers(): string
    {
        return (string) fake()->numberBetween(1000, 9999);
    }

    /**
     * State: mark transaction as successful (paid).
     */
    public function paid(): self
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatusEnum::PAID,
        ]);
    }

    /**
     * State: mark transaction as failed.
     */
    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatusEnum::FAILED,
        ]);
    }

    /**
     * State: mark transaction as refunded.
     */
    public function refunded(): self
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatusEnum::REFUNDED,
        ]);
    }

    /**
     * State: attach an existing client.
     */
    public function forClient(Client $client): self
    {
        return $this->state(fn (): array => [
            'client_id' => $client->id,
        ]);
    }

    /**
     * State: attach an existing gateway.
     */
    public function forGateway(Gateway $gateway): self
    {
        return $this->state(fn (): array => [
            'gateway_id' => $gateway->id,
        ]);
    }

    /**
     * State: define the last four card digits.
     */
    public function withCardLastNumbers(string $cardLastNumbers): self
    {
        return $this->state(fn (): array => [
            'card_last_numbers' => preg_replace('/\D+/', '', $cardLastNumbers) ?: '0000',
        ]);
    }

    /**
     * Deterministic transaction for testing scenarios.
     */
    public function deterministic(): self
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatusEnum::PENDING,
            'amount' => 1999,
            'external_id' => 'txn_test_reference',
            'card_last_numbers' => '4242',
        ]);
    }
}