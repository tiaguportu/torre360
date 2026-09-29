<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cronograma_aula', function (Blueprint $table) {
            if (! Schema::hasColumn('cronograma_aula', 'dever_casa')) {
                $table->text('dever_casa')->nullable()->after('conteudo_ministrado');
            }
            if (! Schema::hasColumn('cronograma_aula', 'anexo_material')) {
                $table->json('anexo_material')->nullable()->after('dever_casa');
            }
        });

        $tabelaHabilidade = Schema::hasTable('habilidade') ? 'habilidade' : 'habilidades';

        if (! Schema::hasTable('cronograma_aula_habilidade')) {
            Schema::create('cronograma_aula_habilidade', function (Blueprint $table) use ($tabelaHabilidade) {
                $table->id();
                $table->foreignId('cronograma_aula_id')->constrained('cronograma_aula')->cascadeOnDelete();
                $table->foreignId('habilidade_id')->constrained($tabelaHabilidade)->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cronograma_aula_habilidade');

        Schema::table('cronograma_aula', function (Blueprint $table) {
            $table->dropColumn(['dever_casa', 'anexo_material']);
        });
    }
};
