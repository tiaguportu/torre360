<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substituicoes_professor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->nullable()->constrained('disciplina')->nullOnDelete();
            $table->foreignId('professor_titular_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('professor_substituto_id')->constrained('pessoa')->cascadeOnDelete();
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substituicoes_professor');
    }
};
