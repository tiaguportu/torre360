<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class PreceptoriaCalendarWidget extends Widget implements HasForms
{
    use HasWidgetShield;
    use InteractsWithForms;

    protected string $view = 'filament.widgets.preceptoria-calendar-widget';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    public function getAllEvents(): array
    {
        $query = Preceptoria::with(['professor', 'matricula.pessoa']);

        $this->applyQueryFilters($query);

        return $query->get()->map(function (Preceptoria $record) {
            $dataStr = $record->data->format('Y-m-d');
            $inicioStr = $record->hora_inicio ? $record->hora_inicio->format('H:i:s') : '00:00:00';
            $fimStr = $record->hora_fim ? $record->hora_fim->format('H:i:s') : '23:59:59';

            $start = $dataStr.'T'.$inicioStr;
            $end = $dataStr.'T'.$fimStr;

            $isAgendado = $record->matricula_id !== null;
            $cor = $isAgendado ? '#10b981' : '#6b7280'; // Verde se agendado, Cinza se disponível

            return [
                'id' => (string) $record->id,
                'title' => $isAgendado
                    ? 'Agendado: '.($record->matricula?->pessoa?->nome ?? 'Aluno')
                    : 'Disponível',
                'start' => $start,
                'end' => $end,
                'url' => PreceptoriaResource::getUrl('edit', ['record' => $record]),
                'professor_id' => (string) $record->professor_id,
                'professor_nome' => $record->professor?->nome ?? 'Sem Professor',
                'matricula_id' => $record->matricula_id ? (string) $record->matricula_id : null,
                'aluno_nome' => $record->matricula?->pessoa?->nome ?? 'N/A',
                'hora_inicio' => $record->hora_inicio ? $record->hora_inicio->format('H:i') : '',
                'hora_fim' => $record->hora_fim ? $record->hora_fim->format('H:i') : '',
                'data' => date('d/m/Y', strtotime($record->data)),
                'backgroundColor' => $cor,
                'borderColor' => $cor,
                'textColor' => '#ffffff',
                'is_agendado' => $isAgendado,
            ];
        })->toArray();
    }

    private function applyQueryFilters(Builder $query): void
    {
        if (auth()->user()?->hasRole('professor')) {
            $pessoaIds = auth()->user()->pessoas->pluck('id')->toArray();
            $query->whereIn('professor_id', $pessoaIds);
        }
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filtros')
                    ->components([
                        Grid::make(2)
                            ->components([
                                Select::make('professores')
                                    ->label('Professores')
                                    ->multiple()
                                    ->options(Pessoa::whereHas('users', fn ($q) => $q->role('professor'))
                                        ->whereNotNull('nome')
                                        ->orderBy('nome')
                                        ->pluck('nome', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn () => auth()->user()?->hasRole('professor')),

                                Select::make('status')
                                    ->label('Status de Agendamento')
                                    ->options([
                                        'agendado' => 'Agendado (Com Matrícula)',
                                        'disponivel' => 'Disponível (Sem Matrícula)',
                                    ])
                                    ->placeholder('Todos')
                                    ->live(),
                            ]),
                    ])
                    ->collapsible()
                    ->compact(),
            ])
            ->statePath('data');
    }
}
