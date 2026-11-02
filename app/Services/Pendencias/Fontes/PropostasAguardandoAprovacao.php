<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusPropostaComercial;
use App\Filament\Resources\PropostaComercials\PropostaComercialResource;
use App\Models\PropostaComercial;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Support\Collection;

/**
 * Propostas com desconto esperando aprovação (Onda 87), só as que a alçada do usuário cobre: o consultor não vê a fila de
 * quem aprova desconto acima da alçada dele. Atrasada é a que espera há mais de 2 dias.
 */
final class PropostasAguardandoAprovacao implements FontePendencia
{
    private const DIAS_ATE_ATRASAR = 2;

    public function chave(): string
    {
        return 'propostas_aguardando_aprovacao';
    }

    public function titulo(): string
    {
        return 'Propostas para aprovar';
    }

    public function area(): string
    {
        return 'Comercial';
    }

    public function icone(): string
    {
        return 'heroicon-o-check-badge';
    }

    public function orientacao(): string
    {
        return 'O consultor pediu um desconto acima da alçada dele. Aprove, recuse com o motivo ou proponha outra condição.';
    }

    public function permissao(): string
    {
        return 'ViewAny:PropostaComercial';
    }

    public function url(): string
    {
        return PropostaComercialResource::getUrl('index', ['filters[status][value]' => StatusPropostaComercial::AguardandoAprovacao->value]);
    }

    public function contar(User $usuario): int
    {
        return $this->doUsuario($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->doUsuario($usuario)->filter(fn (PropostaComercial $p): bool => $p->updated_at->lte(now()->subDays(self::DIAS_ATE_ATRASAR)))->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->doUsuario($usuario)
            ->take($limite)
            ->map(fn (PropostaComercial $proposta): ItemPendencia => new ItemPendencia(
                titulo: $proposta->codigo.' · '.$proposta->responsavel_nome,
                detalhe: 'Desconto de '.number_format((float) $proposta->desconto_solicitado, 1, ',', '.').($proposta->tipo_desconto === 'percentual' ? '%' : ' (valor fixo)').' · alçada: '.$proposta->nivel_alcada_necessario->getLabel(),
                desde: $proposta->updated_at,
                url: PropostaComercialResource::getUrl('index', ['tableSearch' => $proposta->codigo]),
                atrasado: $proposta->updated_at->lte(now()->subDays(self::DIAS_ATE_ATRASAR)),
            ))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, PropostaComercial>
     */
    private function doUsuario(User $usuario): Collection
    {
        return PropostaComercial::query()
            ->where('status', StatusPropostaComercial::AguardandoAprovacao->value)
            ->orderBy('updated_at')
            ->get()
            ->filter(fn (PropostaComercial $proposta): bool => $proposta->podeSerAprovadaPor($usuario))
            ->values();
    }
}
