<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ficha_medicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->unique()->constrained('pessoa')->cascadeOnDelete();
            $table->string('tipo_sanguineo')->nullable();
            $table->boolean('has_alergia_lactose')->default(false);
            $table->boolean('has_alergia_gluten')->default(false);
            $table->boolean('has_alergia_amendoim')->default(false);
            $table->text('outras_alergias_alimentares')->nullable();
            $table->text('observacoes_alimentares')->nullable();
            $table->string('plano_saude')->nullable();
            $table->string('numero_carteira_sus')->nullable();
            $table->string('hospital_preferencia')->nullable();
            $table->text('observacoes_gerais')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha_medicas');
    }
};
