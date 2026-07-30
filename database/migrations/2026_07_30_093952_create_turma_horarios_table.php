<?php

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
        if (! Schema::hasTable('turma_horario')) {
            Schema::create('turma_horario', function (Blueprint $table) {
                $table->id();
                $table->foreignId('turma_id')->constrained('turma')->onDelete('cascade');
                $table->unsignedTinyInteger('dia_semana'); // 0=Domingo, 1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado
                $table->time('hora_inicio')->nullable();
                $table->time('hora_fim')->nullable();
                $table->timestamps();

                $table->unique(['turma_id', 'dia_semana']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turma_horario');
    }
};
