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
        Schema::create('categoria_avaliacao_substituicao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categoria_avaliacao')->cascadeOnDelete();
            $table->foreignId('substituida_id')->constrained('categoria_avaliacao')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categoria_avaliacao_substituicao');
    }
};
