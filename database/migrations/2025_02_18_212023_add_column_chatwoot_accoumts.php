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
            $table->unsignedBigInteger('chatwoot_accoumts')->nullable()->after('cadencia_id');
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
