<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Teto de 7 dias para os links do Portal de Admissão que já foram enviados.
 *
 * A migração 2026_10_04_210000 deu 90 dias aos tokens já existentes e o código passou a renovar por 90 dias. A
 * regra agora é "revogação máxima de 7 dias" (Interessado::DIAS_VALIDADE_TOKEN_DOCUMENTOS): sem este ajuste, os links
 * antigos continuariam valendo por até 3 meses. Esta migração SÓ REDUZ validades que passam de 7 dias a partir de
 * agora — nunca estende, não apaga tokens e não mexe nos que já vencem antes. As famílias com link em uso ainda têm
 * 7 dias; depois disso a secretaria gera um novo (que também vale 7 dias).
 */
return new class extends Migration
{
    public function up(): void
    {
        $teto = now()->addDays(7);

        DB::table('interessado')
            ->whereNotNull('token_documentos')
            ->where('token_documentos_expira_em', '>', $teto)
            ->update(['token_documentos_expira_em' => $teto]);
    }

    public function down(): void
    {
        // Intencionalmente vazio: não há como (nem por que) restaurar as validades antigas de 90 dias.
    }
};
