<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_consentimentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('texto_padrao');
            $table->boolean('exige_renovacao_periodica')->default(false);
            $table->unsignedSmallInteger('periodicidade_meses')->nullable();
            $table->boolean('is_ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_consentimentos');
    }
};
