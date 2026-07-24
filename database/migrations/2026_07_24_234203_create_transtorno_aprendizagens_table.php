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
        Schema::create('transtorno_aprendizagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('categoria_transtorno_aprendizagem_id')
                ->constrained('categoria_transtorno_aprendizagens', 'id', 'fk_ta_categoria')
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
        Schema::dropIfExists('transtorno_aprendizagens');
    }
};
