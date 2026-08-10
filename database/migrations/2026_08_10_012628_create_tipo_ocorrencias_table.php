<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('categoria')->default('disciplinar');
            $table->string('gravidade')->default('leve');
            $table->boolean('notificar_responsaveis_padrao')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_ocorrencias');
    }
};
