<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'failed_jobs';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {

            $table->bigIncrements('id');

            $table->uuid('uuid')
                ->unique('failed_jobs_uuid_unique')
                ->comment('Unique identifier of the failed job execution');

            $table->string('connection', 100)
                ->comment('Queue connection used for the job');

            $table->string('queue', 100)
                ->comment('Queue where the job was processed');

            $table->longText('payload')
                ->comment('Serialized job payload');

            $table->longText('exception')
                ->comment('Exception stack trace generated during execution');

            $table->timestamp('failed_at')
                ->useCurrent()
                ->comment('Timestamp when the job failure occurred');

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->index(
                'failed_at',
                'failed_jobs_failed_at_idx'
            );

            $table->index(
                'queue',
                'failed_jobs_queue_idx'
            );

            $table->index(
                ['connection', 'queue'],
                'failed_jobs_connection_queue_idx'
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