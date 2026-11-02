<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ouvidoria e convivência escolar (Ondas 35 e 63): denúncia anônima ou identificada, com protocolo, código de
 * acompanhamento (só o hash fica no banco), prazo, histórico de movimentações e o comitê que pode lê-la.
 *
 * O texto da denúncia, os envolvidos e a identificação do autor ficam criptografados. Uma denúncia anônima não guarda
 * usuário, IP nem agente de navegador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('denuncias', function (Blueprint $table) {
            $table->id();
            $table->string('protocolo', 32)->unique();
            $table->string('codigo_hash', 64);
            $table->string('categoria', 40);
            $table->text('descricao');
            $table->text('local_data')->nullable();
            $table->text('envolvidos')->nullable();
            $table->boolean('anonima')->default(true);
            $table->text('identificacao')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('recebida');
            $table->boolean('suspeita_violencia')->default(false);
            $table->dateTime('prazo_em')->nullable();
            $table->foreignId('responsavel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('conclusao')->nullable();
            $table->dateTime('encerrada_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'prazo_em']);
            $table->index('categoria');
        });

        Schema::create('denuncia_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('denuncia_id')->constrained('denuncias')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 30);
            $table->text('texto');
            $table->boolean('visivel_ao_autor')->default(false);
            $table->timestamps();

            $table->index(['denuncia_id', 'created_at']);
        });

        Schema::create('denuncia_comites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Em branco: a pessoa acompanha todas as categorias.
            $table->string('categoria', 40)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denuncia_comites');
        Schema::dropIfExists('denuncia_movimentacoes');
        Schema::dropIfExists('denuncias');
    }
};
