<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_flow_leads', function (Blueprint $table) {
            $table->boolean('is_whatsapp')->default(false)
                ->after('contact_number')
                ->comment('Indica se o número é WhatsApp válido');
        });
    }

    public function down(): void
    {
        Schema::table('sync_flow_leads', function (Blueprint $table) {
            $table->dropColumn('is_whatsapp');
        });
    }
};
