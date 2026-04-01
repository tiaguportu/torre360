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
        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endereco_id')->nullable()->constrained('enderecos')->onDelete('set null');
            $table->foreignId('naturalidade_id')->nullable()->constrained('cidades')->onDelete('set null');
            $table->foreignId('nacionalidade_id')->nullable()->constrained('paises')->onDelete('set null');
            $table->string('nome');
            $table->string('cpf', 14)->unique()->nullable();
            $table->string('raca_cor')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }
};
