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
        Schema::create('visita_interessado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interessado_id')->constrained('interessado')->cascadeOnDelete();
            $table->foreignId('interessado_dependente_id')->nullable()->constrained('interessado_dependente')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('data_hora');
            $table->string('status')->default('agendada');
            $table->text('observacoes')->nullable();
            $table->dateTime('lembrete_enviado_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'data_hora']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visita_interessado');
    }
};
