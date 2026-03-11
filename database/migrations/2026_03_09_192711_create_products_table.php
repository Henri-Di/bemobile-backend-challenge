<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'products';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->string('name', 150)
                ->comment('Commercial name of the product');

            $table->unsignedInteger('amount')
                ->comment('Product amount stored in minor units');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Logical activation flag for the product');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->index('name', 'products_name_index');

            $table->index('is_active', 'products_is_active_index');

            $table->index(
                ['is_active', 'name'],
                'products_is_active_name_index'
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