<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faltas excessivas e comunicação ao Conselho Tutelar (Onda 57). O caso guarda a apuração no dia em que foi aberto (o
 * número que a direção viu), o registro das tentativas de contato com a família (a prova de que os recursos escolares
 * foram esgotados) e o controle do ofício enviado e da resposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conselhos_tutelares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->string('nome', 160);
            $table->string('municipio', 120)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('endereco', 255)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->boolean('is_ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('casos_frequencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->restrictOnDelete();
            $table->foreignId('periodo_letivo_id')->nullable()->constrained('periodo_letivo')->nullOnDelete();
            $table->string('status', 20)->default('aberto');
            // Apuração do dia em que o caso foi aberto.
            $table->unsignedInteger('aulas_previstas')->default(0);
            $table->unsignedInteger('faltas')->default(0);
            $table->unsignedInteger('faltas_justificadas')->default(0);
            $table->unsignedInteger('limite_aulas')->default(0);
            $table->decimal('percentual_faltas', 5, 2)->default(0);
            $table->foreignId('conselho_tutelar_id')->nullable()->constrained('conselhos_tutelares')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->timestamp('notificado_em')->nullable();
            $table->string('protocolo_externo', 80)->nullable();
            // Criptografado: a resposta do Conselho pode trazer dados da família.
            $table->text('resposta')->nullable();
            $table->timestamp('respondido_em')->nullable();
            $table->timestamp('encerrado_em')->nullable();
            $table->string('encerramento_motivo', 160)->nullable();
            $table->foreignId('aberto_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('notificado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('encerrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['matricula_id', 'status'], 'idx_caso_frequencia_matricula_status');
            $table->index('status', 'idx_caso_frequencia_status');
        });

        Schema::create('contatos_caso_frequencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_frequencia_id')->constrained('casos_frequencia')->cascadeOnDelete();
            $table->date('data');
            $table->string('meio', 20);
            $table->string('resultado', 20);
            // Criptografado: o que a família disse sobre o motivo das faltas.
            $table->text('observacao')->nullable();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contatos_caso_frequencia');
        Schema::dropIfExists('casos_frequencia');
        Schema::dropIfExists('conselhos_tutelares');
    }
};
