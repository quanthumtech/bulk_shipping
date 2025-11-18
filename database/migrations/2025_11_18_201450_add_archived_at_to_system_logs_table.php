<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('archived');
        });

        DB::table('system_logs')
            ->where('archived', true)
            ->whereNull('archived_at')
            ->update(['archived_at' => DB::raw('created_at')]);
    }

    public function down()
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
