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
        Schema::create('historico_escolars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('curso')->nullOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->string('codigo_autenticidade', 40)->unique();
            $table->string('situacao', 30)->default('em_curso'); // em_curso, concluido, transferido
            $table->date('data_conclusao')->nullable();
            $table->date('data_emissao');
            $table->string('titulo_certificacao')->nullable();
            $table->text('texto_certificacao')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('emitido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('historico_escolar_anos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historico_escolar_id')->constrained('historico_escolars')->cascadeOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matricula')->nullOnDelete();
            $table->unsignedSmallInteger('ano_letivo');
            $table->foreignId('serie_id')->nullable()->constrained('serie')->nullOnDelete();
            $table->string('serie_nome');
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->string('tipo', 20)->default('interno'); // interno, externo
            $table->string('escola_nome');
            $table->string('escola_cidade')->nullable();
            $table->string('escola_uf', 2)->nullable();
            $table->unsignedSmallInteger('dias_letivos')->nullable()->default(200);
            $table->unsignedSmallInteger('carga_horaria_total')->nullable();
            $table->decimal('frequencia_percentual', 5, 2)->nullable();
            $table->string('situacao_ano', 30)->default('Aprovado');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('historico_escolar_disciplinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historico_escolar_ano_id')->constrained('historico_escolar_anos')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->nullable()->constrained('disciplina')->nullOnDelete();
            $table->string('disciplina_nome');
            $table->string('area_conhecimento')->nullable();
            $table->unsignedSmallInteger('carga_horaria')->nullable();
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->string('conceito', 20)->nullable();
            $table->string('situacao', 30)->default('Aprovado');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historico_escolar_disciplinas');
        Schema::dropIfExists('historico_escolar_anos');
        Schema::dropIfExists('historico_escolars');
    }
};
