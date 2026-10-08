<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios_acervo', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->string('status')->default('em_andamento'); // em_andamento, concluido
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventario_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained('inventarios_acervo')->cascadeOnDelete();
            $table->foreignId('livro_id')->constrained('livros')->cascadeOnDelete();
            $table->timestamp('bipado_em');
            $table->unsignedInteger('quantidade_conferida')->default(1);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inventario_id', 'livro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_itens');
        Schema::dropIfExists('inventarios_acervo');
    }
};
