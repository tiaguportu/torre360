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
        Schema::create('grade_horario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplina')->cascadeOnDelete();
            $table->foreignId('professor_id')->nullable()->constrained('pessoa')->nullOnDelete();
            $table->foreignId('sala_id')->nullable()->constrained('sala')->nullOnDelete();
            // Mesma convenção de turma_horario.dia_semana: 0=Domingo ... 6=Sábado.
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->timestamps();

            $table->index(['turma_id', 'dia_semana']);
            $table->index(['professor_id', 'dia_semana']);
            $table->index(['sala_id', 'dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_horario');
    }
};
