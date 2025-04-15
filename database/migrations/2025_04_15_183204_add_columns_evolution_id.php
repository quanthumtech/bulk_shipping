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
        Schema::table('menssages', function (Blueprint $table) {
            $table->foreignId('evolution_id')->nullable()->constrained()->onDelete('set null')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menssages', function (Blueprint $table) {
            $table->dropForeign(['evolution_id']);
            $table->dropColumn('evolution_id');
        });
    }
};
