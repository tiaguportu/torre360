<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class CrmFollowUpCalendarWidget extends Widget implements HasForms
{
    use HasWidgetShield;
    use InteractsWithForms;

    protected string $view = 'filament.widgets.crm-follow-up-calendar-widget';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    // Filtro fixo vindo de um componente pai (ex: agenda de um consultor específico)
    public ?int $fixedConsultorId = null;

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
                                Select::make('consultores')
                                    ->label('Consultor')
                                    ->multiple()
                                    ->options(fn () => User::whereIn('id',
                                        Interessado::whereNotNull('usuario_id')->distinct()->pluck('usuario_id')
                                    )->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn () => $this->fixedConsultorId !== null),

                                Toggle::make('somente_atrasados')
                                    ->label('Somente atrasados')
                                    ->live(),
                            ]),
                    ])
                    ->collapsible()
                    ->compact(),
            ])
            ->statePath('data');
    }

    /**
     * Carrega todos os eventos que o usuário tem permissão de ver (regra de negócio,
     * aplicada no servidor). Os filtros de "consultor" e "somente atrasados" do
     * formulário são aplicados no cliente (JS), sobre esse conjunto já permitido —
     * mesmo padrão do CronogramaCalendarWidget, que não faz round-trip ao servidor
     * a cada mudança de filtro.
     */
    public function getEvents(): array
    {
        $query = Interessado::ativos()
            ->whereNotNull('data_proximo_contato')
            ->with(['pessoa', 'usuario', 'status']);

        if ($this->fixedConsultorId) {
            $query->where('usuario_id', $this->fixedConsultorId);
        } elseif (! auth()->user()->hasRole(['super_admin', 'admin'])) {
            $query->where('usuario_id', auth()->id());
        }

        return $query->get()->map(function (Interessado $i) {
            $overdue = $i->data_proximo_contato->isPast();
            $color = $overdue ? '#ef4444' : ($i->data_proximo_contato->isToday() ? '#f97316' : '#3b82f6');

            return [
                'id' => (string) $i->id,
                'title' => $i->pessoa?->nome ?? 'Interessado #'.$i->id,
                'start' => $i->data_proximo_contato->format('Y-m-d\TH:i:s'),
                'url' => InteressadoResource::getUrl('edit', ['record' => $i]),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',
                'consultor_id' => (string) $i->usuario_id,
                'atrasado' => $overdue,
                'extendedProps' => [
                    'consultor' => $i->usuario?->name ?? 'Sem consultor',
                    'status' => $i->status?->nome,
                    'atrasado' => $overdue,
                ],
            ];
        })->toArray();
    }
}
