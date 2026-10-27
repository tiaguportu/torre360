<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\Unidade;
use App\Services\RentabilidadeTurmaService;
use App\Support\HelpContent;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Resultado realizado por turma, série, curso e unidade (Onda 77). Complementa a Controladoria de Turmas (que é o planejado, com os
 * custos digitados na turma): aqui tudo vem do que aconteceu no mês. O critério do rateio dos custos indiretos aparece ao lado dos
 * números, porque o rateio é uma escolha da escola, não um fato contábil.
 */
class RentabilidadeRealizada extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 26;

    protected static ?string $title = 'Resultado Realizado por Turma';

    protected static ?string $navigationLabel = 'Resultado Realizado';

    protected static ?string $slug = 'financeiro/resultado-realizado-turmas';

    protected string $view = 'filament.pages.rentabilidade-realizada';

    #[Url(as: 'mes')]
    public string $mes = '';

    #[Url(as: 'criterio')]
    public string $criterio = 'aluno';

    #[Url(as: 'visao')]
    public string $visao = 'turma';

    #[Url(as: 'unidade')]
    public ?int $unidadeId = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $memo = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:RentabilidadeRealizada');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // Por padrão, o último mês completo: o mês corrente ainda não tem despesa nem apuração fechadas.
        $this->mes = preg_match('/^\d{4}-\d{2}$/', $this->mes) ? $this->mes : CarbonImmutable::today()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        $this->criterio = array_key_exists($this->criterio, RentabilidadeTurmaService::CRITERIOS) ? $this->criterio : 'aluno';
        $this->visao = in_array($this->visao, ['turma', 'serie', 'curso', 'unidade'], true) ? $this->visao : 'turma';
    }

    /**
     * @return array<string, string>
     */
    public function opcoesDeMes(): array
    {
        $opcoes = [];
        $mes = CarbonImmutable::today()->startOfMonth();

        for ($i = 0; $i < 18; $i++) {
            $opcoes[$mes->format('Y-m')] = RentabilidadeTurmaService::rotuloDoMes($mes).($i === 0 ? ' (em andamento)' : '');
            $mes = $mes->subMonthNoOverflow();
        }

        return $opcoes;
    }

    /**
     * @return array<string, string>
     */
    public function criterios(): array
    {
        return RentabilidadeTurmaService::CRITERIOS;
    }

    /**
     * @return array<int, string>
     */
    public function unidades(): array
    {
        abort_unless(static::canAccess(), 403);

        return Unidade::query()->orderBy('nome')->pluck('nome', 'id')->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function resultado(): array
    {
        abort_unless(static::canAccess(), 403);

        if ($this->memo !== null) {
            return $this->memo;
        }

        [$ano, $mes] = array_map('intval', explode('-', $this->mes));
        $resultado = app(RentabilidadeTurmaService::class)->comparar($ano, max(1, min(12, $mes)), $this->criterio, $this->unidadeId ?: null);

        if ($this->visao !== 'turma') {
            $resultado['grupos'] = app(RentabilidadeTurmaService::class)->agrupar($resultado['linhas'], $this->visao);
        }

        return $this->memo = $resultado;
    }

    public function updated(string $propriedade): void
    {
        $this->memo = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Resultado realizado', HelpContent::make('📊', 'Resultado realizado por turma', 'Quanto cada turma rendeu e custou de verdade no mês, ao lado do que estava planejado.')
                ->passos('🔁 Como ler', [
                    'Receita: as faturas com vencimento no mês, já líquidas de bolsa e desconto, com o quanto foi pago e o quanto está em atraso.',
                    'Custo docente: a apuração de hora-aula do professor, dividida entre as turmas pela proporção das aulas dele no cronograma.',
                    'Custo direto: despesas do mês no centro de custo da turma (ou do curso, dividido entre as turmas pelo número de alunos).',
                    'Custo indireto (administração, aluguel, limpeza): rateado pelo critério escolhido no alto da tela.',
                ])
                ->secao('📌 Bom saber', [
                    ['⚖️', 'O rateio é uma escolha', 'Cada critério dá uma margem diferente. O número vale para decidir, não é a contabilidade oficial.'],
                    ['🏷️', 'Classifique as despesas', 'Despesa sem conta do plano não entra na conta (pode ser transferência). O aviso mostra quanto ficou de fora.'],
                    ['🔗', 'Centros de custo', 'Vincule o centro de custo à turma ou ao curso em Centros de Custo; marque "fora do resultado das turmas" para folha de professores e investimentos.'],
                    ['📅', 'Mês em andamento', 'Ainda não tem despesas e apuração completas: prefira o último mês fechado.'],
                ])),
        ];
    }
}
