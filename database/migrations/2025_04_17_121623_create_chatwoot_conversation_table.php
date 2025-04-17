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
        Schema::create('chatwoot_conversation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sync_flow_lead_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('status');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->foreign('sync_flow_lead_id')->references('id')->on('sync_flow_leads')->onDelete('cascade');
            $table->index(['conversation_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatwoot_conversation');
    }
};
