<?php

namespace App\Filament\Resources\TemplateContratos\Pages;

use App\Filament\Resources\TemplateContratos\TemplateContratoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditTemplateContrato extends EditRecord
{
    protected static string $resource = TemplateContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Template de Contrato')
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
        $html = '<p>Esta página permite editar o modelo de contrato selecionado.</p>';

        $html .= '<h3>Sintaxe do Blade Suportada</h3>';
        $html .= '<p>Você pode escrever loops e condições no conteúdo do contrato para torná-lo dinâmico:</p>';

        $html .= '<h4>Exemplo de Repetição (Loop - @foreach)</h4>';
        $html .= '<pre style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-bottom: 10px;">';
        $html .= '@foreach($responsaveis as $rf)'."\n";
        $html .= '  Responsável: {{ $rf->pessoa->nome }} (CPF: {{ $rf->pessoa->cpf }})'."\n";
        $html .= '@endforeach';
        $html .= '</pre>';

        $html .= '<h4>Exemplo Condicional (IF-ELSE - @if)</h4>';
        $html .= '<pre style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-bottom: 10px;">';
        $html .= '@if($aluno->responsaveis->where(\'pivot.tipo_vinculo.nome\', \'Pai\')->count())'."\n";
        $html .= '  Pai: {{ $aluno->responsaveis->firstWhere(\'pivot.tipo_vinculo.nome\', \'Pai\')->nome }}'."\n";
        $html .= '@else'."\n";
        $html .= '  (Pai não cadastrado)'."\n";
        $html .= '@endif';
        $html .= '</pre>';

        $html .= '<h4>Variáveis de Contexto</h4>';
        $html .= '<ul>';
        $html .= '<li><code>$contrato</code>: Modelo do contrato gerado.</li>';
        $html .= '<li><code>$aluno</code>: Cadastro da pessoa (aluno).</li>';
        $html .= '<li><code>$unidade</code>: Unidade de ensino correspondente.</li>';
        $html .= '<li><code>$responsaveis</code>: Coleção dos responsáveis financeiros.</li>';
        $html .= '<li><code>$faturas</code>: Coleção das faturas.</li>';
        $html .= '</ul>';

        return $html;
    }
}
