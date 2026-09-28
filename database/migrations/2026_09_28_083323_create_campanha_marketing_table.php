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
        Schema::create('campanha_marketing', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('canal')->nullable();
            $table->string('codigo_utm')->nullable()->unique();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->decimal('custo', 12, 2)->default(0);
            $table->boolean('ativa')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campanha_marketing');
    }
};
