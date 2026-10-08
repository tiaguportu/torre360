<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_rotina_diarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->date('data');
            $table->string('humor')->nullable();
            $table->time('hora_inicio_soneca')->nullable();
            $table->time('hora_fim_soneca')->nullable();
            $table->text('higiene_observacoes')->nullable();
            $table->text('atividades_dia')->nullable();
            $table->string('foto_path')->nullable();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['matricula_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_rotina_diarias');
    }
};
