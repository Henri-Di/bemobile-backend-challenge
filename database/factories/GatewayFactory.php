<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GatewayCodeEnum;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory responsible for generating Gateway entities for tests.
 *
 * This factory ensures:
 * - Valid gateway codes
 * - Distinct names
 * - Proper priority values
 * - Valid JSON settings payload
 * - Active gateways by default
 *
 * Intended for:
 * - Feature tests
 * - Integration tests
 * - Development seed generation
 *
 * @extends Factory<Gateway>
 */
final class GatewayFactory extends Factory
{
    /**
     * The model associated with the factory.
     *
     * @var class-string<Gateway>
     */
    protected $model = Gateway::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->randomElement($this->availableCodes());

        return [
            'name' => $this->resolveGatewayName($code),
            'code' => $code,
            'priority' => fake()->unique()->numberBetween(1, 10),
            'is_active' => true,
            'settings_json' => $this->generateSettings($code),
        ];
    }

    /**
     * Return the available gateway codes.
     *
     * @return array<int, string>
     */
    private function availableCodes(): array
    {
        return [
            GatewayCodeEnum::GATEWAY_ONE->value,
            GatewayCodeEnum::GATEWAY_TWO->value,
        ];
    }

    /**
     * Resolve a human-readable gateway name from the code.
     */
    private function resolveGatewayName(string $code): string
    {
        return match ($code) {
            GatewayCodeEnum::GATEWAY_ONE->value => 'Gateway One',
            GatewayCodeEnum::GATEWAY_TWO->value => 'Gateway Two',
            default => 'Gateway',
        };
    }

    /**
     * Generate the gateway settings payload.
     *
     * @return array<string, mixed>
     */
    private function generateSettings(string $code): array
    {
        return match ($code) {
            GatewayCodeEnum::GATEWAY_ONE->value => [
                'base_url' => 'http://bemobile_gateway_mock:3001',
            ],
            GatewayCodeEnum::GATEWAY_TWO->value => [
                'base_url' => 'http://bemobile_gateway_mock:3002',
            ],
            default => [],
        };
    }

    /**
     * State: mark the gateway as inactive.
     */
    public function inactive(): self
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /**
     * State: configure Gateway One.
     */
    public function gatewayOne(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Gateway One',
            'code' => GatewayCodeEnum::GATEWAY_ONE->value,
            'priority' => 1,
            'is_active' => true,
            'settings_json' => [
                'base_url' => 'http://bemobile_gateway_mock:3001',
            ],
        ]);
    }

    /**
     * State: configure Gateway Two.
     */
    public function gatewayTwo(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Gateway Two',
            'code' => GatewayCodeEnum::GATEWAY_TWO->value,
            'priority' => 2,
            'is_active' => true,
            'settings_json' => [
                'base_url' => 'http://bemobile_gateway_mock:3002',
            ],
        ]);
    }

    /**
     * Deterministic gateway useful for repeatable tests.
     */
    public function deterministic(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Gateway One',
            'code' => GatewayCodeEnum::GATEWAY_ONE->value,
            'priority' => 1,
            'is_active' => true,
            'settings_json' => [
                'base_url' => 'http://bemobile_gateway_mock:3001',
            ],
        ]);
    }
}