<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocorrencia_escolars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('tipo_ocorrencia_id')->constrained('tipo_ocorrencias')->cascadeOnDelete();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('data_hora');
            $table->text('descricao');
            $table->text('providencias_tomadas')->nullable();
            $table->boolean('notificar_responsaveis')->default(true);
            $table->timestamp('notificacao_enviada_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocorrencia_escolars');
    }
};
