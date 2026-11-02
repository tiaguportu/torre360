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
 * Chamados da Central de Atendimento (Onda 83) sem a primeira resposta no prazo ou com a resolução vencida. Todo item desta fila é,
 * por definição, atrasado: a família já passou do tempo que a escola prometeu.
 */
final class ChamadosComPrazoEstourado implements FontePendencia
{
    public function chave(): string
    {
        return 'chamados_prazo_estourado';
    }

    public function titulo(): string
    {
        return 'Chamados com prazo estourado';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-chat-bubble-left-ellipsis';
    }

    public function orientacao(): string
    {
        return 'A família abriu o chamado e o prazo do setor passou. Assuma o chamado e responda; se não for com você, reatribua.';
    }

    public function permissao(): string
    {
        return 'View:AtendimentoChamado';
    }

    public function url(): string
    {
        return AtendimentoChamadoResource::getUrl('index', ['filters[prazo_estourado][isActive]' => 'true']);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->contar($usuario);
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta($usuario)
            ->with(['setor', 'solicitante'])
            ->orderBy('prazo_primeira_resposta_em')
            ->limit($limite)
            ->get()
            ->map(fn (AtendimentoChamado $chamado): ItemPendencia => new ItemPendencia(
                titulo: $chamado->protocolo.($chamado->restrito ? ' · pedido restrito' : ' · '.$chamado->assunto),
                detalhe: ($chamado->setor?->nome ?? 'Sem setor').' · '.($chamado->responsavelAtendimento ? 'com '.$chamado->responsavelAtendimento->name : 'ninguém assumiu'),
                desde: $chamado->created_at,
                url: AtendimentoChamadoResource::getUrl('index', ['tableSearch' => $chamado->protocolo]),
                atrasado: true,
            ))
            ->all();
    }

    /**
     * @return Builder<AtendimentoChamado>
     */
    private function consulta(User $usuario): Builder
    {
        return AtendimentoChamado::query()->comPrazoEstourado()->restritosVisiveisPara($usuario);
    }
}
