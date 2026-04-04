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
            // Adicionar coluna via SQL bruto no SQLite
            try { DB::statement("ALTER TABLE matricula ADD COLUMN contrato_id INTEGER"); } catch (\Exception $e) {}

            // Migrar dados existentes de contrato para matricula
            // Tentamos rodar a migração de dados apenas se a coluna antiga existir
            try {
                $contratos = DB::table('contrato')->get();
                foreach ($contratos as $contrato) {
                    if (isset($contrato->matricula_id) && $contrato->matricula_id) {
                        DB::table('matricula')
                            ->where('id', $contrato->matricula_id)
                            ->update(['contrato_id' => $contrato->id]);
                    }
                }
            } catch (\Exception $e) {
                // Silenciamos se houver erro ao buscar a coluna antiga (ex: ela já foi dropada ou nunca existiu)
            }
        } else {
            if (! Schema::hasColumn('matricula', 'contrato_id')) {
                Schema::table('matricula', function (Blueprint $table) {
                    $table->foreignId('contrato_id')->nullable()->constrained('contrato')->onDelete('set null');
                });
            }

            // Migrar dados existentes de contrato.matricula_id para matricula.contrato_id
            if (Schema::hasColumn('contrato', 'matricula_id')) {
                $contratos = DB::table('contrato')->get();
                foreach ($contratos as $contrato) {
                    if ($contrato->matricula_id) {
                        DB::table('matricula')
                            ->where('id', $contrato->matricula_id)
                            ->update(['contrato_id' => $contrato->id]);
                    }
                }

                Schema::table('contrato', function (Blueprint $table) {
                    $table->dropColumn('matricula_id');
                });
            }
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
            Schema::table('contrato', function (Blueprint $table) {
                $table->foreignId('matricula_id')->nullable()->constrained('matricula')->onDelete('cascade');
            });

            // Reverter dados
            $matriculas = DB::table('matricula')->whereNotNull('contrato_id')->get();
            foreach ($matriculas as $matricula) {
                DB::table('contrato')
                    ->where('id', $matricula->contrato_id)
                    ->update(['matricula_id' => $matricula->id]);
            }

            Schema::table('matricula', function (Blueprint $table) {
                $table->dropColumn('contrato_id');
            });
        }
    }
};
