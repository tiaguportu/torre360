<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusCasoFrequencia;
use App\Filament\Resources\CasosFrequencia\CasoFrequenciaResource;
use App\Models\CasoFrequencia;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Casos de faltas (Onda 57) parados: aberto há dias sem nenhum contato com a família, ou ofício enviado ao Conselho
 * Tutelar e sem resposta registrada há muito tempo. Todo item desta fila é atrasado.
 */
final class CasosDeFrequenciaParados implements FontePendencia
{
    public function chave(): string
    {
        return 'casos_de_frequencia_parados';
    }

    public function titulo(): string
    {
        return 'Casos de faltas parados';
    }

    public function area(): string
    {
        return 'Acadêmico';
    }

    public function icone(): string
    {
        return 'heroicon-o-shield-exclamation';
    }

    public function orientacao(): string
    {
        return 'Procure a família e registre o contato; no caso já enviado ao Conselho, cobre e registre a resposta ou encerre.';
    }

    public function permissao(): string
    {
        return 'Update:CasoFrequencia';
    }

    public function url(): string
    {
        return CasoFrequenciaResource::getUrl('index', ['filters[status][values][0]' => StatusCasoFrequencia::Aberto->value]);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->contar($usuario);
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta()
            ->with(['matricula.pessoa', 'matricula.turma'])
            ->withCount('contatos')
            ->orderBy('created_at')
            ->limit($limite)
            ->get()
            ->map(function (CasoFrequencia $caso): ItemPendencia {
                $notificado = $caso->status === StatusCasoFrequencia::Notificado;

                return new ItemPendencia(
                    titulo: $caso->matricula?->pessoa?->nome ?? 'Aluno',
                    detalhe: ($caso->matricula?->turma?->nome ?? 'sem turma').' · '.($notificado
                        ? 'ofício enviado em '.$caso->notificado_em?->format('d/m/Y').', sem resposta'
                        : 'aberto em '.$caso->created_at->format('d/m/Y').', sem contato com a família'),
                    desde: $notificado ? $caso->notificado_em : $caso->created_at,
                    url: CasoFrequenciaResource::getUrl('view', ['record' => $caso]),
                    atrasado: true,
                );
            })
            ->all();
    }

    /**
     * @return Builder<CasoFrequencia>
     */
    private function consulta(): Builder
    {
        return CasoFrequencia::query()->where(fn (Builder $quando) => $quando
            ->where(fn (Builder $semContato) => $semContato
                ->where('status', StatusCasoFrequencia::Aberto->value)
                ->where('created_at', '<=', $this->limite('dias_sem_contato', 7)))
            ->orWhere(fn (Builder $semResposta) => $semResposta
                ->where('status', StatusCasoFrequencia::Notificado->value)
                ->whereNull('respondido_em')
                ->where('notificado_em', '<=', $this->limite('dias_sem_resposta', 30))));
    }

    private function limite(string $chave, int $padrao): CarbonInterface
    {
        return now()->subDays((int) config("frequencia_legal.notificacao.{$chave}", $padrao));
    }
}
