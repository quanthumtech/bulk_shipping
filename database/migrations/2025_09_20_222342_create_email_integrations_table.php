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
        Schema::create('email_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('host'); // e.g., smtp.gmail.com
            $table->integer('port'); // e.g., 587
            $table->string('username'); // e.g., user@example.com
            $table->string('password'); // Encrypted or plain; consider encrypting in production
            $table->string('encryption')->nullable(); // e.g., 'tls', 'ssl', 'none'
            $table->string('from_email'); // e.g., noreply@example.com
            $table->string('from_name'); // e.g., My Company
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_integrations');
    }
};
