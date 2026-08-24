<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('situacao_final_disciplina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplina')->cascadeOnDelete();
            $table->foreignId('periodo_letivo_id')->constrained('periodo_letivo')->cascadeOnDelete();
            $table->decimal('media_final', 5, 2)->nullable();
            $table->string('situacao')->nullable();
            $table->timestamp('calculado_em')->nullable();
            $table->timestamps();

            $table->unique(['matricula_id', 'disciplina_id', 'periodo_letivo_id'], 'situacao_final_disciplina_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situacao_final_disciplina');
    }
};
