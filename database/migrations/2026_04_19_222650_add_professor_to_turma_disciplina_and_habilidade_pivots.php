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
        Schema::table('turma_disciplina', function (Blueprint $table) {
            if (!Schema::hasColumn('turma_disciplina', 'professor_id')) {
                $table->foreignId('professor_id')->nullable()->constrained('pessoa')->onDelete('set null');
            }
        });

        Schema::table('turma_habilidade', function (Blueprint $table) {
            if (!Schema::hasColumn('turma_habilidade', 'professor_id')) {
                $table->foreignId('professor_id')->nullable()->constrained('pessoa')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turma_disciplina', function (Blueprint $table) {
            $table->dropForeign(['professor_id']);
            $table->dropColumn('professor_id');
        });

        Schema::table('turma_habilidade', function (Blueprint $table) {
            $table->dropForeign(['professor_id']);
            $table->dropColumn('professor_id');
        });
    }
};
