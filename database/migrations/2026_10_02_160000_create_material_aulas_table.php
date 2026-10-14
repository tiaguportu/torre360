<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplina')->cascadeOnDelete();
            $table->foreignId('professor_id')->nullable()->constrained('pessoa')->nullOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('tipo');
            $table->string('arquivo_path')->nullable();
            $table->string('url')->nullable();
            $table->date('data_publicacao');
            $table->boolean('visivel')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_aulas');
    }
};
