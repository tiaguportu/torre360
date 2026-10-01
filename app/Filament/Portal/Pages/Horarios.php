<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Services\HorarioAlunoService;
use App\Support\HelpContent;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Aulas da semana do aluno (do cronograma da turma), com navegação semanal.
 */
class Horarios extends Page
{
    use HasAjudaAction;
    use InteractsWithMatriculaSelecionada;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Horários';

    protected static ?string $slug = 'horarios';

    protected string $view = 'filament.portal.pages.horarios';

    /**
     * Qualquer data (Y-m-d) dentro da semana exibida; vazio = semana atual.
     */
    #[Url(as: 'semana')]
    public ?string $semana = null;

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    public function getInicioSemana(): Carbon
    {
        try {
            $referencia = $this->semana ? Carbon::createFromFormat('Y-m-d', $this->semana) : now();
        } catch (\Throwable) {
            $referencia = now();
        }

        return HorarioAlunoService::inicioDaSemana($referencia ?: now());
    }

    public function semanaAnterior(): void
    {
        $this->semana = $this->getInicioSemana()->subWeek()->toDateString();
    }

    public function proximaSemana(): void
    {
        $this->semana = $this->getInicioSemana()->addWeek()->toDateString();
    }

    public function semanaAtual(): void
    {
        $this->semana = null;
    }

    /**
     * @return array{inicio: Carbon, fim: Carbon, dias: list<array<string, mixed>>}|null
     */
    public function getAgenda(): ?array
    {
        $matricula = $this->getMatriculaSelecionada();

        return $matricula
            ? app(HorarioAlunoService::class)->semana($matricula, $this->getInicioSemana())
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Horários', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🕒', 'Horários', 'A grade de aulas da semana.')
            ->secao('🎯 O que você pode fazer?', [
                ['📅', 'Agenda semanal', 'Veja as aulas de cada dia da semana.'],
                ['⬅️', 'Navegar', 'Use "Semana anterior", "Próxima semana" e "Semana atual" para mudar o período exibido.'],
            ])
            ->dica('A grade pode mudar durante o ano; consulte sempre a semana atual.');
    }
}
