<?php

namespace App\Http\Middleware;

use App\Enums\SituacaoMatricula;
use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\Matricula;
use App\Models\Pessoa;
use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveRole
{
    /**
     * Situações de matrícula que aparecem na barra lateral de responsáveis e alunos.
     *
     * @var array<int, SituacaoMatricula>
     */
    private const SITUACOES_NA_BARRA = [SituacaoMatricula::ATIVA, SituacaoMatricula::PENDENTE, SituacaoMatricula::RESERVA];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Não processa lógica de navegação em rotas de visualização de documentos ou fora do admin
        if ($request->is('visualizar-documento/*') || ! str($request->path())->startsWith('admin')) {
            return $next($request);
        }

        if (auth()->check()) {
            $user = auth()->user();
            $roles = $user->roles->pluck('name')->toArray();
            $activeRole = session('active_role');

            // Se não houver role na sessão ou o role da sessão não pertencer mais ao usuário
            if (! $activeRole || ! in_array($activeRole, $roles)) {
                if (! empty($roles)) {
                    session(['active_role' => $roles[0]]);
                    $activeRole = $roles[0];
                } else {
                    session()->forget('active_role');
                    $activeRole = null;
                }
            }

            // Registra os itens de menu dinamicamente
            Filament::registerUserMenuItems(
                $user->roles->map(
                    fn ($role) => MenuItem::make()
                        ->label("Atuar como: {$role->name}")
                        ->icon($role->name === $activeRole ? 'heroicon-s-check-circle' : 'heroicon-o-arrow-path')
                        ->url(route('switch-role', ['role' => $role->name]))
                        ->visible(fn () => $role->name !== session('active_role'))
                )->toArray()
            );

            // Sobrescreve a navegação se o role ativo for 'responsavel'
            if (in_array($activeRole, ['responsavel', 'aluno']) && ! $user->hasRole('super_admin')) {
                Filament::getCurrentPanel()->navigation(function (NavigationBuilder $builder) use ($user, $activeRole): NavigationBuilder {
                    $pessoa = $user->pessoa;
                    $alunos = $activeRole === 'responsavel'
                        ? ($pessoa?->alunos ?? collect())
                        : collect([$pessoa])->filter();

                    $groups = [
                        NavigationGroup::make('Principal')
                            ->items([
                                ...Dashboard::getNavigationItems(),
                            ]),
                        NavigationGroup::make('Preceptoria')
                            ->items([
                                NavigationItem::make('Agendar preceptoria')
                                    ->icon('heroicon-o-calendar-days')
                                    ->url(fn () => PreceptoriaResource::getUrl('agendar')),
                            ]),
                    ];

                    foreach ($alunos as $aluno) {
                        $matriculas = $this->matriculasDaBarra($aluno);

                        if ($matriculas->isEmpty()) {
                            continue;
                        }

                        $avatarUrl = $aluno->foto
                            ? route('documentos.visualizar', ['path' => $aluno->foto])
                            : 'https://ui-avatars.com/api/?name='.urlencode($aluno->nome).'&color=7F9CF5&background=EBF4FF';

                        // Com mais de uma matrícula do mesmo aluno, os atalhos de cada uma levam o período letivo no rótulo.
                        $rotulos = $matriculas->count() > 1 ? $this->rotulosPorPeriodo($matriculas) : [];

                        $items = [
                            NavigationItem::make('Dados Cadastrais')
                                ->url(fn () => PessoaResource::getUrl('edit', ['record' => $aluno->id])),
                        ];

                        foreach ($matriculas as $matricula) {
                            $sufixo = isset($rotulos[$matricula->id]) ? ' · '.$rotulos[$matricula->id] : '';

                            $items[] = NavigationItem::make('Boletim Escolar'.$sufixo)
                                ->url(fn () => MatriculaResource::getUrl('boletim', ['record' => $matricula->id]))
                                ->visible(fn () => $matricula->notas()->exists());

                            $items[] = NavigationItem::make('Preceptorias'.$sufixo)
                                ->url(fn () => PreceptoriaResource::getUrl('index', [
                                    'filters[matricula][value]' => $matricula->id,
                                ]));

                            $items[] = NavigationItem::make('Documentos'.$sufixo)
                                ->url(fn () => MatriculaResource::getUrl('documentos', ['record' => $matricula->id]))
                                ->badge(fn () => $matricula->getMissingMandatoryDocumentsCount() ?: null, color: 'danger');
                        }

                        $groups[] = NavigationGroup::make($aluno->nome)
                            ->icon(new HtmlString('
                                <div style="width: 2.25rem; height: 2.25rem; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" class="shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                                    <img src="'.$avatarUrl.'" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            '))
                            ->items($items);
                    }

                    return $builder->groups($groups);
                });
            }
        }

        return $next($request);
    }

    /**
     * Matrículas do aluno que a família enxerga na barra lateral: ativa primeiro, depois o período mais recente.
     *
     * @return Collection<int, Matricula>
     */
    private function matriculasDaBarra(Pessoa $aluno): Collection
    {
        return $aluno->matriculas()
            ->whereIn('situacao', self::SITUACOES_NA_BARRA)
            // Além do período, o que o selo de documentos pendentes lê (evita N consultas por matrícula).
            ->with(['periodoLetivo', 'turma.serie.curso.documentos', 'turma.tiposDocumentos', 'tiposDocumentos', 'documentoInseridos'])
            ->get()
            ->sortByDesc(fn (Matricula $matricula): array => [
                $matricula->situacao === SituacaoMatricula::ATIVA ? 1 : 0,
                $matricula->periodoLetivo?->data_inicio?->timestamp ?? 0,
                (int) $matricula->periodo_letivo_id,
                $matricula->id,
            ])
            ->values();
    }

    /**
     * Rótulo de cada matrícula pelo período letivo, com a situação quando não é ativa. Duas matrículas do
     * mesmo período e situação (cursos diferentes) só se distinguem pela turma, que entra no rótulo.
     *
     * @param  Collection<int, Matricula>  $matriculas
     * @return array<int, string>
     */
    private function rotulosPorPeriodo(Collection $matriculas): array
    {
        $rotulos = $matriculas->mapWithKeys(function (Matricula $matricula): array {
            $rotulo = $matricula->periodoLetivo?->nome ?? 'Sem período';

            if ($matricula->situacao !== SituacaoMatricula::ATIVA) {
                $rotulo .= ' ('.$matricula->situacao->getLabel().')';
            }

            return [$matricula->id => $rotulo];
        });

        $repetidos = $rotulos->countBy()->filter(fn (int $quantidade): bool => $quantidade > 1)->keys();

        return $rotulos
            ->map(fn (string $rotulo, int $matriculaId): string => $repetidos->contains($rotulo)
                ? $rotulo.' – '.($matriculas->firstWhere('id', $matriculaId)->turma?->nome ?? 'Sem turma')
                : $rotulo)
            ->all();
    }
}
