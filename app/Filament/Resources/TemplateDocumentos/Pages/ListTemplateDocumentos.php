<?php

namespace App\Filament\Resources\TemplateDocumentos\Pages;

use App\Filament\Resources\TemplateDocumentos\TemplateDocumentoResource;
use App\Services\Documentos\ModelosPadraoDocumento;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTemplateDocumentos extends ListRecords
{
    protected static string $resource = TemplateDocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('modelos_padrao')
                ->label('Criar modelos padrão')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->can('Create:TemplateDocumento'))
                ->requiresConfirmation()
                ->modalHeading('Criar os modelos padrão')
                ->modalDescription('Cria o distrato de matrícula, o contrato de trabalho e o termo de confidencialidade que ainda não existem. Eles nascem inativos, como rascunhos: leia, ajuste com o jurídico e só então ative.')
                ->action(function (): void {
                    $criados = app(ModelosPadraoDocumento::class)->criarFaltantes();

                    Notification::make()
                        ->title($criados > 0 ? "{$criados} modelo(s) criado(s)" : 'Os modelos padrão já existem')
                        ->body($criados > 0 ? 'Estão inativos. Abra cada um, revise o texto e ative.' : null)
                        ->success()
                        ->send();
                }),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Modelos de Documentos Oficiais')
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

        $canCreate = $user->can('Create:TemplateDocumento');
        $canUpdate = $user->can('Update:TemplateDocumento');
        $canDelete = $user->can('Delete:TemplateDocumento');

        $html = '<p>Nesta página você gerencia os <strong>Modelos de Documentos Oficiais da Secretaria</strong> (Declaração de Matrícula, Frequência, Quitação de Débitos e Histórico Escolar).</p>';
        $html .= '<h4>Como Funciona a Emissão com QR Code:</h4>';
        $html .= '<p>Os modelos cadastrados aqui servem de base para a emissão de declarações para os estudantes. Sempre que um documento é emitido pelo sistema (seja pela secretaria ou por solicitação do aluno no Portal), o sistema substitui automaticamente as variáveis dinâmicas pelos dados reais da matrícula e anexa no rodapé um <strong>carimbo de autenticidade digital com QR Code</strong> para validação pública imediata.</p>';

        $html .= '<h4>Modelos para Assinatura Eletrônica:</h4>';
        $html .= '<p>Além das declarações do aluno, há modelos de <strong>Distrato de Matrícula</strong>, <strong>Contrato de Trabalho</strong> e <strong>Termo de Confidencialidade</strong>. Eles não saem pela emissão de declarações: vão para o Assinafy em <em>Documentos para Assinatura</em>. A coluna "Dados de" mostra de onde cada modelo tira as variáveis (aluno, cancelamento ou funcionário).</p>';
        $html .= '<p><strong>Atenção:</strong> os modelos padrão são pontos de partida e nascem <strong>inativos</strong>. O jurídico e o contador da escola devem revisar o texto antes de ativar.</p>';

        $html .= '<h4>Ações Disponíveis:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem e Busca:</strong> Consulte os modelos cadastrados, visualize o prazo de validade e o número de emissões já realizadas.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Modelo:</strong> Cadastre novos tipos de atestados ou declarações personalizadas com texto formatado no editor de texto.</li>';
            $html .= '<li><strong>Criar modelos padrão:</strong> Traz o distrato, o contrato de trabalho e o termo de confidencialidade que ainda não existem, como rascunhos inativos.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar Modelo:</strong> Ajuste o texto padrão, prazo de validade em dias ou ative/desative o modelo. O editor mostra as variáveis do tipo escolhido, recusa as que não existem e tem o botão <strong>Prévia</strong>, que mostra o documento com os dados de um registro.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Remova modelos que não são mais utilizados na escola.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
