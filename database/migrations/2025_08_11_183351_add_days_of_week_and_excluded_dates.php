<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cadencias', function (Blueprint $table) {
            $table->json('days_of_week')->nullable();
            $table->json('excluded_dates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cadencias', function (Blueprint $table) {
            $table->dropColumn('days_of_week');
            $table->dropColumn('excluded_dates');
        });
    }
};
