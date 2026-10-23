<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carga letiva e faltas excessivas (Ondas 49 e 57).
 *
 * - `cronograma_aula`: aula que não aconteceu (professor faltou, evento, clima) e a aula de reposição, que aponta para a
 *   que ela repõe. A aula não realizada deixa de contar no realizado e a reposição conta.
 * - `frequencia_escolar`: a falta pode ser justificada (atestado, por exemplo). A lei mede as faltas; o ECA (art. 56, II),
 *   as injustificadas: o sistema guarda a diferença e a decisão de notificar é da direção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cronograma_aula', function (Blueprint $table) {
            $table->timestamp('nao_realizada_em')->nullable();
            $table->string('motivo_nao_realizacao', 160)->nullable();
            $table->foreignId('reposicao_de_id')->nullable()->constrained('cronograma_aula')->nullOnDelete();
        });

        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->boolean('justificada')->default(false);
            $table->string('justificativa', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->dropColumn(['justificada', 'justificativa']);
        });

        Schema::table('cronograma_aula', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reposicao_de_id');
            $table->dropColumn(['nao_realizada_em', 'motivo_nao_realizacao']);
        });
    }
};
