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
        Schema::table('unidade', function (Blueprint $table) {
            $table->dropColumn([
                'orgao_vinculado_escola_publica',
                'flag_secretaria_educacao_mec',
                'flag_seguranca_publica_forcas_armadas',
                'flag_secretaria_saude',
                'flag_outro_orgao_publico',
            ]);
        });

        Schema::table('instituicao_ensinos', function (Blueprint $table) {
            $table->string('orgao_vinculado_escola_publica')->nullable()->after('codigo_inep');
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
            $table->dropColumn([
                'orgao_vinculado_escola_publica',
                'flag_secretaria_educacao_mec',
                'flag_seguranca_publica_forcas_armadas',
                'flag_secretaria_saude',
                'flag_outro_orgao_publico',
            ]);
        });

        Schema::table('unidade', function (Blueprint $table) {
            $table->string('orgao_vinculado_escola_publica')->nullable();
            $table->boolean('flag_secretaria_educacao_mec')->default(false);
            $table->boolean('flag_seguranca_publica_forcas_armadas')->default(false);
            $table->boolean('flag_secretaria_saude')->default(false);
            $table->boolean('flag_outro_orgao_publico')->default(false);
        });
    }
};
