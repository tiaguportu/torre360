<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Exports\ContratoExporter;
use App\Filament\Imports\ContratoImporter;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Contrato;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(ContratoImporter::class)
                ->visible(fn (): bool => auth()->user()->can('import', Contrato::class)),
            ExportAction::make()
                ->exporter(ContratoExporter::class)
                ->visible(fn (): bool => auth()->user()->can('export', Contrato::class)),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Contratos')
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

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $canCreate = $user->can('Create:Contrato');
        $canUpdate = $user->can('Update:Contrato');
        $canImport = $user->can('import', Contrato::class);
        $canExport = $user->can('export', Contrato::class);

        $html = '<p>Aqui você gerencia os contratos financeiros vinculados às matrículas.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Visualizar:</strong> Acompanhe o status de assinatura e valores totais dos contratos.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Gerar Contrato:</strong> Normalmente os contratos são gerados a partir da matrícula, mas podem ser criados manualmente aqui se necessário.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Ajuste valores, adicione responsáveis financeiros e registre a data de aceite.</li>';
        }

        if ($canImport) {
            $html .= '<li><strong>Importar Contratos:</strong> Permite importar contratos em lote via planilha. Ao importar com ID que já existe, os dados são atualizados; ao importar com ID que não existe, um novo contrato é criado. É possível indicar os IDs das tabelas relacionadas (Matrícula e Template) ou seus nomes equivalentes para associação automática.</li>';
        }

        if ($canExport) {
            $html .= '<li><strong>Exportar Contratos:</strong> Permite exportar a listagem de contratos atual para uma planilha de dados.</li>';
        }

        $html .= '<li><strong>Faturas:</strong> Os contratos são a base para a geração automática das faturas mensais.</li>';
        $html .= '</ul>';

        return $html;
    }
}
