<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O formulário público de captação (`/quero-uma-vaga`) sempre permitiu enviar o
     * interesse sem escolher a série do dependente (validação `nullable` em
     * CaptacaoInteressadoController::rules()), mas o banco exigia `serie_id`
     * (NOT NULL) — o lead (Interessado) era criado, e o INSERT do dependente falhava
     * logo em seguida com erro 500, perdendo o e-mail de agradecimento e o aviso à
     * equipe de captação.
     */
    public function up(): void
    {
        Schema::table('interessado_dependente', function (Blueprint $table) {
            $table->unsignedBigInteger('serie_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('interessado_dependente', function (Blueprint $table) {
            $table->unsignedBigInteger('serie_id')->nullable(false)->change();
        });
    }
};
