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
        Schema::create('menssages', function (Blueprint $table) {
            $table->id();
            $table->string('contact_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->text('message_content')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('file')->nullable();
            $table->boolean('active')->default(0)->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menssages');
    }
};
