<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajuste manual da situação do aluno no Censo Escolar (2ª etapa). Só guarda o que a secretaria corrigiu: o resto é
 * calculado na hora a partir da matrícula, das transferências e do resultado final.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('censo_situacoes_aluno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->unsignedTinyInteger('situacao');
            $table->text('justificativa');
            $table->foreignId('definido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('matricula_id', 'uq_censo_situacao_matricula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('censo_situacoes_aluno');
    }
};
