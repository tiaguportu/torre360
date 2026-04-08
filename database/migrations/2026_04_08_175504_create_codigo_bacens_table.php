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
        Schema::create('codigo_bacens', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nome_extenso');
            $table->string('nome_reduzido')->nullable();
            $table->string('ispb')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codigo_bacens');
    }
};
