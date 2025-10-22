<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained('plans')->onDelete('set null');
            $table->date('plan_start_date')->nullable();
            $table->date('plan_end_date')->nullable();
            $table->integer('used_cadence_flows')->default(0);
            $table->integer('used_daily_leads')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'plan_start_date', 'plan_end_date', 'used_cadence_flows', 'used_daily_leads']);
        });
    }
};