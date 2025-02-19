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
        Schema::create('cadence_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sync_flow_leads_id');
            $table->unsignedBigInteger('etapa_id');
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();

            // Adicione as chaves estrangeiras se necessário
            $table->foreign('sync_flow_leads_id')->references('id')->on('sync_flow_leads')->onDelete('cascade');
            $table->foreign('etapa_id')->references('id')->on('etapas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cadence_messages');
    }
};
