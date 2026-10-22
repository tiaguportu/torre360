<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grade horária assistida (Onda 64): os tempos de aula de cada turno (o "sinal" da escola) e as janelas em que cada
 * professor pode ou não dar aula. Com eles o sistema confere a grade contra a matriz curricular e sugere horários.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tempos_aula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turno')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->timestamps();

            $table->unique(['turno_id', 'ordem'], 'uq_tempo_aula_turno_ordem');
        });

        Schema::create('disponibilidades_professor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->string('tipo', 15)->default('disponivel');
            $table->string('observacao', 160)->nullable();
            $table->timestamps();

            $table->index(['pessoa_id', 'dia_semana'], 'idx_disponibilidade_pessoa_dia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidades_professor');
        Schema::dropIfExists('tempos_aula');
    }
};
