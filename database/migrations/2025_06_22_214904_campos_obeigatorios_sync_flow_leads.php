<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sync_flow_leads', function (Blueprint $table) {
            $table->string('id_card')->nullable()->change();
            $table->string('contact_name')->nullable(false)->change();
            $table->string('contact_number')->nullable(false)->change();
            $table->string('contact_number_empresa')->nullable()->change();
            $table->string('contact_email')->nullable()->change();
            $table->string('estagio')->nullable()->change();
            $table->unsignedBigInteger('cadencia_id')->nullable()->change();
            $table->unsignedBigInteger('chatwoot_accoumts')->nullable()->change();
            $table->timestamp('created_at')->nullable()->change();
            $table->timestamp('updated_at')->nullable()->change();
            $table->string('situacao_contato')->nullable()->change();
            $table->string('email_vendedor')->nullable()->change();
            $table->string('nome_vendedor')->nullable()->change();
            $table->unsignedBigInteger('id_vendedor')->nullable()->change();
            $table->string('chatwoot_status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_flow_leads', function (Blueprint $table) {
            // Reverter as alterações conforme necessário
        });
    }
};
