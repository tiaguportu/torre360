<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Pages\MeusEncaminhamentos;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\ReuniaoEncaminhamento;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Encaminhamentos de reunião (Ondas 27 e 32) em aberto com a própria pessoa como responsável. Atrasado é o que passou do prazo.
 */
final class EncaminhamentosDeReuniao implements FontePendencia
{
    public function chave(): string
    {
        return 'encaminhamentos_reuniao';
    }

    public function titulo(): string
    {
        return 'Meus encaminhamentos de reunião';
    }

    public function area(): string
    {
        return 'Acadêmico';
    }

    public function icone(): string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public function orientacao(): string
    {
        return 'Faça o que foi combinado e conclua o encaminhamento, contando em uma linha como foi resolvido.';
    }

    public function permissao(): string
    {
        return 'ViewAny:Reuniao';
    }

    public function url(): string
    {
        return MeusEncaminhamentos::getUrl();
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)->vencidos()->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta($usuario)
            ->with('reuniao')
            ->orderByRaw('prazo is null')
            ->orderBy('prazo')
            ->limit($limite)
            ->get()
            ->map(function (ReuniaoEncaminhamento $encaminhamento): ItemPendencia {
                $reuniao = $encaminhamento->reuniao;

                return new ItemPendencia(
                    titulo: $encaminhamento->descricao,
                    detalhe: ($reuniao->ehSigilosa() ? $reuniao->tipo->getLabel() : $reuniao->titulo)
                        .($encaminhamento->prazo ? ' · prazo '.$encaminhamento->prazo->format('d/m/Y') : ' · sem prazo'),
                    desde: $encaminhamento->prazo ?? $encaminhamento->created_at,
                    url: ReuniaoResource::getUrl('view', ['record' => $reuniao]),
                    atrasado: $encaminhamento->estaVencido(),
                );
            })
            ->all();
    }

    /**
     * @return Builder<ReuniaoEncaminhamento>
     */
    private function consulta(User $usuario): Builder
    {
        return ReuniaoEncaminhamento::query()
            ->abertos()
            ->where('responsavel_user_id', $usuario->id)
            ->whereHas('reuniao', fn (Builder $q) => $q->where('status', '!=', 'cancelada'));
    }
}
