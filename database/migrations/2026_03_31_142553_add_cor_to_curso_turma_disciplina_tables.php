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
        Schema::table('curso', function (Blueprint $table) {
            $table->string('cor')->nullable()->after('minutos_por_periodo');
        });

        Schema::table('turma', function (Blueprint $table) {
            $table->string('cor')->nullable()->after('periodo_letivo_id');
        });

        Schema::table('disciplina', function (Blueprint $table) {
            $table->string('cor')->nullable()->after('carga_horaria_semanal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curso', function (Blueprint $table) {
            $table->dropColumn('cor');
        });

        Schema::table('turma', function (Blueprint $table) {
            $table->dropColumn('cor');
        });

        Schema::table('disciplina', function (Blueprint $table) {
            $table->dropColumn('cor');
        });
    }
};
