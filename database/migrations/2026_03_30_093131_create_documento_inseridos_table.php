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
        Schema::create('documento_inserido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_obrigatorio_id')->constrained('documento_obrigatorio')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('situacao_documento_inserido_id')->constrained('situacao_documento_inserido')->restrictOnDelete();
            $table->text('observacoes')->nullable();
            $table->string('arquivo_path');
            $table->string('nome_arquivo_original')->nullable();
            $table->string('hash_arquivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_inserido');
    }
};
