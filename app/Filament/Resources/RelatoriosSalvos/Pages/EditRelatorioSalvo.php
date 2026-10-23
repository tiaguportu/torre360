<?php

declare(strict_types=1);

namespace App\Filament\Resources\RelatoriosSalvos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RelatoriosSalvos\RelatorioSalvoResource;
use App\Filament\Resources\RelatoriosSalvos\Tables\RelatoriosSalvosTable;
use App\Services\Relatorios\ConfiguracaoDeRelatorioService;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRelatorioSalvo extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = RelatorioSalvoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            return app(ConfiguracaoDeRelatorioService::class)->normalizar($data, auth()->user(), $this->getRecord());
        } catch (\DomainException $e) {
            Notification::make()->title('Revise o relatório')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            RelatoriosSalvosTable::executar(),
            DeleteAction::make(),
            $this->ajudaAction('Editar relatório', HelpContent::make('📑', 'Editar relatório', 'Ajuste as colunas, os filtros ou o agendamento.')
                ->secao('📌 Bom saber', [
                    ['🕐', 'Mudar o agendamento', 'Trocar a frequência, o dia ou a hora recalcula a próxima execução. Salvar sem mexer nisso não muda a agenda.'],
                    ['🛑', 'Agendamento desligado', 'Se o relatório falhar três vezes seguidas, o sistema desliga o agendamento e avisa você. Veja o motivo no histórico e ligue de novo.'],
                    ['🗃️', 'Histórico', 'Cada execução fica registrada; o arquivo é apagado depois de alguns dias, o registro permanece.'],
                ])),
        ];
    }
}
