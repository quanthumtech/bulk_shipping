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
        Schema::create('sync_flow_leads', function (Blueprint $table) {
            $table->id();
            $table->string('id_card');
            $table->string('contact_name');
            $table->string('contact_number');
            $table->string('contact_number_empresa');
            $table->string('contact_email');
            $table->string('estagio');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_flow_leads');
    }
};
