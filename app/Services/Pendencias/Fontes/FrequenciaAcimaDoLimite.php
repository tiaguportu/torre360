<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Contracts\GuardaPendenciasEmCache;
use App\Filament\Pages\FaltasExcessivas;
use App\Models\PeriodoLetivo;
use App\Models\User;
use App\Services\FaltasExcessivasService;
use App\Support\Pendencias\ItemPendencia;
use App\Support\Pendencias\PeriodoEmCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Alunos ativos acima do limite de faltas (Onda 57) que ainda não têm caso aberto. A apuração percorre todas as turmas do
 * período, então o resultado fica guardado alguns minutos para todos os usuários (a contagem não depende de quem olha).
 */
final class FrequenciaAcimaDoLimite implements FontePendencia, GuardaPendenciasEmCache
{
    /** @var Collection<int, array{nome: string, turma: string, faltas: int, limite: int, percentual: float}>|null */
    private ?Collection $linhas = null;

    private ?PeriodoLetivo $periodo = null;

    private bool $periodoCarregado = false;

    public function chave(): string
    {
        return 'frequencia_acima_do_limite';
    }

    public function titulo(): string
    {
        return 'Alunos acima do limite de faltas';
    }

    public function area(): string
    {
        return 'Acadêmico';
    }

    public function icone(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public function orientacao(): string
    {
        return 'Confira se há falta com atestado para justificar e abra o caso: a LDB manda comunicar o Conselho Tutelar.';
    }

    public function permissao(): string
    {
        return 'Apurar:CasoFrequencia';
    }

    public function url(): string
    {
        return FaltasExcessivas::getUrl();
    }

    public function contar(User $usuario): int
    {
        return $this->semCaso()->count();
    }

    /**
     * É obrigação legal e o aluno já passou do limite: toda a fila está atrasada.
     */
    public function atrasados(User $usuario): int
    {
        return $this->semCaso()->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->semCaso()
            ->take($limite)
            ->map(fn (array $linha): ItemPendencia => new ItemPendencia(
                titulo: $linha['nome'],
                detalhe: "{$linha['turma']} · {$linha['faltas']} falta(s), limite {$linha['limite']} ({$linha['percentual']}% das aulas do ano)",
                url: $this->url(),
                atrasado: true,
            ))
            ->values()
            ->all();
    }

    public function esquecer(): void
    {
        $periodo = $this->periodo();

        if ($periodo) {
            Cache::forget($this->chaveDoCache($periodo));
        }

        $this->linhas = null;
    }

    /**
     * @return Collection<int, array{nome: string, turma: string, faltas: int, limite: int, percentual: float}>
     */
    private function semCaso(): Collection
    {
        if ($this->linhas === null) {
            $periodo = $this->periodo();

            $this->linhas = collect($periodo ? Cache::remember(
                $this->chaveDoCache($periodo),
                max(1, (int) config('mesa_pendencias.selo_cache_segundos', 300)),
                fn (): array => app(FaltasExcessivasService::class)->acimaDoLimite($periodo)
                    ->filter(fn (array $linha): bool => $linha['caso'] === null)
                    ->map(fn (array $linha): array => [
                        'nome' => (string) ($linha['matricula']->pessoa?->nome ?? 'Aluno'),
                        'turma' => (string) $linha['turma']->nome,
                        'faltas' => (int) $linha['faltas'],
                        'limite' => (int) $linha['limite_aulas'],
                        'percentual' => (float) $linha['percentual'],
                    ])
                    ->values()
                    ->all(),
            ) : []);
        }

        return $this->linhas;
    }

    private function chaveDoCache(PeriodoLetivo $periodo): string
    {
        return "mesa-pendencias:frequencia-acima-do-limite:{$periodo->id}";
    }

    private function periodo(): ?PeriodoLetivo
    {
        if (! $this->periodoCarregado) {
            $this->periodo = PeriodoEmCurso::atual();
            $this->periodoCarregado = true;
        }

        return $this->periodo;
    }
}
