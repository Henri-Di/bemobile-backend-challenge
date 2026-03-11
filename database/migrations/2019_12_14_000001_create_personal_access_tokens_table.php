<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'personal_access_tokens';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->bigIncrements('id');

            /*
             |--------------------------------------------------------------------------
             | Token owner
             |--------------------------------------------------------------------------
             |
             | Allows different authenticatable models to own access tokens
             | such as User, Admin or other tokenable entities.
             |
             */
            $table->string('tokenable_type')
                ->comment('Morph class name of the token owner');

            $table->unsignedBigInteger('tokenable_id')
                ->comment('Primary key of the token owner');

            $table->string('name', 100)
                ->comment('Human-readable token name or identifier');

            $table->string('token', 64)
                ->comment('SHA-256 hashed personal access token');

            $table->text('abilities')
                ->nullable()
                ->comment('Token abilities stored as JSON');

            $table->timestamp('last_used_at')
                ->nullable()
                ->comment('Timestamp of the last token usage');

            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Token expiration timestamp');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes and constraints
             |--------------------------------------------------------------------------
             */

            $table->unique('token', 'personal_access_tokens_token_unique');

            $table->index(
                ['tokenable_type', 'tokenable_id'],
                'personal_access_tokens_tokenable_index'
            );

            $table->index(
                'expires_at',
                'personal_access_tokens_expires_at_index'
            );

            $table->index(
                'last_used_at',
                'personal_access_tokens_last_used_at_index'
            );

            $table->index(
                'created_at',
                'personal_access_tokens_created_at_index'
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