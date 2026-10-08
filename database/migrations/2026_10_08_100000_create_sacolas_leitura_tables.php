<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacolas_leitura', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable()->unique();
            $table->string('titulo');
            $table->foreignId('turma_id')->nullable()->constrained('turma')->nullOnDelete();
            $table->foreignId('responsavel_id')->nullable()->constrained('pessoa')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('data_retirada');
            $table->date('data_prevista_devolucao');
            $table->date('data_devolucao')->nullable();
            $table->string('status')->default('em_circulacao');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('sacola_leitura_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sacola_id')->constrained('sacolas_leitura')->cascadeOnDelete();
            $table->foreignId('livro_id')->constrained('livros')->cascadeOnDelete();
            $table->boolean('devolvido')->default(false);
            $table->dateTime('devolvido_em')->nullable();
            $table->string('observacao_devolucao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacola_leitura_itens');
        Schema::dropIfExists('sacolas_leitura');
    }
};
