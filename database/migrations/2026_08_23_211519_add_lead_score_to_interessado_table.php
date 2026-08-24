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
        Schema::table('interessado', function (Blueprint $table) {
            $table->unsignedTinyInteger('lead_score')->nullable()->after('temperatura');
            $table->dateTime('lead_score_atualizado_em')->nullable()->after('lead_score');
            $table->string('faixa_distancia_escola')->nullable()->after('lead_score_atualizado_em');
            $table->string('meio_transporte')->nullable()->after('faixa_distancia_escola');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->dropColumn(['lead_score', 'lead_score_atualizado_em', 'faixa_distancia_escola', 'meio_transporte']);
        });
    }
};
