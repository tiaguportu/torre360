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
            if (! Schema::hasColumn('turma', 'carga_horaria_total')) {
                $table->unsignedInteger('carga_horaria_total')->nullable()->after('vagas_maximas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turma', function (Blueprint $table) {
            if (Schema::hasColumn('turma', 'carga_horaria_total')) {
                $table->dropColumn('carga_horaria_total');
            }
        });
    }
};
