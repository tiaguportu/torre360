<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roteiro de qualificação do lead (SDR, Lote 1): o que a família disse sobre quem decide, o motivo da busca, o prazo, o
 * bairro e a mensalidade que cabe, mais a procedência de cada resposta (`campos`). O que é de cada aluno (situação
 * atual, escola atual) fica no dependente. Idempotente: só cria o que não existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('interessado_qualificacao')) {
            Schema::create('interessado_qualificacao', function (Blueprint $table) {
                $table->id();
                $table->foreignId('interessado_id')->unique()->constrained('interessado')->cascadeOnDelete();
                $table->string('decisor_tipo', 30)->nullable();
                $table->string('decisor_vai_visita', 20)->nullable();
                $table->string('motivo_busca', 40)->nullable();
                $table->string('prazo_decisao', 40)->nullable();
                $table->string('bairro', 120)->nullable();
                $table->json('escolas_em_comparacao')->nullable();
                $table->string('faixa_mensalidade', 30)->nullable();
                // Calculado pelo QualificacaoService: nao_iniciada, em_qualificacao ou qualificado.
                $table->string('resultado', 20)->default('nao_iniciada')->index();
                $table->dateTime('qualificado_em')->nullable();
                // Por campo: estado (perguntado, respondido, recusou), fonte, evidência, data e quem registrou.
                $table->json('campos')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('interessado_dependente', function (Blueprint $table) {
            if (! Schema::hasColumn('interessado_dependente', 'situacao_atual')) {
                $table->string('situacao_atual', 30)->nullable()->after('data_nascimento');
            }

            if (! Schema::hasColumn('interessado_dependente', 'escola_atual')) {
                $table->string('escola_atual', 120)->nullable()->after('situacao_atual');
            }

            if (! Schema::hasColumn('interessado_dependente', 'escola_atual_concorrente_id')) {
                // Não reaproveita `interessado.concorrente_id`, que significa "escolheu esta escola" quando o lead é perdido.
                $table->foreignId('escola_atual_concorrente_id')->nullable()->after('escola_atual')->constrained('crm_concorrentes')->nullOnDelete();
            }

            if (! Schema::hasColumn('interessado_dependente', 'qualificacao_meta')) {
                $table->json('qualificacao_meta')->nullable()->after('escola_atual_concorrente_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interessado_dependente', function (Blueprint $table) {
            if (Schema::hasColumn('interessado_dependente', 'escola_atual_concorrente_id')) {
                $table->dropForeign(['escola_atual_concorrente_id']);
            }

            $colunas = array_values(array_filter(
                ['situacao_atual', 'escola_atual', 'escola_atual_concorrente_id', 'qualificacao_meta'],
                fn (string $coluna): bool => Schema::hasColumn('interessado_dependente', $coluna),
            ));

            if ($colunas !== []) {
                $table->dropColumn($colunas);
            }
        });

        Schema::dropIfExists('interessado_qualificacao');
    }
};
