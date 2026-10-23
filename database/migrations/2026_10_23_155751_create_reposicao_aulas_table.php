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
        Schema::create('reposicao_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplina')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoa')->nullOnDelete();
            $table->foreignId('cronograma_aula_original_id')->nullable()->constrained('cronograma_aula')->nullOnDelete();
            $table->foreignId('cronograma_aula_reposicao_id')->nullable()->constrained('cronograma_aula')->nullOnDelete();
            $table->date('data_reposicao');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->string('motivo');
            $table->string('status', 30)->default('agendada'); // agendada, realizada, cancelada
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
        Schema::dropIfExists('reposicao_aulas');
    }
};
