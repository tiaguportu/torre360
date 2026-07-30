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
        Schema::table('pessoa', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->after('id');
            $table->string('codigo_inep', 12)->nullable()->after('identidade');
            $table->string('certidao_nascimento', 32)->nullable()->after('codigo_inep');
            $table->string('filiacao_1', 100)->nullable()->after('certidao_nascimento');
            $table->string('filiacao_2', 100)->nullable()->after('filiacao_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            $table->dropColumn([
                'codigo',
                'codigo_inep',
                'certidao_nascimento',
                'filiacao_1',
                'filiacao_2',
            ]);
        });
    }
};
