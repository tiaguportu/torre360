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
        Schema::dropIfExists('pessoa_perfil');
        Schema::dropIfExists('perfil');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('perfil', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('pessoa_perfil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('perfil_id')->constrained('perfil')->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
