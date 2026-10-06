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
        Schema::create('proposta_comercials', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('interessado_id')->nullable()->constrained('interessado')->nullOnDelete();
            $table->string('responsavel_nome');
            $table->string('responsavel_telefone')->nullable();
            $table->string('responsavel_email')->nullable();
            $table->string('aluno_nome')->nullable();

            $table->foreignId('unidade_id')->constrained('unidade')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('curso')->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('serie')->cascadeOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turma')->nullOnDelete();
            $table->foreignId('turno_id')->nullable()->constrained('turno')->nullOnDelete();

            $table->integer('quantidade_alunos')->default(1);
            $table->decimal('valor_tabela_mensal', 10, 2);
            $table->string('tipo_desconto')->default('percentual'); // percentual ou fixo
            $table->decimal('desconto_solicitado', 10, 2)->default(0);
            $table->decimal('valor_desconto_mensal', 10, 2)->default(0);
            $table->decimal('valor_liquido_mensal', 10, 2);
            $table->integer('quantidade_parcelas')->default(12);
            $table->decimal('valor_total_anual', 10, 2);

            $table->text('motivo_desconto')->nullable();
            $table->string('status')->default('aguardando_aprovacao');
            $table->string('nivel_alcada_necessario')->default('consultor'); // consultor, coordenacao, diretoria

            $table->foreignId('solicitado_por_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('aprovado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('aprovado_em')->nullable();
            $table->text('motivo_recusa')->nullable();
            $table->date('validade');
            $table->text('observacoes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposta_comercials');
    }
};
