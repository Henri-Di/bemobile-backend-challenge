<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\GatewayCodeEnum;
use App\Models\Gateway;
use Illuminate\Database\Seeder;

final class GatewaySeeder extends Seeder
{
    /**
     * Seed the default gateway records required by the application.
     */
    public function run(): void
    {
        foreach ($this->gateways() as $gateway) {
            Gateway::query()->updateOrCreate(
                [
                    'code' => $gateway['code'],
                ],
                [
                    'name' => $gateway['name'],
                    'is_active' => $gateway['is_active'],
                    'priority' => $gateway['priority'],
                    'settings_json' => $gateway['settings_json'],
                ]
            );
        }
    }

    /**
     * Return the default gateway seed definitions.
     *
     * @return array<int, array{
     *     code:string,
     *     name:string,
     *     is_active:bool,
     *     priority:int,
     *     settings_json:array<string, mixed>
     * }>
     */
    private function gateways(): array
    {
        return [
            [
                'code' => GatewayCodeEnum::GATEWAY_ONE->value,
                'name' => 'Gateway One',
                'is_active' => true,
                'priority' => 1,
                'settings_json' => [
                    'base_url' => $this->nullableStringConfig('gateways.gateway1.base_url')
                        ?? 'http://bemobile_gateway_mock:3001',
                ],
            ],
            [
                'code' => GatewayCodeEnum::GATEWAY_TWO->value,
                'name' => 'Gateway Two',
                'is_active' => true,
                'priority' => 2,
                'settings_json' => [
                    'base_url' => $this->nullableStringConfig('gateways.gateway2.base_url')
                        ?? 'http://bemobile_gateway_mock:3002',
                ],
            ],
        ];
    }

    /**
     * Return a trimmed config value or null when empty.
     */
    private function nullableStringConfig(string $key): ?string
    {
        $value = config($key);

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}