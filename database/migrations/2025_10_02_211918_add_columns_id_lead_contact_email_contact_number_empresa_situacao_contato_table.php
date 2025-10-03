<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list_contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('id_lead')->nullable()->after('chatwoot_id');
            $table->string('contact_email')->nullable()->after('id_lead');
            $table->string('contact_number_empresa')->nullable()->after('contact_email');
            $table->string('situacao_contato')->nullable()->after('contact_number_empresa');
        });
    }

    public function down(): void
    {
        Schema::table('list_contacts', function (Blueprint $table) {
            $table->dropColumn([
                'id_lead',
                'contact_email',
                'contact_number_empresa',
                'situacao_contato',
            ]);
        });
    }
};
