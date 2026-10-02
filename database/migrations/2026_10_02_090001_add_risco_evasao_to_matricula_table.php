<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matricula', function (Blueprint $table) {
            $table->unsignedTinyInteger('risco_evasao_score')->nullable()->after('situacao');
            $table->dateTime('risco_evasao_atualizado_em')->nullable()->after('risco_evasao_score');
        });
    }

    public function down(): void
    {
        Schema::table('matricula', function (Blueprint $table) {
            $table->dropColumn(['risco_evasao_score', 'risco_evasao_atualizado_em']);
        });
    }
};
