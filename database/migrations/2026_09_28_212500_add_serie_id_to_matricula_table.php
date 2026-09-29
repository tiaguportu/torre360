<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('matricula', function (Blueprint $table) {
            if (! Schema::hasColumn('matricula', 'serie_id')) {
                $table->foreignId('serie_id')->nullable()->after('turma_id')->constrained('serie')->nullOnDelete();
            }
        });

        // Preenchimento retroativo da serie_id com base na turma existente
        try {
            DB::statement('
                UPDATE matricula 
                SET serie_id = (SELECT serie_id FROM turma WHERE turma.id = matricula.turma_id)
                WHERE matricula.turma_id IS NOT NULL AND matricula.serie_id IS NULL
            ');
        } catch (Throwable $e) {
            // Caso ocorra em SQLite em memória durante testes
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matricula', function (Blueprint $table) {
            if (Schema::hasColumn('matricula', 'serie_id')) {
                $table->dropConstrainedForeignId('serie_id');
            }
        });
    }
};
