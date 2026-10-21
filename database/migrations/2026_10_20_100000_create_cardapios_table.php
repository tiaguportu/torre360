<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardapios', function (Blueprint $table): void {
            $table->id();
            $table->date('data');
            $table->string('tipo_refeicao');
            $table->text('descricao');
            // Alérgenos declarados da refeição (lista de valores de `Alergeno`).
            $table->json('alergenos')->nullable();
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            // `unidade_id` ou 0: permite o índice único tratar "todas as unidades" como um valor (NULL não colide).
            $table->unsignedBigInteger('unidade_chave')->default(0);
            $table->boolean('publicado')->default(false);
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['data', 'tipo_refeicao', 'unidade_chave'], 'uq_cardapio_data_tipo_unidade');
            $table->index(['publicado', 'data'], 'idx_cardapio_publicado_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardapios');
    }
};
