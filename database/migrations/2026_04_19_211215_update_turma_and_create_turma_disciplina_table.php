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
            if (! Schema::hasColumn('turma', 'tipo_avaliacao')) {
                $table->enum('tipo_avaliacao', ['notas', 'habilidades', 'hibrido'])->default('notas');
            }
        });

        if (! Schema::hasTable('turma_disciplina')) {
            Schema::create('turma_disciplina', function (Blueprint $table) {
                $table->id();
                $table->foreignId('turma_id')->constrained('turma')->onDelete('cascade');
                $table->foreignId('disciplina_id')->constrained('disciplina')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turma_disciplina');

        Schema::table('turma', function (Blueprint $table) {
            $table->dropColumn('tipo_avaliacao');
        });
    }
};
