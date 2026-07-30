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
        Schema::table('turma', function (Blueprint $table) {
            if (! Schema::hasColumn('turma', 'etapa_ensino_agregada_id')) {
                $table->foreignId('etapa_ensino_agregada_id')->nullable()->constrained('etapa_ensino_agregada')->nullOnDelete()->after('serie_id');
            }
            if (! Schema::hasColumn('turma', 'etapa_ensino_id')) {
                $table->foreignId('etapa_ensino_id')->nullable()->constrained('etapa_ensino')->nullOnDelete()->after('etapa_ensino_agregada_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turma', function (Blueprint $table) {
            if (Schema::hasColumn('turma', 'etapa_ensino_id')) {
                $table->dropForeign(['etapa_ensino_id']);
                $table->dropColumn('etapa_ensino_id');
            }
            if (Schema::hasColumn('turma', 'etapa_ensino_agregada_id')) {
                $table->dropForeign(['etapa_ensino_agregada_id']);
                $table->dropColumn('etapa_ensino_agregada_id');
            }
        });
    }
};
