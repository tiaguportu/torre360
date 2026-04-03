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
        Schema::table('matricula', function (Blueprint $table) {
            $table->foreignId('contrato_id')->nullable()->constrained('contrato')->onDelete('set null');
        });

        // Migrar dados existentes de contrato.matricula_id para matricula.contrato_id
        $contratos = DB::table('contrato')->get();
        foreach ($contratos as $contrato) {
            DB::table('matricula')
                ->where('id', $contrato->matricula_id)
                ->update(['contrato_id' => $contrato->id]);
        }

        Schema::table('contrato', function (Blueprint $table) {
            $table->dropColumn('matricula_id');
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
};
