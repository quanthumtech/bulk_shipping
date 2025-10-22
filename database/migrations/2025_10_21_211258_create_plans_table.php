<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('billing_cycle')->default('monthly');
            $table->integer('max_cadence_flows')->default(0);
            $table->integer('max_attendance_channels')->default(0);
            $table->integer('max_daily_leads')->default(0);
            $table->integer('message_storage_days')->default(0);
            $table->string('support_level')->default('basic');
            $table->boolean('has_crm_integration')->default(true);
            $table->boolean('has_chatwoot_connection')->default(true);
            $table->boolean('has_scheduled_sending')->default(true);
            $table->boolean('has_operational_reports')->default(true);
            $table->boolean('has_performance_panel')->default(true);
            $table->text('description')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};