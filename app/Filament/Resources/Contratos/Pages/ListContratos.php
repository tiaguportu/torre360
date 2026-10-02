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

        $html .= '<h3>Coluna "Assinatura" (assinatura digital)</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Não enviado:</strong> o contrato foi criado, mas ainda não foi enviado para assinatura.</li>';
        $html .= '<li><strong>Pendente:</strong> enviado ao Assinafy, aguardando as assinaturas.</li>';
        $html .= '<li><strong>Todos assinaram:</strong> todas as assinaturas foram coletadas. É a primeira etapa depois da última assinatura.</li>';
        $html .= '<li><strong>Certificando:</strong> o certificado digital do documento está sendo gerado.</li>';
        $html .= '<li><strong>Certificado:</strong> etapa final, com o certificado gerado e o documento assinado pronto para download.</li>';
        $html .= '<li><strong>Recusado, Cancelado, Expirado e Erro no envio:</strong> o processo foi interrompido; o contrato precisa ser enviado novamente.</li>';
        $html .= '</ul>';
        $html .= '<p><strong>Todos assinaram</strong>, <strong>Certificando</strong> e <strong>Certificado</strong> contam como contrato <strong>assinado</strong>: a ação passa a ser "Ver Contrato Assinado", o contrato sai da lista de pendentes e a matrícula deixa de ter a pendência "Contrato não assinado".</p>';

        $html .= '<h3>Data de aceite e atualização do status</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Data de aceite:</strong> é o dia em que a <strong>última assinatura</strong> foi coletada (quando todos assinaram), e não o dia em que o contrato foi criado.</li>';
        $html .= '<li><strong>Atualização automática:</strong> o Assinafy avisa o sistema a cada assinatura e o sistema também o consulta de hora em hora para recuperar avisos que não chegaram.</li>';
        $html .= '<li><strong>Sincronizar Assinaturas:</strong> use esta ação na linha do contrato para consultar o Assinafy na hora e atualizar o status e quem já assinou.</li>';
        $html .= '</ul>';

        return $html;
    }
}
