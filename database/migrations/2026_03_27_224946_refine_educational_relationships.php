<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            // No SQLite antigo, evitamos DROP COLUMN e usamos SQL bruto para ADD
            try { DB::statement("ALTER TABLE avaliacao ADD COLUMN disciplina_id INTEGER"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE avaliacao ADD COLUMN turma_id INTEGER"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE avaliacao ADD COLUMN data_prevista DATE"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE avaliacao ADD COLUMN nota_maxima DECIMAL(5,2)"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE avaliacao ADD COLUMN peso_etapa_avaliativa DECIMAL(5,2)"); } catch (\Exception $e) {}
        } else {
            Schema::table('dia_nao_letivo', function (Blueprint $table) {
                // Tenta remover a chave estrangeira de forma segura para MySQL
                try {
                    $table->dropForeign(['curso_id']);
                } catch (\Exception $e) {
                    // Ignora se a chave não existir ou tiver outro nome
                }
                $table->dropColumn('curso_id');
            });

            Schema::table('etapa_avaliativa', function (Blueprint $table) {
                // Tentando dropar a chave que pode ter vindo da tabela 'etapas'
                try {
                    $table->dropForeign('etapas_turma_id_foreign');
                } catch (\Exception $e) {
                    try {
                        $table->dropForeign(['turma_id']);
                    } catch (\Exception $e2) {
                        // Ignora se não conseguir remover a chave
                    }
                }
                $table->dropColumn('turma_id');
            });

            Schema::table('avaliacao', function (Blueprint $table) {
                if (!Schema::hasColumn('avaliacao', 'disciplina_id')) {
                    $table->foreignId('disciplina_id')->nullable()->constrained('disciplina')->onDelete('cascade');
                }
                if (!Schema::hasColumn('avaliacao', 'turma_id')) {
                    $table->foreignId('turma_id')->nullable()->constrained('turma')->onDelete('cascade');
                }
                if (!Schema::hasColumn('avaliacao', 'data_prevista')) {
                    $table->date('data_prevista')->nullable();
                }
                if (!Schema::hasColumn('avaliacao', 'nota_maxima')) {
                    $table->decimal('nota_maxima', 5, 2)->nullable();
                }
                if (!Schema::hasColumn('avaliacao', 'peso_etapa_avaliativa')) {
                    $table->decimal('peso_etapa_avaliativa', 5, 2)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            // No action
        } else {
            Schema::table('dia_nao_letivo', function (Blueprint $table) {
                $table->foreignId('curso_id')->nullable()->constrained('curso')->onDelete('cascade');
            });

            Schema::table('etapa_avaliativa', function (Blueprint $table) {
                $table->foreignId('turma_id')->nullable()->constrained('turma')->onDelete('cascade');
            });

            Schema::table('avaliacao', function (Blueprint $table) {
                $table->dropForeign(['disciplina_id']);
                $table->dropForeign(['turma_id']);
                $table->dropColumn(['disciplina_id', 'turma_id', 'data_prevista', 'nota_maxima', 'peso_etapa_avaliativa']);
            });
        }
    }
};
