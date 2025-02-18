<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cadencia_id')->constrained()->onDelete('cascade');
            $table->string('titulo');
            $table->integer('tempo');
            $table->enum('unidade_tempo', ['dias', 'horas', 'minutos']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etapas');
    }
};
