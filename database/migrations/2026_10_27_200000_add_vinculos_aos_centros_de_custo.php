<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_custos', function (Blueprint $table): void {
            // Centro de custo de uma turma (custo direto dela) ou de um curso (custo direto dividido entre as turmas do curso).
            $table->foreignId('turma_id')->nullable()->after('nome')->constrained('turma')->nullOnDelete();
            $table->foreignId('curso_id')->nullable()->after('turma_id')->constrained('curso')->nullOnDelete();
            // Fora do resultado das turmas: folha dos professores (já contada pela apuração de hora-aula), investimentos, impostos.
            $table->boolean('fora_do_resultado_das_turmas')->default(false)->after('curso_id');
        });
    }

    public function down(): void
    {
        Schema::table('centro_custos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('turma_id');
            $table->dropConstrainedForeignId('curso_id');
            $table->dropColumn('fora_do_resultado_das_turmas');
        });
    }
};
