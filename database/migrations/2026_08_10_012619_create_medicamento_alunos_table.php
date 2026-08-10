<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicamento_alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_medica_id')->constrained('ficha_medicas')->cascadeOnDelete();
            $table->string('nome_medicamento');
            $table->string('dosagem')->nullable();
            $table->string('horario_administracao')->nullable();
            $table->text('instrucoes')->nullable();
            $table->boolean('autorizado_responsaveis')->default(true);
            $table->string('arquivo_receita_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicamento_alunos');
    }
};
