<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentoRegulatorios\Pages;

use App\Filament\Resources\DocumentoRegulatorios\DocumentoRegulatorioResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListDocumentoRegulatorios extends ListRecords
{
    protected static string $resource = DocumentoRegulatorioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo Documento Regulatório'),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Documentos Regulatórios e Validades')
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
        $html = '<p>Nesta tela você gerencia os <strong>Documentos Regulatórios, Alvarás, Laudos e Licenças</strong> da escola e de suas unidades, acompanhando prazos de validade e alertas de renovação.</p>';
        $html .= '<h4>Funcionalidades Disponíveis:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Alertas de Validade:</strong> Documentos com validade próxima ao vencimento recebem destaque em amarelo e vencidos em vermelho.</li>';
        $html .= '<li><strong>Filtros Rápidos:</strong> Filtre por tipo de documento (Alvará, Bombeiros, Sanitária, etc.) e por unidade escolar.</li>';
        $html .= '<li><strong>Armazenamento Seguro:</strong> Faça o upload do PDF ou imagem do documento original para consulta rápida.</li>';
        $html .= '</ul>';

        return $html;
    }
}
