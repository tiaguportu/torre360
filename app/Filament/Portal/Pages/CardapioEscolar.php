<?php

declare(strict_types=1);

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Models\Cardapio;
use App\Services\CardapioAlergiaService;
use App\Support\HelpContent;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Cardápio da semana no Portal da Família, só com o que foi publicado. Para o aluno selecionado, destaca as refeições
 * cujos alérgenos conflitam com a restrição alimentar registrada na ficha médica DELE (nunca de outro aluno).
 */
class CardapioEscolar extends Page
{
    use HasAjudaAction;
    use InteractsWithMatriculaSelecionada;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cake';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Cardápio';

    protected static ?string $navigationLabel = 'Cardápio';

    protected static ?string $slug = 'cardapio';

    protected string $view = 'filament.portal.pages.cardapio-escolar';

    /** Segunda-feira da semana exibida (Y-m-d). Vem do cliente, então é sempre normalizada. */
    #[Url(as: 'semana')]
    public ?string $semana = null;

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    public function getSegundaDaSemana(): CarbonInterface
    {
        try {
            $data = $this->semana ? Carbon::parse($this->semana) : today();
        } catch (\Throwable) {
            $data = today();
        }

        return $data->copy()->startOfWeek();
    }

    public function semanaAnterior(): void
    {
        $this->semana = $this->getSegundaDaSemana()->subWeek()->toDateString();
    }

    public function proximaSemana(): void
    {
        $this->semana = $this->getSegundaDaSemana()->addWeek()->toDateString();
    }

    public function semanaAtual(): void
    {
        $this->semana = null;
    }

    /**
     * Dias da semana com refeições publicadas para a unidade do aluno, já com os conflitos de restrição.
     *
     * @return list<array{data: CarbonInterface, refeicoes: list<array{cardapio: Cardapio, confirmados: list<string>, possiveis: list<string>}>}>
     */
    public function getDias(): array
    {
        $matricula = $this->getMatriculaSelecionada();

        if (! $matricula) {
            return [];
        }

        $segunda = $this->getSegundaDaSemana();
        $unidadeId = $matricula->turma?->serie?->curso?->unidade_id;
        $ficha = $matricula->pessoa?->fichaMedica;
        $servico = app(CardapioAlergiaService::class);

        $porDia = Cardapio::query()
            ->publicados()
            ->daSemana($segunda)
            ->daUnidade($unidadeId)
            ->get()
            ->sortBy(fn (Cardapio $c): int => $c->tipo_refeicao->ordem())
            ->groupBy(fn (Cardapio $c): string => $c->data->toDateString());

        $dias = [];

        foreach ($porDia->sortKeys() as $data => $refeicoes) {
            $dias[] = [
                'data' => Carbon::parse($data),
                'refeicoes' => $refeicoes->map(function (Cardapio $c) use ($servico, $ficha): array {
                    $conflitos = $servico->conflitosDaFicha($ficha, $c->alergenosEnum());

                    return [
                        'cardapio' => $c,
                        'confirmados' => array_map(fn ($a): string => $a->getLabel(), $conflitos['confirmados']),
                        'possiveis' => array_map(fn ($a): string => $a->getLabel(), $conflitos['possiveis']),
                    ];
                })->values()->all(),
            ];
        }

        return $dias;
    }

    /**
     * A ficha do aluno tem alguma restrição alimentar registrada (estruturada ou em texto)?
     */
    public function alunoTemRestricaoRegistrada(): bool
    {
        $analise = app(CardapioAlergiaService::class)->analisarFicha($this->getMatriculaSelecionada()?->pessoa?->fichaMedica);

        return $analise['confirmados'] !== [] || $analise['tem_texto'];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Cardápio', HelpContent::make('🍽️', 'Cardápio', 'Veja as refeições da semana e os alérgenos de cada uma.')
                ->secao('🎯 O que você pode fazer?', [
                    ['📅', 'Navegar pelas semanas', 'Use os botões para ver a semana anterior, a próxima ou voltar para a atual.'],
                    ['⚠️', 'Alertas de restrição', 'Se o seu filho(a) tem restrição alimentar registrada na ficha médica, as refeições que contêm esse alérgeno aparecem destacadas.'],
                ])
                ->alerta('O alerta compara o cardápio com a ficha médica que a escola tem. Se a restrição do seu filho(a) mudou ou não está registrada, atualize a ficha com a secretaria.')
                ->dica('Em caso de dúvida sobre os ingredientes, fale com a escola antes de a refeição ser servida.')),
            Action::make('semanaAtual')
                ->label('Esta semana')
                ->color('gray')
                ->action('semanaAtual'),
        ];
    }
}
