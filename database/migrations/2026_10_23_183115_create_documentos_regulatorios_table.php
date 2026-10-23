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
        Schema::create('documentos_regulatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->string('tipo_documento', 50)->index();
            $table->string('nome');
            $table->string('numero_registro', 100)->nullable();
            $table->string('orgao_emissor', 150)->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_validade')->nullable()->index();
            $table->unsignedInteger('dias_antecedencia_alerta')->default(30);
            $table->string('responsavel_renovacao', 150)->nullable();
            $table->string('arquivo_path')->nullable();
            $table->string('status', 30)->default('vigente')->index();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_regulatorios');
    }
};
