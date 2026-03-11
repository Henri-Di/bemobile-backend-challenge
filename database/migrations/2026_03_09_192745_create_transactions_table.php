<?php

declare(strict_types=1);

use App\Enums\TransactionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'transactions';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnUpdate()
                ->restrictOnDelete()
                ->comment('Customer associated with the transaction');

            $table->foreignId('gateway_id')
                ->nullable()
                ->constrained('gateways')
                ->cascadeOnUpdate()
                ->nullOnDelete()
                ->comment('Gateway that successfully processed the transaction');

            $table->string('external_id', 100)
                ->nullable()
                ->comment('External transaction identifier returned by the gateway');

            $table->string('status', 20)
                ->default(TransactionStatusEnum::PENDING->value)
                ->comment('Main transaction lifecycle status');

            $table->unsignedInteger('amount')
                ->comment('Total transaction amount stored in minor units');

            $table->char('card_last_numbers', 4)
                ->comment('Last four digits of the payment card');

            $table->string('gateway_response_code', 50)
                ->nullable()
                ->comment('Response code returned by the relevant gateway attempt');

            $table->string('gateway_message', 255)
                ->nullable()
                ->comment('Short gateway integration message');

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete()
                ->comment('Internal user who initiated the transaction');

            $table->timestamp('paid_at')
                ->nullable()
                ->comment('Timestamp when the transaction was marked as paid');

            $table->timestamp('refunded_at')
                ->nullable()
                ->comment('Timestamp when the transaction was marked as refunded');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->index('client_id', 'transactions_client_id_index');
            $table->index('gateway_id', 'transactions_gateway_id_index');
            $table->index('status', 'transactions_status_index');
            $table->index('external_id', 'transactions_external_id_index');
            $table->index('created_by_user_id', 'transactions_created_by_user_id_index');
            $table->index(['client_id', 'status'], 'transactions_client_id_status_index');
            $table->index(['status', 'created_at'], 'transactions_status_created_at_index');
            $table->index('paid_at', 'transactions_paid_at_index');
            $table->index('refunded_at', 'transactions_refunded_at_index');
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