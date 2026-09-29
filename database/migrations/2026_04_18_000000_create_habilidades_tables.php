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
        if (! Schema::hasTable('habilidades') && ! Schema::hasTable('habilidade')) {
            Schema::create('habilidades', function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->nullable()->index(); // BNCC code
                $table->string('nome');
                $table->text('descricao')->nullable();
                $table->foreignId('disciplina_id')->nullable()->constrained('disciplina')->onDelete('cascade');
                $table->enum('tipo', ['BNCC', 'Institucional'])->default('BNCC');
                $table->timestamps();
            });
        }

        $tabelaHabilidade = Schema::hasTable('habilidade') ? 'habilidade' : 'habilidades';

        if (! Schema::hasTable('turma_habilidade')) {
            Schema::create('turma_habilidade', function (Blueprint $table) use ($tabelaHabilidade) {
                $table->id();
                $table->foreignId('turma_id')->constrained('turma')->onDelete('cascade');
                $table->foreignId('habilidade_id')->constrained($tabelaHabilidade)->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('avaliacao_habilidades')) {
            Schema::create('avaliacao_habilidades', function (Blueprint $table) use ($tabelaHabilidade) {
                $table->id();
                $table->foreignId('matricula_id')->constrained('matricula')->onDelete('cascade');
                $table->foreignId('habilidade_id')->constrained($tabelaHabilidade)->onDelete('cascade');
                $table->foreignId('etapa_avaliativa_id')->constrained('etapa_avaliativa')->onDelete('cascade');
                $table->string('conceito'); // Ex: Pleno, Suficiente, etc
                $table->text('observacao')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avaliacao_habilidades');
        Schema::dropIfExists('turma_habilidade');
        Schema::dropIfExists('habilidades');
    }
};
