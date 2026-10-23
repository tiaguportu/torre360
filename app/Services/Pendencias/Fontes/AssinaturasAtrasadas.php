<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusAssinante;
use App\Enums\StatusDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Models\DocumentoAssinatura;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Documentos já enviados para assinatura eletrônica (Onda 69) que esperam há muito tempo ou cujo prazo está acabando.
 * Todo item desta fila é, por definição, atrasado: a escola precisa cobrar quem não assinou.
 */
final class AssinaturasAtrasadas implements FontePendencia
{
    public function chave(): string
    {
        return 'assinaturas_atrasadas';
    }

    public function titulo(): string
    {
        return 'Assinaturas atrasadas';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-clock';
    }

    public function orientacao(): string
    {
        return 'Veja quem ainda não assinou e cobre a pessoa; o link dela está em "Links de assinatura". Se o documento perdeu o sentido, cancele.';
    }

    public function permissao(): string
    {
        return 'View:DocumentoAssinatura';
    }

    public function url(): string
    {
        return DocumentoAssinaturaResource::getUrl('index', ['filters[status][values][0]' => StatusDocumentoAssinatura::Enviado->value]);
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
            ->withCount([
                'assinantes as assinantes_total',
                'assinantes as assinantes_assinaram' => fn (Builder $assinantes) => $assinantes->where('status', StatusAssinante::Assinou->value),
            ])
            ->orderBy('enviado_em')
            ->limit($limite)
            ->get()
            ->map(fn (DocumentoAssinatura $documento): ItemPendencia => new ItemPendencia(
                titulo: $documento->titulo,
                detalhe: 'Enviado em '.$documento->enviado_em?->format('d/m/Y').' · '.$documento->assinantes_assinaram.' de '.$documento->assinantes_total.' assinaram'
                    .($documento->expira_em ? ' · prazo até '.$documento->expira_em->format('d/m/Y') : ''),
                desde: $documento->enviado_em,
                url: DocumentoAssinaturaResource::getUrl('view', ['record' => $documento]),
                atrasado: true,
            ))
            ->all();
    }

    /**
     * Enviado há mais de N dias, ou com o prazo terminando em poucos dias.
     *
     * @return Builder<DocumentoAssinatura>
     */
    private function consulta(User $usuario): Builder
    {
        $consulta = DocumentoAssinatura::query()
            ->where('status', StatusDocumentoAssinatura::Enviado->value)
            ->where(fn (Builder $quando) => $quando
                ->where('enviado_em', '<=', $this->limiteDoEnvio())
                ->orWhere(fn (Builder $prazo) => $prazo->whereNotNull('expira_em')->whereDate('expira_em', '<=', $this->limiteDoPrazo()->toDateString())));

        return $usuario->can('View:ProcessoFuncionario') ? $consulta : $consulta->semRh();
    }

    private function limiteDoEnvio(): CarbonInterface
    {
        return now()->subDays((int) config('mesa_pendencias.assinatura_atraso_dias', 7));
    }

    private function limiteDoPrazo(): CarbonInterface
    {
        return today()->addDays((int) config('mesa_pendencias.assinatura_prazo_dias', 3));
    }
}
