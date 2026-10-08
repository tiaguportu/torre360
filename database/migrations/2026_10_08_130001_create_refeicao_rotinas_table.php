<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refeicao_rotinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registro_rotina_diaria_id')->constrained('registro_rotina_diarias')->cascadeOnDelete();
            $table->string('nome');
            $table->string('quantidade');
            $table->text('observacao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refeicao_rotinas');
    }
};
