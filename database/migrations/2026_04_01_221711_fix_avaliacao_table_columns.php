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
        // Renomear etapa_id para etapa_avaliativa_id se existir
        if (Schema::hasColumn('avaliacao', 'etapa_id')) {
            Schema::table('avaliacao', function (Blueprint $table) {
                $table->renameColumn('etapa_id', 'etapa_avaliativa_id');
            });
        }

        Schema::table('avaliacao', function (Blueprint $table) {
            if (!Schema::hasColumn('avaliacao', 'disciplina_id')) {
                $table->foreignId('disciplina_id')->nullable()->constrained('disciplina')->nullOnDelete();
            }
            if (!Schema::hasColumn('avaliacao', 'turma_id')) {
                $table->foreignId('turma_id')->nullable()->constrained('turma')->nullOnDelete();
            }
            if (!Schema::hasColumn('avaliacao', 'professor_id')) {
                $table->foreignId('professor_id')->nullable()->constrained('pessoa')->nullOnDelete();
            }
            if (!Schema::hasColumn('avaliacao', 'nota_maxima')) {
                $table->decimal('nota_maxima', 5, 2)->default(10.00);
            }
            if (!Schema::hasColumn('avaliacao', 'peso_etapa_avaliativa')) {
                $table->decimal('peso_etapa_avaliativa', 5, 2)->default(1.00);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('avaliacao', function (Blueprint $table) {
            $table->dropColumn(['disciplina_id', 'turma_id', 'professor_id', 'nota_maxima', 'peso_etapa_avaliativa']);
            $table->renameColumn('etapa_avaliativa_id', 'etapa_id');
        });
    }
};
