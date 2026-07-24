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
        Schema::create('recurso_acessabilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('categoria_recurso_acessabilidade_id')
                ->constrained('categoria_recurso_acessabilidades', 'id', 'fk_ra_categoria')
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
        Schema::dropIfExists('recurso_acessabilidades');
    }
};
