<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emprestimos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livro_id')->constrained('livros')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->date('data_emprestimo');
            $table->date('data_prevista_devolucao');
            $table->date('data_devolucao')->nullable();
            $table->string('status')->default('emprestado');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emprestimos');
    }
};
