<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menssages', function (Blueprint $table) {
            $table->json('emails')->nullable()->after('phone_number');
            $table->foreignId('email_integration_id')->nullable()->constrained('email_integrations')->onDelete('set null')->after('evolution_id');
        });
    }

    public function down(): void
    {
        Schema::table('menssages', function (Blueprint $table) {
            $table->dropForeign(['email_integration_id']);
            $table->dropColumn(['emails', 'email_integration_id']);
        });
    }
};
