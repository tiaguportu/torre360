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
        Schema::create('rematriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_rematricula_id')->constrained('periodo_rematriculas')->cascadeOnDelete();
            $table->foreignId('matricula_origem_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('turma_destino_id')->nullable()->constrained('turma')->nullOnDelete();
            $table->foreignId('serie_destino_id')->nullable()->constrained('serie')->nullOnDelete();
            $table->foreignId('turno_pretendido_id')->nullable()->constrained('turno')->nullOnDelete();
            $table->foreignId('solicitante_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('iniciada');
            $table->foreignId('contrato_id')->nullable()->constrained('contrato')->nullOnDelete();
            $table->foreignId('nova_matricula_id')->nullable()->constrained('matricula')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->dateTime('data_confirmacao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rematriculas');
    }
};
