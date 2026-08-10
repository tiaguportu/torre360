<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contato_emergencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_medica_id')->constrained('ficha_medicas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('parentesco_grau');
            $table->string('telefone_principal');
            $table->string('telefone_secundario')->nullable();
            $table->string('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contato_emergencias');
    }
};
