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
        Schema::table('instituicao_ensinos', function (Blueprint $table) {
            $table->string('codigo_inep')->nullable()->after('cnpj');
        });

        Schema::table('unidade', function (Blueprint $table) {
            if (Schema::hasColumn('unidade', 'flag_ativo')) {
                $table->dropColumn('flag_ativo');
            }

            $table->string('codigo_inep')->nullable()->after('cnpj');
            $table->string('situacao_funcionamento')->default('1')->comment('1-Em atividade, 2-Paralisada, 3-Extinta')->after('codigo_inep');
            $table->string('telefone')->nullable()->after('situacao_funcionamento');
            $table->string('email')->nullable()->after('telefone');
            $table->string('codigo_orgao_regional_ensino')->nullable()->after('email');
            $table->string('localizacao_zona')->nullable()->comment('1-Urbana, 2-Rural')->after('codigo_orgao_regional_ensino');
            $table->string('localizacao_diferenciada')->nullable()->comment('1-Área de assentamento, 2-Terra indígena, 3-Comunidade quilombola, 7-Não está em área de localização diferenciada, 8-Área onde se localizam povos e comunidades tradicionais')->after('localizacao_zona');
            $table->string('dependencia_administrativa')->nullable()->comment('1-Federal, 2-Estadual, 3-Municipal, 4-Privada')->after('localizacao_diferenciada');
            $table->string('orgao_vinculado_escola_publica')->nullable()->after('dependencia_administrativa');
            $table->boolean('flag_secretaria_educacao_mec')->default(false)->after('orgao_vinculado_escola_publica');
            $table->boolean('flag_seguranca_publica_forcas_armadas')->default(false)->after('flag_secretaria_educacao_mec');
            $table->boolean('flag_secretaria_saude')->default(false)->after('flag_seguranca_publica_forcas_armadas');
            $table->boolean('flag_outro_orgao_publico')->default(false)->after('flag_secretaria_saude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instituicao_ensinos', function (Blueprint $table) {
            $table->dropColumn('codigo_inep');
        });

        Schema::table('unidade', function (Blueprint $table) {
            $table->boolean('flag_ativo')->default(true);
            $table->dropColumn([
                'codigo_inep',
                'situacao_funcionamento',
                'telefone',
                'email',
                'codigo_orgao_regional_ensino',
                'localizacao_zona',
                'localizacao_diferenciada',
                'dependencia_administrativa',
                'orgao_vinculado_escola_publica',
                'flag_secretaria_educacao_mec',
                'flag_seguranca_publica_forcas_armadas',
                'flag_secretaria_saude',
                'flag_outro_orgao_publico',
            ]);
        });
    }
};
