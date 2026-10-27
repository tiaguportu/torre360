<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusReuniao;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\Reuniao;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reuniões realizadas cuja ata ainda não foi fechada (Ondas 27 e 32), para quem as organiza e para a coordenação (menos as sigilosas
 * dos outros). Atrasada é a ata em aberto há mais dias do que `config('reunioes.ata_prazo_dias')`.
 */
final class AtasPorFechar implements FontePendencia
{
    public function chave(): string
    {
        return 'atas_por_fechar';
    }

    public function titulo(): string
    {
        return 'Atas de reunião por fechar';
    }

    public function area(): string
    {
        return 'Acadêmico';
    }

    public function icone(): string
    {
        return 'heroicon-o-document-text';
    }

    public function orientacao(): string
    {
        return 'Registre a presença, escreva a ata (e, no conselho de classe, delibere sobre cada aluno) e feche a ata.';
    }

    public function permissao(): string
    {
        return 'Update:Reuniao';
    }

    public function url(): string
    {
        return ReuniaoResource::getUrl('index');
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)->where('realizada_em', '<', $this->limite())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $prazo = $this->limite();

        return $this->consulta($usuario)
            ->orderBy('realizada_em')
            ->limit($limite)
            ->get()
            ->map(function (Reuniao $reuniao) use ($prazo): ItemPendencia {
                $quando = $reuniao->realizada_em ?? $reuniao->inicio_em;

                return new ItemPendencia(
                    titulo: $reuniao->ehSigilosa() ? $reuniao->tipo->getLabel() : $reuniao->titulo,
                    detalhe: 'Realizada em '.$quando->format('d/m/Y').' · ata em aberto',
                    desde: $quando,
                    url: ReuniaoResource::getUrl('view', ['record' => $reuniao]),
                    atrasado: $quando->lt($prazo),
                );
            })
            ->all();
    }

    private function limite(): CarbonInterface
    {
        return now()->subDays((int) config('reunioes.ata_prazo_dias', 5));
    }

    /**
     * @return Builder<Reuniao>
     */
    private function consulta(User $usuario): Builder
    {
        return Reuniao::query()
            ->visiveisPara($usuario)
            ->where('status', StatusReuniao::Realizada->value)
            ->where(fn (Builder $q) => $q
                ->where('organizador_user_id', $usuario->id)
                ->orWhere(fn (Builder $outras) => $outras
                    ->where('confidencial', false)
                    ->where('tipo', '!=', 'devolutiva')
                    ->when(! $usuario->can('VerTodas:Reuniao'), fn (Builder $b) => $b->whereRaw('1 = 0'))));
    }
}
