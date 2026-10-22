<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admissão e desligamento de funcionários (Onda 65): um processo por entrada ou saída, com a lista de providências
 * (documentos, exames, contrato, acessos, equipamentos, treinamentos, financeiro) tirada de config/rh_processos.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processos_funcionario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->string('tipo', 15);
            $table->string('status', 15)->default('em_andamento');
            // Admissão: primeiro dia de trabalho. Desligamento: último dia do contrato.
            $table->date('data_prevista');
            $table->string('motivo', 30)->nullable();
            // Criptografado: justa causa, saúde e acordos não devem aparecer em consulta de banco nem em log.
            $table->text('observacao')->nullable();
            $table->foreignId('iniciado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('concluido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();

            $table->index(['funcionario_id', 'tipo', 'status'], 'idx_processo_func_tipo_status');
        });

        Schema::create('itens_processo_funcionario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('processos_funcionario')->cascadeOnDelete();
            $table->string('categoria', 20);
            $table->string('titulo', 200);
            $table->string('orientacao', 300)->nullable();
            $table->boolean('obrigatorio')->default(true);
            $table->date('prazo')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamp('concluido_em')->nullable();
            $table->boolean('nao_se_aplica')->default(false);
            $table->string('observacao', 300)->nullable();
            $table->string('arquivo_path')->nullable();
            $table->foreignId('concluido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['processo_id', 'ordem'], 'idx_item_processo_func_ordem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_processo_funcionario');
        Schema::dropIfExists('processos_funcionario');
    }
};
