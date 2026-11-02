<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 87: a proposta comercial passa a ter link público com validade, aceite da família (data, IP e nome de quem aceitou),
 * expiração, aviso de vencimento e a ligação com a matrícula que a converteu. Os estados `AceitaPelaFamilia`, `Convertida` e
 * `Expirada` já existiam no enum, mas nenhum código os atribuía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposta_comercials', function (Blueprint $table) {
            $table->string('token_publico', 64)->nullable()->unique()->after('codigo');
            $table->dateTime('enviada_em')->nullable()->after('validade');
            $table->dateTime('visualizada_em')->nullable()->after('enviada_em');
            $table->unsignedSmallInteger('visualizacoes')->default(0)->after('visualizada_em');
            $table->dateTime('aceita_em')->nullable()->after('visualizacoes');
            $table->string('aceite_nome', 120)->nullable()->after('aceita_em');
            $table->string('aceite_ip', 45)->nullable()->after('aceite_nome');
            $table->dateTime('expirada_em')->nullable()->after('aceite_ip');
            $table->dateTime('avisada_3d_em')->nullable()->after('expirada_em');
            $table->dateTime('avisada_1d_em')->nullable()->after('avisada_3d_em');
            $table->dateTime('convertida_em')->nullable()->after('avisada_1d_em');
            $table->foreignId('matricula_id')->nullable()->after('convertida_em')->constrained('matricula')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proposta_comercials', function (Blueprint $table) {
            $table->dropForeign(['matricula_id']);
            $table->dropUnique(['token_publico']);
            $table->dropColumn([
                'token_publico', 'enviada_em', 'visualizada_em', 'visualizacoes', 'aceita_em', 'aceite_nome', 'aceite_ip',
                'expirada_em', 'avisada_3d_em', 'avisada_1d_em', 'convertida_em', 'matricula_id',
            ]);
        });
    }
};
