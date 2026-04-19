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
        // Remove a tabela singular legada que está em duplicidade
        Schema::dropIfExists('habilidade');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não é necessário restaurar a duplicidade
    }
};
