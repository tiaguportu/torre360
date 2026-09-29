<?php

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Enums\TipoPublicoComunicacao;
use App\Models\ComunicacaoEmMassa;
use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\Pessoa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolve o público de uma comunicação em massa em uma lista de `Pessoa`
 * únicas, já filtrando quem pediu para não receber comunicações (LGPD) e
 * quem não tem e-mail cadastrado.
 */
class ComunicacaoEmMassaService
{
    /**
     * @return Collection<int, Pessoa>
     */
    public function destinatarios(ComunicacaoEmMassa $comunicacao): Collection
    {
        $filtros = $comunicacao->filtros ?? [];

        $pessoas = match ($comunicacao->tipo_publico) {
            TipoPublicoComunicacao::Interessados => $this->destinatariosInteressados($filtros),
            TipoPublicoComunicacao::ResponsaveisTurma => $this->destinatariosResponsaveisTurma($filtros),
            default => collect(),
        };

        return $pessoas
            ->filter(fn (Pessoa $pessoa): bool => $pessoa->aceita_comunicacao && filled($pessoa->email))
            ->unique('id')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Pessoa>
     */
    private function destinatariosInteressados(array $filtros): Collection
    {
        $interessadoIds = $filtros['interessado_ids'] ?? [];

        $query = Interessado::query()->with('pessoa');

        if ($interessadoIds !== []) {
            $query->whereIn('id', $interessadoIds);
        } else {
            $statusIds = $filtros['status_interessado_ids'] ?? [];
            $origemIds = $filtros['origem_interessado_ids'] ?? [];

            // Nenhum filtro de segmento e nenhuma seleção explícita: por segurança,
            // não devolve "todos os leads" silenciosamente.
            if ($statusIds === [] && $origemIds === []) {
                return collect();
            }

            $query
                ->when($statusIds !== [], fn ($q) => $q->whereIn('status_interessado_id', $statusIds))
                ->when($origemIds !== [], fn ($q) => $q->whereIn('origem_interessado_id', $origemIds));
        }

        return $query->get()
            ->map(fn (Interessado $interessado): ?Pessoa => $interessado->pessoa)
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Pessoa>
     */
    private function destinatariosResponsaveisTurma(array $filtros): Collection
    {
        $turmaIds = $filtros['turma_ids'] ?? [];

        if ($turmaIds === []) {
            return collect();
        }

        $alunoIds = Matricula::query()
            ->whereIn('turma_id', $turmaIds)
            ->where('situacao', SituacaoMatricula::ATIVA->value)
            ->pluck('pessoa_id')
            ->unique();

        if ($alunoIds->isEmpty()) {
            return collect();
        }

        $responsavelIds = DB::table('aluno_responsavel')
            ->whereIn('aluno_id', $alunoIds)
            ->pluck('responsavel_id')
            ->unique();

        return Pessoa::query()->whereIn('id', $responsavelIds)->get();
    }
}
