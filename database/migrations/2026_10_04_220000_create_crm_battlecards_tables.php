<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Concorrentes para Battlecards
        if (! Schema::hasTable('crm_concorrentes')) {
            Schema::create('crm_concorrentes', function (Blueprint $table) {
                $table->id();
                $table->string('nome')->index();
                $table->string('sigla', 30)->nullable();
                $table->foreignId('cidade_id')->nullable()->constrained('cidade')->nullOnDelete();
                $table->string('bairro')->nullable();
                $table->string('faixa_preco')->nullable(); // mais_barato, equivalente, mais_caro
                $table->decimal('mensalidade_estimada', 10, 2)->nullable();
                $table->string('proposta_pedagogica')->nullable(); // Tradicional, Construtivista, Bilíngue, etc.
                $table->json('pontos_fortes')->nullable();
                $table->json('pontos_fracos')->nullable();
                $table->text('diferenciais_nossos')->nullable();
                $table->text('estrategia_abordagem')->nullable();
                $table->text('observacoes')->nullable();
                $table->boolean('is_ativo')->default(true)->index();
                $table->timestamps();
            });
        }

        // 2. Tabela de Matriz de Objeções Comerciais
        if (! Schema::hasTable('crm_objecoes')) {
            Schema::create('crm_objecoes', function (Blueprint $table) {
                $table->id();
                $table->string('titulo')->index();
                $table->string('categoria', 50)->default('geral')->index(); // preco, distancia, pedagogico, estrutura, vagas, outro
                $table->text('descricao')->nullable();
                $table->text('resposta_sugerida');
                $table->text('pergunta_virada')->nullable();
                $table->text('dicas_postura')->nullable();
                $table->integer('ordem')->default(0);
                $table->boolean('is_ativo')->default(true)->index();
                $table->timestamps();
            });
        }

        // 3. Conexão na tabela interessado para tracking de perda por concorrência
        Schema::table('interessado', function (Blueprint $table) {
            if (! Schema::hasColumn('interessado', 'concorrente_id')) {
                $table->foreignId('concorrente_id')->nullable()->after('motivo_perda')->constrained('crm_concorrentes')->nullOnDelete();
            }
            if (! Schema::hasColumn('interessado', 'fator_decisivo_concorrente')) {
                $table->string('fator_decisivo_concorrente')->nullable()->after('concorrente_id');
            }
            if (! Schema::hasColumn('interessado', 'detalhes_concorrencia')) {
                $table->text('detalhes_concorrencia')->nullable()->after('fator_decisivo_concorrente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            if (Schema::hasColumn('interessado', 'concorrente_id')) {
                $table->dropForeign(['concorrente_id']);
                $table->dropColumn('concorrente_id');
            }
            if (Schema::hasColumn('interessado', 'fator_decisivo_concorrente')) {
                $table->dropColumn('fator_decisivo_concorrente');
            }
            if (Schema::hasColumn('interessado', 'detalhes_concorrencia')) {
                $table->dropColumn('detalhes_concorrencia');
            }
        });

        Schema::dropIfExists('crm_objecoes');
        Schema::dropIfExists('crm_concorrentes');
    }
};
