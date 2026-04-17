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
        Schema::create('representante_unidade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidade')->onDelete('cascade');
            $table->foreignId('pessoa_id')->constrained('pessoa')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('representante_unidade');
    }
};
