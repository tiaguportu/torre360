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
        Schema::create('questionario_responsaveis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_id')->constrained('questionarios')->cascadeOnDelete();
            $table->morphs('responsavel'); // responsavel_id, responsavel_type (User / Role)
            $table->enum('nivel', ['dono', 'observador']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questionario_responsaveis');
    }
};
