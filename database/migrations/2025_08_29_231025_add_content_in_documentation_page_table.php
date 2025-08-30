<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentation_pages', function (Blueprint $table) {
            $table->json('content')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('documentation_pages', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
