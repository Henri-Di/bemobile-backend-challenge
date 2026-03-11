<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory responsible for generating TransactionProduct pivot entities.
 *
 * This factory ensures:
 * - Valid relations with Transaction and Product
 * - Consistent quantity generation
 * - Safe monetary values stored in minor units
 * - Automatic subtotal calculation
 *
 * Intended for:
 * - Feature tests
 * - Integration tests
 * - Development seed generation
 *
 * @extends Factory<TransactionProduct>
 */
final class TransactionProductFactory extends Factory
{
    /**
     * The model associated with the factory.
     *
     * @var class-string<TransactionProduct>
     */
    protected $model = TransactionProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->generateQuantity();
        $unitAmount = $this->generateUnitAmount();

        return [
            'transaction_id' => Transaction::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'total_amount' => $this->calculateTotalAmount($quantity, $unitAmount),
        ];
    }

    /**
     * Generate a valid quantity.
     */
    protected function generateQuantity(): int
    {
        return fake()->numberBetween(1, 10);
    }

    /**
     * Generate a valid unit amount in minor units.
     */
    protected function generateUnitAmount(): int
    {
        return fake()->numberBetween(500, 200000);
    }

    /**
     * Calculate subtotal for the transaction product.
     */
    protected function calculateTotalAmount(int $quantity, int $unitAmount): int
    {
        return $quantity * $unitAmount;
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
     * Associate an existing product.
     */
    public function forProduct(Product $product): self
    {
        return $this->state(fn (): array => [
            'product_id' => $product->id,
            'unit_amount' => $product->amount,
        ]);
    }

    /**
     * Define a custom quantity.
     */
    public function quantity(int $quantity): self
    {
        return $this->state(function () use ($quantity): array {
            $unitAmount = $this->generateUnitAmount();

            return [
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'total_amount' => $this->calculateTotalAmount($quantity, $unitAmount),
            ];
        });
    }

    /**
     * Deterministic transaction product for repeatable tests.
     */
    public function deterministic(): self
    {
        $quantity = 2;
        $unitAmount = 1999;

        return $this->state(fn (): array => [
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'total_amount' => $quantity * $unitAmount,
        ]);
    }
}