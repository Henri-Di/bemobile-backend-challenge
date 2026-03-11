<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory responsible for generating test Product entities.
 *
 * This factory ensures:
 * - Distinct and normalized product names
 * - Valid monetary amounts stored in minor units
 * - Safe default active state
 * - Reusable states for feature and integration tests
 *
 * Intended for:
 * - Feature tests
 * - Integration tests
 * - Development seed generation
 *
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    /**
     * The model that this factory corresponds to.
     *
     * @var class-string<Product>
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * Generates a valid and normalized product payload.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->generateSafeProductName(),
            'amount' => $this->generateAmountInMinorUnits(),
            'is_active' => true,
        ];
    }

    /**
     * Generate a safe and normalized distinct product name.
     */
    protected function generateSafeProductName(): string
    {
        $baseName = fake()->unique()->words(
            nb: fake()->numberBetween(1, 3),
            asText: true
        );

        $normalized = preg_replace('/\s+/u', ' ', trim($baseName));

        return Str::title((string) $normalized);
    }

    /**
     * Generate a valid product amount in minor units.
     *
     * Example:
     * 1099 => 10.99
     */
    protected function generateAmountInMinorUnits(): int
    {
        return fake()->numberBetween(500, 500000);
    }

    /**
     * State: mark the product as inactive.
     */
    public function inactive(): self
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /**
     * State: generate a low-priced product.
     */
    public function lowPrice(): self
    {
        return $this->state(fn (): array => [
            'amount' => fake()->numberBetween(100, 1999),
        ]);
    }

    /**
     * State: generate a mid-priced product.
     */
    public function mediumPrice(): self
    {
        return $this->state(fn (): array => [
            'amount' => fake()->numberBetween(2000, 9999),
        ]);
    }

    /**
     * State: generate a high-priced product.
     */
    public function highPrice(): self
    {
        return $this->state(fn (): array => [
            'amount' => fake()->numberBetween(10000, 500000),
        ]);
    }

    /**
     * State: generate a deterministic product useful for tests.
     */
    public function deterministic(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Test Product',
            'amount' => 1999,
            'is_active' => true,
        ]);
    }
}