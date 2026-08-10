<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cronograma_aula', function (Blueprint $table) {
            $table->text('dever_casa')->nullable()->after('conteudo_ministrado');
            $table->json('anexo_material')->nullable()->after('dever_casa');
        });

        Schema::create('cronograma_aula_habilidade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cronograma_aula_id')->constrained('cronograma_aula')->cascadeOnDelete();
            $table->foreignId('habilidade_id')->constrained('habilidades')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cronograma_aula_habilidade');

        Schema::table('cronograma_aula', function (Blueprint $table) {
            $table->dropColumn(['dever_casa', 'anexo_material']);
        });
    }
};
