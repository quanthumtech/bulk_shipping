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
            //nome do vededor
            $table->string('nome_vendedor')->nullable()->after('email_vendedor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_flow_leads', function (Blueprint $table) {
            //
        });
    }
};
