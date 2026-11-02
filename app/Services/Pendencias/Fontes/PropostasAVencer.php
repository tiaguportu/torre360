<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusPropostaComercial;
use App\Filament\Resources\PropostaComercials\PropostaComercialResource;
use App\Models\PropostaComercial;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Propostas comerciais (Onda 87) que pedem uma ligação: as aprovadas que vencem em poucos dias e as que a família já aceitou e
 * ainda não viraram matrícula. Consultor vê as próprias; administração e coordenação veem todas.
 */
final class PropostasAVencer implements FontePendencia
{
    private const DIAS_DE_AVISO = 3;

    private const DIAS_DE_ACEITA_PARADA = 3;

    public function chave(): string
    {
        return 'propostas_a_vencer';
    }

    public function titulo(): string
    {
        return 'Propostas a vencer';
    }

    public function area(): string
    {
        return 'Comercial';
    }

    public function icone(): string
    {
        return 'heroicon-o-clock';
    }

    public function orientacao(): string
    {
        return 'Ligue para a família antes de a proposta vencer. Se ela já aceitou, combine a matrícula: o aceite é intenção, o contrato é que vale.';
    }

    public function permissao(): string
    {
        return 'ViewAny:PropostaComercial';
    }

    public function url(): string
    {
        return PropostaComercialResource::getUrl('index');
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $a) => $a->where('status', StatusPropostaComercial::AceitaPelaFamilia->value)->where('aceita_em', '<=', now()->subDays(self::DIAS_DE_ACEITA_PARADA)))
                ->orWhere(fn (Builder $b) => $b->whereIn('status', $this->aprovadas())->whereDate('validade', '<=', today()->addDay()->toDateString())))
            ->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta($usuario)
            ->orderBy('validade')
            ->limit($limite)
            ->get()
            ->map(fn (PropostaComercial $proposta): ItemPendencia => new ItemPendencia(
                titulo: $proposta->codigo.' · '.$proposta->responsavel_nome,
                detalhe: $proposta->status === StatusPropostaComercial::AceitaPelaFamilia
                    ? 'Aceita em '.$proposta->aceita_em?->format('d/m/Y').' · falta matricular'
                    : 'Vence em '.$proposta->validade?->format('d/m/Y'),
                desde: $proposta->aprovado_em ?? $proposta->created_at,
                url: PropostaComercialResource::getUrl('index', ['tableSearch' => $proposta->codigo]),
                atrasado: $proposta->status === StatusPropostaComercial::AceitaPelaFamilia
                    ? (bool) $proposta->aceita_em?->lte(now()->subDays(self::DIAS_DE_ACEITA_PARADA))
                    : (bool) $proposta->validade?->lte(today()->addDay()),
            ))
            ->all();
    }

    /**
     * @return Builder<PropostaComercial>
     */
    private function consulta(User $usuario): Builder
    {
        $consulta = PropostaComercial::query()
            ->where(fn (Builder $q) => $q
                ->where('status', StatusPropostaComercial::AceitaPelaFamilia->value)
                ->orWhere(fn (Builder $a) => $a->whereIn('status', $this->aprovadas())
                    ->whereNotNull('validade')
                    ->whereDate('validade', '<=', today()->addDays(self::DIAS_DE_AVISO)->toDateString())));

        return $usuario->hasAnyRole(['super_admin', 'admin', 'coordenador'])
            ? $consulta
            : $consulta->where('solicitado_por_user_id', $usuario->id);
    }

    /**
     * @return list<string>
     */
    private function aprovadas(): array
    {
        return [StatusPropostaComercial::Aprovada->value, StatusPropostaComercial::AprovadaAutomatica->value];
    }
}
