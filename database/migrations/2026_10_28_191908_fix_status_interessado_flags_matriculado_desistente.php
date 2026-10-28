<?php

use App\Services\ContadoresCrm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('status_interessado')
            ->whereIn(DB::raw('LOWER(TRIM(nome))'), [
                'matriculado',
                'matriculada',
                'matrícula realizada',
                'matricula realizada',
                'ganho',
            ])
            ->update([
                'is_final' => true,
                'is_ganho' => true,
            ]);

        DB::table('status_interessado')
            ->whereIn(DB::raw('LOWER(TRIM(nome))'), [
                'desistente',
                'desistência',
                'desistencia',
                'perdido',
                'perda',
                'cancelado',
                'sem interesse',
                'descarte',
                'não matriculado',
                'nao matriculado',
            ])
            ->update([
                'is_final' => true,
                'is_ganho' => false,
            ]);

        if (class_exists(ContadoresCrm::class)) {
            ContadoresCrm::invalidar();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (class_exists(ContadoresCrm::class)) {
            ContadoresCrm::invalidar();
        }
    }
};
