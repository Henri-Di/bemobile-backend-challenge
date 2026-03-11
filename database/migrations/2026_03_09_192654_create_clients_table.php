<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'clients';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->string('name', 150)
                ->comment('Full name of the end customer');

            $table->string('email', 150)
                ->comment('Customer email used for identification and communication');

            $table->string('document', 30)
                ->nullable()
                ->comment('Customer document identifier such as CPF or CNPJ');

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes and constraints
             |--------------------------------------------------------------------------
             */

            $table->unique('email', 'clients_email_unique');

            $table->index('name', 'clients_name_index');

            $table->index('document', 'clients_document_index');

            $table->index(
                ['name', 'email'],
                'clients_name_email_index'
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