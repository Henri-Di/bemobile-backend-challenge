<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\TransactionProduct;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Core seeders (dados fixos do sistema)
        |--------------------------------------------------------------------------
        */

        $this->call([
            UserSeeder::class,
            GatewaySeeder::class,
            ProductSeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Fake data (Factories)
        |--------------------------------------------------------------------------
        */

        // Users adicionais
        User::factory()->count(10)->create();

        // Clients
        $clients = Client::factory()->count(40)->create();

        // Products
        $products = Product::factory()->count(20)->create();

        // Transactions
        $transactions = Transaction::factory()
            ->count(50)
            ->create();

        // Transaction products (pivot)
        $transactions->each(function ($transaction) use ($products) {

            $products
                ->random(rand(1, 4))
                ->each(function ($product) use ($transaction) {

                    TransactionProduct::factory()->create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'quantity' => rand(1, 3),
                        'unit_amount' => $product->amount,
                        'total_amount' => $product->amount * rand(1, 3),
                    ]);

                });
        });

        // Refunds
        Refund::factory()->count(10)->create();
    }
}