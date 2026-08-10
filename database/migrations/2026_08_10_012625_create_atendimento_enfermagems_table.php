<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_enfermagems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('atendido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('data_hora');
            $table->text('sintomas_queixa');
            $table->text('procedimento_realizado');
            $table->string('medicamento_ministrado')->nullable();
            $table->boolean('notificado_responsaveis')->default(false);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_enfermagems');
    }
};
