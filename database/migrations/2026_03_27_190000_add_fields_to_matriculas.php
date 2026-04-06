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
        if (config('database.default') === 'sqlite') {
            // No SQLite antigo, usamos SQL puro por coluna para evitar inspeções de esquema falhas
            try {
                DB::statement('ALTER TABLE matriculas ADD COLUMN pessoa_id INTEGER');
            } catch (Exception $e) {
            }
            try {
                DB::statement('ALTER TABLE matriculas ADD COLUMN turma_id INTEGER');
            } catch (Exception $e) {
            }
            try {
                DB::statement('ALTER TABLE matriculas ADD COLUMN data_matricula DATE');
            } catch (Exception $e) {
            }
            try {
                DB::statement("ALTER TABLE matriculas ADD COLUMN status VARCHAR DEFAULT 'ativa'");
            } catch (Exception $e) {
            }

            try {
                DB::statement('ALTER TABLE turmas ADD COLUMN serie_id INTEGER');
            } catch (Exception $e) {
            }
            try {
                DB::statement('ALTER TABLE turmas ADD COLUMN turno_id INTEGER');
            } catch (Exception $e) {
            }
            try {
                DB::statement('ALTER TABLE turmas ADD COLUMN codigo VARCHAR');
            } catch (Exception $e) {
            }
        } else {
            Schema::table('matriculas', function (Blueprint $table) {
                $table->foreignId('pessoa_id')->nullable()->constrained('pessoas')->onDelete('cascade');
                $table->foreignId('turma_id')->nullable()->constrained('turmas')->onDelete('cascade');
                $table->date('data_matricula')->nullable();
                $table->string('status')->default('ativa');
            });

            Schema::table('turmas', function (Blueprint $table) {
                if (! Schema::hasColumn('turmas', 'serie_id')) {
                    $table->foreignId('serie_id')->nullable()->constrained('series')->onDelete('cascade');
                }
                if (! Schema::hasColumn('turmas', 'turno_id')) {
                    $table->foreignId('turno_id')->nullable()->constrained('turnos')->onDelete('cascade');
                }
                if (! Schema::hasColumn('turmas', 'codigo')) {
                    $table->string('codigo')->nullable();
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
            // SQLite não suporta DROP COLUMN em versões muito antigas,
            // frequentemente é melhor deixar as colunas lá se for rollback de SQLite
        } else {
            Schema::table('matriculas', function (Blueprint $table) {
                $table->dropForeign(['pessoa_id']);
                $table->dropForeign(['turma_id']);
                $table->dropColumn(['pessoa_id', 'turma_id', 'data_matricula', 'status']);
            });

            Schema::table('turmas', function (Blueprint $table) {
                $table->dropForeign(['serie_id']);
                $table->dropForeign(['turno_id']);
                $table->dropColumn(['serie_id', 'turno_id', 'codigo']);
            });
        }
    }
};
