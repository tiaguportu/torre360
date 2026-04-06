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
        Schema::table('contrato', function (Blueprint $table) {
            if (Schema::hasColumn('contrato', 'matricula_id')) {
                // Remove a chave estrangeira primeiro
                $table->dropForeign(['matricula_id']);
                // Depois a coluna
                $table->dropColumn('matricula_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            $table->foreignId('matricula_id')->nullable()->constrained('matricula')->onDelete('cascade');
        });
    }
};
