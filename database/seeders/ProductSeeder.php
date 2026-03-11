<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the product seeders.
     */
    public function run(): void
    {
        foreach ($this->products() as $product) {
            Product::query()->updateOrCreate(
                ['name' => $product['name']],
                [
                    'amount' => $product['amount'],
                    'is_active' => $product['is_active'],
                ]
            );
        }
    }

    /**
     * Returns the default product seed definitions.
     *
     * Amount values are stored in minor units.
     *
     * @return array<int, array{
     *     name: string,
     *     amount: int,
     *     is_active: bool
     * }>
     */
    private function products(): array
    {
        return [
            [
                'name' => 'Product 1',
                'amount' => 1990,
                'is_active' => true,
            ],
            [
                'name' => 'Product 2',
                'amount' => 2590,
                'is_active' => true,
            ],
            [
                'name' => 'Product 3',
                'amount' => 3990,
                'is_active' => true,
            ],
            [
                'name' => 'Product 4',
                'amount' => 4990,
                'is_active' => true,
            ],
            [
                'name' => 'Product 5',
                'amount' => 9990,
                'is_active' => true,
            ],
        ];
    }
}