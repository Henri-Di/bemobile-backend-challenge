<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'users';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {

            $table->bigIncrements('id');

            $table->string('name', 150)
                ->comment('Full name of the internal system user');

            $table->string('email', 150)
                ->comment('Unique email used for authentication');

            $table->string('password', 255)
                ->comment('Hashed password used for authentication');

            $table->enum('role', [
                UserRoleEnum::ADMIN->value,
                UserRoleEnum::MANAGER->value,
                UserRoleEnum::FINANCE->value,
                UserRoleEnum::USER->value,
            ])
            ->default(UserRoleEnum::USER->value)
            ->comment('Internal application role');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Logical activation flag for the user account');

            $table->timestamp('email_verified_at')
                ->nullable()
                ->comment('Timestamp when the user email was verified');

            $table->rememberToken();

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->unique('email', 'users_email_unique');

            $table->index('role', 'users_role_index');

            $table->index('is_active', 'users_is_active_index');

            $table->index(
                ['is_active', 'role'],
                'users_active_role_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};