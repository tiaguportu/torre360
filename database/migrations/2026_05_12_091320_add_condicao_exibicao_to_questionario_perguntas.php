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
        Schema::table('questionario_perguntas', function (Blueprint $table) {
            $table->json('condicao_exibicao')->nullable()->after('is_obrigatoria')
                ->comment('Lógica JSON de exibição condicional. Ex: {"pergunta_id":1,"operador":"igual","valor":"Sim"}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questionario_perguntas', function (Blueprint $table) {
            $table->dropColumn('condicao_exibicao');
        });
    }
};
