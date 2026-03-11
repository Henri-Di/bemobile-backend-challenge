<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'transaction_products';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete()
                ->comment('Transaction associated with this item');

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete()
                ->comment('Product associated with the transaction');

            $table->unsignedInteger('quantity')
                ->comment('Quantity of the product purchased');

            $table->unsignedInteger('unit_amount')
                ->comment('Historical unit amount stored in minor units');

            $table->unsignedInteger('total_amount')
                ->comment('Historical total amount for the item stored in minor units');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Constraints
             |--------------------------------------------------------------------------
             */

            $table->unique(
                ['transaction_id', 'product_id'],
                'transaction_products_transaction_product_unique'
            );

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->index('transaction_id', 'transaction_products_transaction_id_index');

            $table->index('product_id', 'transaction_products_product_id_index');

            $table->index(
                ['transaction_id', 'created_at'],
                'transaction_products_transaction_created_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};