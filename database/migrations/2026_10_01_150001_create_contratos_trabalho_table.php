<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada linha é uma vigência do contrato (admissão ou aditivo — ex.: reajuste
     * salarial). Um aditivo é registrado encerrando a vigência atual
     * (`vigencia_fim`) e criando uma nova linha com o novo salário.
     */
    public function up(): void
    {
        Schema::create('contratos_trabalho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->decimal('salario', 10, 2);
            $table->text('motivo_encerramento')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_trabalho');
    }
};
