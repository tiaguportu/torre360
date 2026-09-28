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
        Schema::create('periodo_rematriculas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->foreignId('periodo_letivo_origem_id')->constrained('periodo_letivo')->cascadeOnDelete();
            $table->foreignId('periodo_letivo_destino_id')->constrained('periodo_letivo')->cascadeOnDelete();
            $table->foreignId('template_contrato_id')->nullable()->constrained('template_contratos')->nullOnDelete();
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->boolean('is_ativo')->default(true);
            $table->text('mensagem_orientacao')->nullable();
            $table->decimal('valor_taxa', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodo_rematriculas');
    }
};
