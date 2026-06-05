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
        // Alterar avaliacao_habilidades
        Schema::table('avaliacao_habilidades', function (Blueprint $table) {
            $table->dropForeign(['matricula_id']);
            $table->dropColumn(['matricula_id', 'conceito', 'observacao']);

            $table->foreignId('turma_id')->after('id')->constrained('turma')->onDelete('cascade');
            $table->foreignId('professor_id')->after('etapa_avaliativa_id')->nullable()->constrained('pessoa')->onDelete('set null');
        });

        // Criar nota_habilidades
        Schema::create('nota_habilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_habilidade_id')->constrained('avaliacao_habilidades')->onDelete('cascade');
            $table->foreignId('matricula_id')->constrained('matricula')->onDelete('cascade');
            $table->string('conceito');
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_habilidades');

        Schema::table('avaliacao_habilidades', function (Blueprint $table) {
            $table->dropForeign(['turma_id']);
            $table->dropForeign(['professor_id']);
            $table->dropColumn(['turma_id', 'professor_id']);

            $table->foreignId('matricula_id')->after('id')->constrained('matricula')->onDelete('cascade');
            $table->string('conceito')->after('etapa_avaliativa_id');
            $table->text('observacao')->nullable()->after('conceito');
        });
    }
};
