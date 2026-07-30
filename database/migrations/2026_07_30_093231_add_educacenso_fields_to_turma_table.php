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
            if (! Schema::hasColumn('turma', 'codigo')) {
                $table->string('codigo')->nullable()->after('nome');
            }
            if (! Schema::hasColumn('turma', 'tipo_mediacao_didatico_pedagogica')) {
                $table->unsignedTinyInteger('tipo_mediacao_didatico_pedagogica')->default(1)->nullable()->after('codigo');
            }
            if (! Schema::hasColumn('turma', 'tipo_turma')) {
                $table->unsignedTinyInteger('tipo_turma')->default(6)->nullable()->after('tipo_mediacao_didatico_pedagogica');
            }
            if (! Schema::hasColumn('turma', 'local_funcionamento_diferenciado')) {
                $table->unsignedTinyInteger('local_funcionamento_diferenciado')->default(0)->nullable()->after('tipo_turma');
            }
            if (! Schema::hasColumn('turma', 'turma_educacao_especial')) {
                $table->boolean('turma_educacao_especial')->default(false)->after('local_funcionamento_diferenciado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turma', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['tipo_mediacao_didatico_pedagogica', 'tipo_turma', 'local_funcionamento_diferenciado', 'turma_educacao_especial'] as $column) {
                if (Schema::hasColumn('turma', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
