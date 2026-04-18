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
        Schema::create('questionarios', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->dateTime('inicio_aplicacao')->nullable();
            $table->dateTime('fim_aplicacao')->nullable();
            $table->boolean('is_anonimo')->default(false);
            $table->boolean('is_ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('questionario_blocos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_id')->constrained('questionarios')->onDelete('cascade');
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('questionario_perguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_bloco_id')->constrained('questionario_blocos')->onDelete('cascade');
            $table->string('enunciado');
            $table->string('tipo'); // objetiva, discursiva, likert, multipla_escolha
            $table->json('opcoes')->nullable(); // Para objetiva/multipla_escolha/likert
            $table->boolean('is_obrigatoria')->default(true);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('questionario_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_id')->constrained('questionarios')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('perfil_institucional')->nullable(); // aluno, professor, colaborador, etc.
            $table->timestamp('inicio_preenchimento')->nullable();
            $table->timestamp('fim_preenchimento')->nullable();
            $table->string('status')->default('pendente'); // pendente, enviado
            $table->timestamps();
        });

        Schema::create('questionario_pergunta_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_resposta_id')->constrained('questionario_respostas')->onDelete('cascade');
            $table->foreignId('questionario_pergunta_id')->constrained('questionario_perguntas')->onDelete('cascade');
            $table->text('resposta_texto')->nullable();
            $table->json('resposta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('questionario_alvos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_id')->constrained('questionarios')->onDelete('cascade');
            $table->string('alvo_type'); // Unidade, Curso, Serie, Turma, Role, User
            $table->unsignedBigInteger('alvo_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questionario_alvos');
        Schema::dropIfExists('questionario_pergunta_respostas');
        Schema::dropIfExists('questionario_respostas');
        Schema::dropIfExists('questionario_perguntas');
        Schema::dropIfExists('questionario_blocos');
        Schema::dropIfExists('questionarios');
    }
};
