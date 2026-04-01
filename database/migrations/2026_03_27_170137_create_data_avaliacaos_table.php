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
        Schema::create('data_avaliacaos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etapa_id')->constrained('etapas')->onDelete('cascade');
            $table->date('data_ocorrencia');
            $table->dateTime('data_limite_lancamento'); // Trava de portal
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_avaliacaos');
    }
};
