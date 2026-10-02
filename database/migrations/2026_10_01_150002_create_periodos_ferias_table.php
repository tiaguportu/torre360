<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_ferias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->date('periodo_aquisitivo_inicio');
            $table->date('periodo_aquisitivo_fim');
            $table->unsignedTinyInteger('dias_direito')->default(30);
            $table->unsignedTinyInteger('dias_gozados')->default(0);
            $table->date('data_inicio_gozo')->nullable();
            $table->date('data_fim_gozo')->nullable();
            $table->string('status')->default('pendente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_ferias');
    }
};
