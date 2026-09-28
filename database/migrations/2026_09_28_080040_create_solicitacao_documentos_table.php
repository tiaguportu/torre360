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
        Schema::create('solicitacao_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('protocolo')->unique();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('template_documento_id')->constrained('template_documentos')->cascadeOnDelete();
            $table->foreignId('solicitado_por_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('atendido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('solicitado');
            $table->text('observacao_solicitante')->nullable();
            $table->text('justificativa_recusa')->nullable();
            $table->string('codigo_verificacao')->unique();
            $table->dateTime('data_solicitacao');
            $table->dateTime('data_emissao')->nullable();
            $table->date('data_validade')->nullable();
            $table->string('arquivo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitacao_documentos');
    }
};
