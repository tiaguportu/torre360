<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Professores designados para a preceptoria de uma matrícula específica. O vínculo é com a matrícula, não com a pessoa do
     * aluno: o aluno que muda de turma ou de período começa a nova matrícula sem designados. Na agenda, a matrícula enxerga os
     * horários do professor conselheiro da turma e os destes professores, de mais ninguém.
     */
    public function up(): void
    {
        Schema::create('matricula_professor_preceptoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('professor_id')->constrained('pessoa')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['matricula_id', 'professor_id'], 'matricula_professor_preceptoria_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricula_professor_preceptoria');
    }
};
