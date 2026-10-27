<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Próxima ação do lead (Onda 84): todo lead em andamento precisa ter dito, na última vez que a escola falou com ele, QUANDO e
 * O QUE vem depois. O "quando" é `data_proximo_contato` (que já alimenta a aba *Precisa de contato*, a régua e os alertas); o "o
 * que" é `proxima_acao`. Lead sem isso cai na fila *Sem próxima ação* da Mesa do consultor.
 *
 * Só encerra a obrigação o resultado que fecha o ciclo: matrícula feita ou família sem interesse.
 */
class ProximaAcaoLeadService
{
    /** Resultados de contato após os quais não há próxima ação a marcar. */
    public const RESULTADOS_QUE_ENCERRAM = ['matriculou', 'sem_interesse'];

    public const TAMANHO_MAXIMO = 160;

    public function exigidaPara(?string $resultado): bool
    {
        return ! in_array($resultado, self::RESULTADOS_QUE_ENCERRAM, true);
    }

    /**
     * O tipo de contato é um registro que o sistema grava por conta própria (movimentação no funil, reenvio do formulário do site)?
     * Não é uma conversa com a família: não pede nem muda a próxima ação.
     */
    public function tipoAdministrativo(mixed $tipoContatoId): bool
    {
        return filled($tipoContatoId)
            && TipoContatoInteressado::query()->whereKey($tipoContatoId)->whereIn('nome', TipoContatoInteressado::ADMINISTRATIVOS)->exists();
    }

    /**
     * A próxima ação é obrigatória neste contato? Não é, no registro administrativo nem no resultado que encerra o ciclo.
     */
    public function exigidaParaContato(?string $resultado, mixed $tipoContatoId): bool
    {
        return ! $this->tipoAdministrativo($tipoContatoId) && $this->exigidaPara($resultado);
    }

    /**
     * Marca a próxima ação do lead. Resultado que encerra o ciclo limpa o que estava marcado.
     *
     * @throws \DomainException
     */
    public function definir(Interessado $lead, ?CarbonInterface $quando, ?string $acao, ?string $resultado = null): Interessado
    {
        if (! $this->exigidaPara($resultado)) {
            $lead->update(['data_proximo_contato' => null, 'proxima_acao' => null]);

            return $lead;
        }

        if ($quando === null) {
            throw new \DomainException('Informe quando será o próximo contato.');
        }

        // Folga de 5 minutos para o relógio do navegador: marcar "agora" a partir de um formulário aberto há pouco vale.
        if (Carbon::instance($quando)->lt(now()->subMinutes(5))) {
            throw new \DomainException('O próximo contato precisa ser agora ou no futuro.');
        }

        $acao = trim((string) $acao);

        $lead->update([
            'data_proximo_contato' => $quando,
            'proxima_acao' => $acao === '' ? null : mb_substr($acao, 0, self::TAMANHO_MAXIMO),
        ]);

        return $lead;
    }

    /**
     * O lead em andamento está sem próxima ação marcada?
     */
    public function faltaProximaAcao(Interessado $lead): bool
    {
        $lead->loadMissing('status');

        return ! ($lead->status?->is_final ?? false) && $lead->data_proximo_contato === null;
    }
}
