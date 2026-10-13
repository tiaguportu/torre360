<?php

declare(strict_types=1);

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
        Schema::create('plano_aula_adaptacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plano_aula_id')->constrained('plano_aula')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->longText('conteudo')->comment('Array encriptado em repouso via cast encrypted:array');
            $table->string('status', 30)->default('rascunho')->comment('rascunho, revisado, aprovado');
            $table->foreignId('gerado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_em')->nullable();
            $table->foreignId('aprovado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->string('modelo_ia', 80)->nullable();
            $table->timestamps();

            $table->unique(['plano_aula_id', 'matricula_id'], 'plano_aula_adaptacao_unica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plano_aula_adaptacoes');
    }
};
