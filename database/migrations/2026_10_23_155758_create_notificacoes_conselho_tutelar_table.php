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
        Schema::create('notificacoes_conselho_tutelar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->integer('ano_letivo');
            $table->string('numero_oficio')->unique(); // Ex: FICAI-2026-00001
            $table->integer('total_aulas_previstas')->default(0);
            $table->integer('total_presencas')->default(0);
            $table->integer('total_faltas')->default(0);
            $table->integer('total_faltas_justificadas')->default(0);
            $table->integer('total_faltas_injustificadas')->default(0);
            $table->decimal('percentual_faltas', 5, 2)->default(0);
            $table->json('tentativas_contato')->nullable();
            $table->string('status', 30)->default('em_analise'); // em_analise, notificado, resolvido, arquivado
            $table->date('data_notificacao')->nullable();
            $table->string('protocolo_conselho', 100)->nullable();
            $table->string('nome_conselho_tutelar', 150)->nullable();
            $table->string('municipio_conselho', 100)->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacoes_conselho_tutelar');
    }
};
