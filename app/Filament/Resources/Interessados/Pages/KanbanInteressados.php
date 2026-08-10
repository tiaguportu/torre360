<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\Actions\ImportarLeadIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class KanbanInteressados extends Page
{
    protected static string $resource = InteressadoResource::class;

    protected string $view = 'filament.resources.interessados.pages.kanban-interessados';

    protected static ?string $title = 'Funil de Vendas (CRM)';

    protected static ?string $slug = 'kanban';

    public ?int $filtroConsultorId = null;

    protected function getHeaderActions(): array
    {
        return [
            ImportarLeadIaAction::make(),
            Action::make('filtroConsultor')
                ->label('Filtrar Consultor')
                ->icon('heroicon-o-funnel')
                ->color('gray')
                ->form([
                    Select::make('consultor_id')
                        ->label('Consultor')
                        ->options(User::pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Todos os consultores'),
                ])
                ->action(function (array $data) {
                    $this->filtroConsultorId = $data['consultor_id'] ?? null;
                }),
            Action::make('list')
                ->label('Ver Lista')
                ->icon('heroicon-o-list-bullet')
                ->color('info')
                ->url(InteressadoResource::getUrl('index')),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Funil de Vendas (Kanban)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    public function getStatuses(): Collection
    {
        return StatusInteressado::orderBy('ordem')->get();
    }

    public function getInteressados(): Collection
    {
        $query = Interessado::with(['pessoa', 'status', 'origem', 'usuario', 'dependentes.serie', 'ultimoHistorico']);

        if ($this->filtroConsultorId) {
            $query->where('usuario_id', $this->filtroConsultorId);
        }

        return $query->get();
    }

    public function updateRecordStatus($recordId, $statusId): void
    {
        $record = Interessado::find($recordId);
        if ($record) {
            $updateData = ['status_interessado_id' => $statusId];

            // Se moveu para status de ganho, registra data de conversão
            $novoStatus = StatusInteressado::find($statusId);
            if ($novoStatus?->is_ganho && ! $record->data_conversao) {
                $updateData['data_conversao'] = now();
            }

            $record->update($updateData);

            Notification::make()
                ->title('Status atualizado!')
                ->success()
                ->send();
        }
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $canCreate = $user->can('Create:Interessado');
        $canUpdate = $user->can('Update:Interessado');

        $html = '<p>O <strong>Funil de Vendas</strong> (Kanban) permite visualizar e gerenciar seus leads de forma visual, arrastando os cards entre as etapas do processo comercial.</p>';
        $html .= '<h3>Como usar:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Visualização:</strong> Cada coluna representa um status do funil. Os cards mostram o interessado, origem, dependentes e próximo contato.</li>';
        $html .= '<li><strong>Arrastar e Soltar:</strong> Mova os cards entre colunas para atualizar o status do lead.</li>';
        $html .= '<li><strong>Cards em Vermelho:</strong> Indicam leads com contato atrasado (urgente!).</li>';
        $html .= '<li><strong>Filtro de Consultor:</strong> Use o botão "Filtrar Consultor" para ver apenas os leads de um consultor específico.</li>';

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Clique no ícone de lápis para abrir os detalhes do lead.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
