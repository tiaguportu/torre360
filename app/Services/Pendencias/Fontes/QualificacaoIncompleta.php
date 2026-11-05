<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Models\Interessado;
use App\Models\InteressadoQualificacao;
use App\Models\User;
use App\Services\Qualificacao\QualificacaoService;
use App\Support\Qualificacao\Roteiro;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lead que a escola já contatou, mas cujo roteiro de qualificação ainda não está completo (SDR): faltam quem decide, o
 * prazo, o motivo da busca ou outro campo do `gate` do roteiro. Sem isso a visita é marcada às cegas e o consultor chega
 * sem saber com quem vai falar. Só entra lead com primeiro contato humano (`data_primeiro_contato`): antes dele a
 * pendência é atender o lead, que as outras filas já mostram.
 */
final class QualificacaoIncompleta extends FilaDeLeads
{
    /** Quantos campos faltantes o detalhe do item cita. */
    private const CAMPOS_CITADOS = 3;

    public function chave(): string
    {
        return 'leads_qualificacao_incompleta';
    }

    public function titulo(): string
    {
        return 'Leads com qualificação incompleta';
    }

    public function icone(): string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public function orientacao(): string
    {
        return 'Abra o lead, vá à aba "Roteiro de qualificação" e preencha o que a família já disse; o que falta aparece em cada item.';
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)
            ->where('interessado.data_primeiro_contato', '<=', now()->subDays($this->configuracao('qualificacao_incompleta_dias', 3)))
            ->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $corte = now()->subDays($this->configuracao('qualificacao_incompleta_dias', 3));
        $servico = app(QualificacaoService::class);

        return $this->comRelacoes($this->consulta($usuario))
            ->with(['dependentes', 'qualificacao'])
            ->orderBy('interessado.data_primeiro_contato')
            ->limit($limite)
            ->get()
            ->map(fn (Interessado $lead) => $this->item(
                $lead,
                $this->detalhe($servico->avaliar($lead)['faltando']),
                $lead->data_primeiro_contato->lte($corte),
                $lead->data_primeiro_contato,
            ))
            ->all();
    }

    /**
     * @return Builder<Interessado>
     */
    private function consulta(User $usuario): Builder
    {
        return $this->base($usuario)
            ->whereNotNull('interessado.data_primeiro_contato')
            ->whereDoesntHave('qualificacao', fn (Builder $q) => $q->where('resultado', InteressadoQualificacao::RESULTADO_QUALIFICADO));
    }

    /**
     * @param  list<string>  $faltando
     */
    private function detalhe(array $faltando): string
    {
        if ($faltando === []) {
            return 'Roteiro completo, aguardando a reavaliação';
        }

        $rotulos = array_map(fn (string $chave): string => Roteiro::campo($chave)?->rotulo ?? $chave, $faltando);
        $citados = array_slice($rotulos, 0, self::CAMPOS_CITADOS);
        $restantes = count($rotulos) - count($citados);

        return 'Falta: '.implode(', ', $citados).($restantes > 0 ? " e mais {$restantes}" : '');
    }
}
