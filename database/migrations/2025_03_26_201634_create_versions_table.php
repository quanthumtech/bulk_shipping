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
       // Criação da tabela
       Schema::create('versions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Inserção das versões iniciais
        DB::table('versions')->insert([
            [
                'name' => 'Evolution v1',
                'type' => 'chat',
                'active' => false,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Evolution v2',
                'type' => 'chat',
                'active' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('versions');
    }
};
