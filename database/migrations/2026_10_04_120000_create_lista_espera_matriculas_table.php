<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_espera_matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->foreignId('periodo_letivo_id')->constrained('periodo_letivo')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('interessado_id')->nullable()->constrained('interessado')->nullOnDelete();
            $table->foreignId('interessado_dependente_id')->nullable()->constrained('interessado_dependente')->nullOnDelete();
            $table->string('status')->default('aguardando');
            $table->text('observacoes')->nullable();
            $table->timestamp('notificado_em')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_espera_matriculas');
    }
};
