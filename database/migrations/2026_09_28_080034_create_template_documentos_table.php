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
        Schema::create('template_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo')->default('declaracao_matricula');
            $table->text('descricao')->nullable();
            $table->longText('cabecalho')->nullable();
            $table->longText('conteudo');
            $table->longText('rodape')->nullable();
            $table->unsignedInteger('validade_dias')->default(30);
            $table->boolean('is_ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_documentos');
    }
};
