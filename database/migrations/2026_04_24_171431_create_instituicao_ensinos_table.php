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
        Schema::create('instituicao_ensinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endereco_id')->nullable()->constrained('endereco')->nullOnDelete();
            $table->string('nome');
            $table->string('cnpj', 18)->nullable();
            $table->string('logo')->nullable();
            $table->string('celular_whatsapp')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('youtube')->nullable();
            $table->boolean('flag_ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instituicao_ensinos');
    }
};
