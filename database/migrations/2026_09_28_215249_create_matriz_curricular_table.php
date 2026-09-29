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
        Schema::create('matriz_curricular', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serie_id')->constrained('serie')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplina')->cascadeOnDelete();
            $table->unsignedInteger('carga_horaria_semanal')->nullable();
            $table->boolean('obrigatoria')->default(true);
            $table->unsignedInteger('ordem')->nullable();
            $table->timestamps();

            $table->unique(['serie_id', 'disciplina_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matriz_curricular');
    }
};
