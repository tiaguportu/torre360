<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReservaEspacos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ReservaEspacos\ReservaEspacoResource;
use App\Models\Sala;
use App\Services\OcupacaoEspacoService;
use App\Support\HelpContent;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Mapa de ocupação de um espaço: reservas e aulas da grade fixa, dia a dia, para achar um horário livre antes de
 * pedir a reserva.
 */
class OcupacaoEspacos extends Page implements HasForms
{
    use HasAjudaAction;
    use InteractsWithForms;

    protected static string $resource = ReservaEspacoResource::class;

    protected string $view = 'filament.resources.reserva-espacos.pages.ocupacao-espacos';

    protected static ?string $title = 'Ocupação dos Espaços';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filtros = [];

    public function mount(): void
    {
        $this->form->fill([
            'sala_id' => null,
            'data_inicial' => today()->toDateString(),
            'dias' => 7,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filtros')
            ->columns(3)
            ->components([
                Select::make('sala_id')
                    ->label('Espaço')
                    ->options(fn (): array => Sala::query()->ativas()->orderBy('nome')->pluck('nome', 'id')->all())
                    ->searchable()
                    ->live()
                    ->placeholder('Escolha um espaço'),
                DatePicker::make('data_inicial')
                    ->label('A partir de')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->live()
                    ->required(),
                Select::make('dias')
                    ->label('Período')
                    ->options([1 => '1 dia', 7 => '7 dias', 14 => '14 dias', 30 => '30 dias'])
                    ->default(7)
                    ->live()
                    ->required(),
            ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getMapa(): array
    {
        $salaId = $this->filtros['sala_id'] ?? null;

        if (! $salaId) {
            return [];
        }

        $sala = Sala::query()->find($salaId);

        if (! $sala) {
            return [];
        }

        $dataInicial = Carbon::parse($this->filtros['data_inicial'] ?? today());
        $dias = (int) ($this->filtros['dias'] ?? 7);

        return app(OcupacaoEspacoService::class)->mapa($sala, $dataInicial, $dias);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nova')
                ->label('Nova reserva')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => ReservaEspacoResource::getUrl('create'))
                ->visible(fn (): bool => (bool) auth()->user()?->can('Create:ReservaEspaco')),
            Action::make('voltar')
                ->label('Voltar para a listagem')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => ReservaEspacoResource::getUrl('index')),
            $this->ajudaAction('Ocupação dos Espaços', HelpContent::make('🗓️', 'Ocupação dos Espaços', 'Veja o que já ocupa um espaço, dia a dia.')
                ->secao('📖 Como ler', [
                    ['🟦', 'Reserva', 'Uso pontual já pedido ou confirmado por alguém.'],
                    ['⬜', 'Grade fixa', 'Aula semanal de uma turma que acontece nessa data. Em dias não letivos e fora do período letivo da turma a grade não aparece.'],
                ])
                ->dica('Escolha o espaço no filtro; os dias sem blocos estão livres o dia todo.')),
        ];
    }
}
