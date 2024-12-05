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
        Schema::create('group_send', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('send_id')->nullable()->constrained('menssages')->nullOnDelete();
            $table->string('title', 255);
            $table->string('sub_title', 255);
            $table->longText('description');
            $table->string('image')->nullable();
            $table->json('phone_number')->nullable();
            $table->json('contact_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_send');
    }
};
