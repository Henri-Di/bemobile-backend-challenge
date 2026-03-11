<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'gateways';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->string('code', 50)
                ->comment('Technical gateway identifier, for example: gateway_1');

            $table->string('name', 150)
                ->comment('Human-readable gateway name');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Indicates whether the gateway is eligible for processing');

            $table->unsignedSmallInteger('priority')
                ->comment('Fallback attempt order; lower values have higher priority');

            $table->json('settings_json')
                ->nullable()
                ->comment('Additional gateway configuration payload');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes and constraints
             |--------------------------------------------------------------------------
             */

            $table->unique('code', 'gateways_code_unique');

            $table->unique('priority', 'gateways_priority_unique');

            $table->index('is_active', 'gateways_is_active_index');

            $table->index('priority', 'gateways_priority_index');

            $table->index(
                ['is_active', 'priority'],
                'gateways_is_active_priority_index'
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