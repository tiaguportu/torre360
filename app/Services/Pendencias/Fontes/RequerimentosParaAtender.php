<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Resources\AtendimentoChamados\AtendimentoChamadoResource;
use App\Models\AtendimentoChamado;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Requerimentos da família (Onda 80) e pedidos do titular de dados (Onda 34) que ainda não foram concluídos. Atrasado é o que
 * passou do prazo do tipo (para o pedido do titular, os 15 dias da LGPD).
 */
final class RequerimentosParaAtender implements FontePendencia
{
    public function chave(): string
    {
        return 'requerimentos_para_atender';
    }

    public function titulo(): string
    {
        return 'Requerimentos para atender';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public function orientacao(): string
    {
        return 'Abra o pedido, confira o que a família pediu e use "Concluir pedido": aplicar a mudança ou explicar o indeferimento.';
    }

    public function permissao(): string
    {
        return 'View:AtendimentoChamado';
    }

    public function url(): string
    {
        return AtendimentoChamadoResource::getUrl('index', ['filters[requerimentos][value]' => '1']);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)->where('prazo_resolucao_em', '<', now())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta($usuario)
            ->orderBy('prazo_resolucao_em')
            ->limit($limite)
            ->get()
            ->map(fn (AtendimentoChamado $chamado): ItemPendencia => new ItemPendencia(
                titulo: $chamado->protocolo.' · '.($chamado->tipo()?->nome ?? $chamado->assunto),
                detalhe: ($chamado->prazo_resolucao_em ? 'Prazo até '.$chamado->prazo_resolucao_em->format('d/m/Y') : 'Sem prazo')
                    .($chamado->restrito ? ' · pedido restrito' : ''),
                desde: $chamado->created_at,
                url: AtendimentoChamadoResource::getUrl('index', ['tableSearch' => $chamado->protocolo]),
                atrasado: (bool) $chamado->prazo_resolucao_em?->isPast(),
            ))
            ->all();
    }

    /**
     * @return Builder<AtendimentoChamado>
     */
    private function consulta(User $usuario): Builder
    {
        return AtendimentoChamado::query()
            ->whereNotNull('tipo_requerimento')
            ->whereNull('resultado')
            ->emAberto()
            ->restritosVisiveisPara($usuario);
    }
}
