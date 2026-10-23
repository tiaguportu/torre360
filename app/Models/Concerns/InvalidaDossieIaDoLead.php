<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\CrmIaVendasService;

/**
 * O Dossiê IA do lead fica em cache por alguns minutos (CrmIaVendasService::dossieDoLead). Todo registro que entra no
 * contexto enviado à IA e pertence a um lead (visitas, pesquisa pós-visita, propostas, lista de espera, dependentes,
 * documentos da pré-admissão) usa este trait: ao salvar ou apagar, o dossiê do lead é descartado para não mostrar a
 * situação antiga. O histórico de contatos e o próprio lead fazem o mesmo nos seus `booted()`.
 *
 * O modelo precisa ter a coluna `interessado_id`; registros sem lead (ex.: documento ligado só a uma matrícula) são ignorados.
 */
trait InvalidaDossieIaDoLead
{
    protected static function bootInvalidaDossieIaDoLead(): void
    {
        $descartar = static function (self $registro): void {
            CrmIaVendasService::descartarDossie($registro->getAttribute('interessado_id'));
        };

        static::saved($descartar);
        static::deleted($descartar);
    }
}
