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
        Schema::create('necessidade_educacao_especiais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('categoria_necessidade_educacao_especial_id')
                ->constrained('categoria_necessidade_educacao_especiais', 'id', 'fk_nee_categoria')
                ->cascadeOnDelete();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('necessidade_educacao_especiais');
    }
};
