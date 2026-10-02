<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_bolsas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->unsignedTinyInteger('percentual_maximo');
            $table->boolean('exige_aprovacao')->default(true);
            $table->text('criterio_renovacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_bolsas');
    }
};
