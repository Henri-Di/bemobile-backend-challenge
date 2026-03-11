<?php

declare(strict_types=1);

use App\Enums\RefundStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'refunds';

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
                ->comment('Transaction associated with this refund');

            $table->foreignId('gateway_id')
                ->constrained('gateways')
                ->cascadeOnUpdate()
                ->restrictOnDelete()
                ->comment('Gateway responsible for processing the refund');

            $table->string('external_refund_id', 100)
                ->nullable()
                ->comment('External refund identifier returned by the gateway');

            $table->string('status', 20)
                ->default(RefundStatusEnum::PENDING->value)
                ->comment('Refund lifecycle status');

            $table->unsignedInteger('amount')
                ->comment('Refund amount stored in minor units');

            $table->foreignId('requested_by_user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete()
                ->comment('Internal user who requested the refund');

            $table->json('response_payload_json')
                ->nullable()
                ->comment('Gateway response payload for the refund flow');

            $table->timestamp('processed_at')
                ->nullable()
                ->comment('Timestamp when the refund was processed');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->index('transaction_id', 'refunds_transaction_id_index');

            $table->index('gateway_id', 'refunds_gateway_id_index');

            $table->index('status', 'refunds_status_index');

            $table->index('requested_by_user_id', 'refunds_requested_by_user_id_index');

            $table->index(
                ['transaction_id', 'status'],
                'refunds_transaction_id_status_index'
            );

            $table->index('processed_at', 'refunds_processed_at_index');

            $table->index(
                ['transaction_id', 'processed_at'],
                'refunds_transaction_id_processed_at_index'
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