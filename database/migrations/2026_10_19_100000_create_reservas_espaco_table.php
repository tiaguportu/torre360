<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas_espaco', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sala_id')->constrained('sala')->cascadeOnDelete();
            $table->date('data');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->foreignId('solicitante_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo');
            $table->text('descricao')->nullable();
            $table->foreignId('turma_id')->nullable()->constrained('turma')->nullOnDelete();
            $table->string('status')->default('solicitada');
            $table->foreignId('decidido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidido_em')->nullable();
            $table->text('justificativa_decisao')->nullable();
            $table->timestamps();

            $table->index(['sala_id', 'data', 'status'], 'idx_reserva_sala_data_status');
            $table->index(['solicitante_user_id', 'data'], 'idx_reserva_solicitante_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_espaco');
    }
};
