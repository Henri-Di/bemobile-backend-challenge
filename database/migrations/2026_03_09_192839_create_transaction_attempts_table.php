<?php

declare(strict_types=1);

use App\Enums\TransactionAttemptStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'transaction_attempts';

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
                ->comment('Transaction associated with this gateway attempt');

            $table->foreignId('gateway_id')
                ->constrained('gateways')
                ->cascadeOnUpdate()
                ->restrictOnDelete()
                ->comment('Gateway used in this processing attempt');

            $table->unsignedSmallInteger('attempt_number')
                ->comment('Sequential attempt number within the transaction');

            $table->string('status', 20)
                ->default(TransactionAttemptStatusEnum::PENDING->value)
                ->comment('Processing result of this gateway attempt');

            $table->string('external_id', 100)
                ->nullable()
                ->comment('External identifier returned by the gateway for this attempt');

            $table->json('request_payload_json')
                ->nullable()
                ->comment('Masked request payload sent to the gateway');

            $table->json('response_payload_json')
                ->nullable()
                ->comment('Gateway response payload or normalized error context');

            $table->string('error_message', 255)
                ->nullable()
                ->comment('Error or fallback message associated with the attempt');

            $table->timestamp('processed_at')
                ->nullable()
                ->comment('Timestamp when the attempt was processed');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Constraints
             |--------------------------------------------------------------------------
             */

            $table->unique(
                ['transaction_id', 'gateway_id', 'attempt_number'],
                'transaction_attempts_transaction_gateway_attempt_unique'
            );

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->index('transaction_id', 'transaction_attempts_transaction_id_index');

            $table->index('gateway_id', 'transaction_attempts_gateway_id_index');

            $table->index('status', 'transaction_attempts_status_index');

            $table->index(
                ['transaction_id', 'status'],
                'transaction_attempts_transaction_id_status_index'
            );

            $table->index('processed_at', 'transaction_attempts_processed_at_index');

            $table->index(
                ['transaction_id', 'processed_at'],
                'transaction_attempts_transaction_id_processed_at_index'
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