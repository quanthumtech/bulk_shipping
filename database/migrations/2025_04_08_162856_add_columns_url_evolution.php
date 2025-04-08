<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->string('url_evolution')->nullable()->after('name');
        });

        DB::table('versions')
            ->where('id', 1)
            ->update(['url_evolution' => 'https://evolution.plataformamundo.com.br/message/sendText/']);

        DB::table('versions')
            ->where('id', 2)
            ->update(['url_evolution' => 'https://evolution.quanthum.tec.br/message/sendText/']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->dropColumn('url_evolution');
        });
    }
};
