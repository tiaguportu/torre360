<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove a tabela singular legada que está em duplicidade
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('habilidade');
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não é necessário restaurar a duplicidade
    }
};
