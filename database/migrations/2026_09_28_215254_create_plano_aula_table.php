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
        Schema::create('plano_aula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplina')->cascadeOnDelete();
            $table->foreignId('professor_id')->nullable()->constrained('pessoa')->nullOnDelete();
            $table->date('data_prevista');
            $table->text('objetivos');
            $table->text('metodologia')->nullable();
            $table->text('recursos')->nullable();
            $table->text('avaliacao')->nullable();
            $table->json('anexo_material')->nullable();
            $table->foreignId('cronograma_aula_id')->nullable()->constrained('cronograma_aula')->nullOnDelete();
            $table->dateTime('executado_em')->nullable();
            $table->timestamps();

            $table->index(['turma_id', 'data_prevista']);
        });

        $tabelaHabilidade = Schema::hasTable('habilidade') ? 'habilidade' : 'habilidades';

        Schema::create('plano_aula_habilidade', function (Blueprint $table) use ($tabelaHabilidade) {
            $table->id();
            $table->foreignId('plano_aula_id')->constrained('plano_aula')->cascadeOnDelete();
            $table->foreignId('habilidade_id')->constrained($tabelaHabilidade)->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plano_aula_habilidade');
        Schema::dropIfExists('plano_aula');
    }
};
