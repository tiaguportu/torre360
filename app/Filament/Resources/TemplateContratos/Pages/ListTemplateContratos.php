<?php

namespace App\Filament\Resources\TemplateContratos\Pages;

use App\Filament\Resources\TemplateContratos\TemplateContratoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTemplateContratos extends ListRecords
{
    protected static string $resource = TemplateContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Templates de Contrato')
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

        $canCreate = $user->can('Create:TemplateContrato');
        $canUpdate = $user->can('Update:TemplateContrato');

        $html = '<p>Esta página permite gerenciar os modelos de contrato do sistema.</p>';

        $html .= '<h3>Recursos e Sintaxe Suportados</h3>';
        $html .= '<p>Os templates suportam tanto substituições simples de macros quanto a sintaxe do compilador <strong>Blade</strong> (estruturas de repetição e condições).</p>';

        $html .= '<h4>1. Estrutura de Repetição (Loop - @foreach)</h4>';
        $html .= '<p>Útil para iterar sobre múltiplos registros vinculados, como faturas ou responsáveis:</p>';
        $html .= '<pre style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-bottom: 10px;">';
        $html .= '@foreach($responsaveis as $rf)'."\n";
        $html .= '  Responsável: {{ $rf->pessoa->nome }} (CPF: {{ $rf->pessoa->cpf }})'."\n";
        $html .= '@endforeach';
        $html .= '</pre>';

        $html .= '<h4>2. Estrutura Condicional (IF-ELSE - @if)</h4>';
        $html .= '<p>Útil para exibir ou ocultar seções com base em dados cadastrados (ex: quando o aluno não possui Pai cadastrado):</p>';
        $html .= '<pre style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-bottom: 10px;">';
        $html .= '@if($aluno->responsaveis->where(\'pivot.tipo_vinculo.nome\', \'Pai\')->count())'."\n";
        $html .= '  Pai: {{ $aluno->responsaveis->firstWhere(\'pivot.tipo_vinculo.nome\', \'Pai\')->nome }}'."\n";
        $html .= '@else'."\n";
        $html .= '  (Pai não declarado)'."\n";
        $html .= '@endif';
        $html .= '</pre>';

        $html .= '<h4>3. Variáveis Disponíveis no Escopo</h4>';
        $html .= '<ul>';
        $html .= '<li><code>$contrato</code>: O modelo do contrato gerado.</li>';
        $html .= '<li><code>$aluno</code>: O cadastro da pessoa (aluno).</li>';
        $html .= '<li><code>$unidade</code>: Unidade de ensino correspondente.</li>';
        $html .= '<li><code>$responsaveis</code>: Coleção dos responsáveis financeiros do contrato.</li>';
        $html .= '<li><code>$faturas</code>: Coleção das faturas geradas.</li>';
        $html .= '</ul>';

        $html .= '<h3>O que você pode fazer nesta página?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem de Modelos:</strong> Visualizar e buscar templates de contrato.</li>';
        if ($canCreate) {
            $html .= '<li><strong>Criar:</strong> Adicionar um novo template de contrato.</li>';
        }
        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Atualizar o nome, conteúdo ou torná-lo modelo padrão.</li>';
        }
        $html .= '<li><strong>Ação de Lote - Clonar:</strong> Selecione os templates na tabela e clique em "Clonar Selecionados" no topo para duplicar os registros rapidamente.</li>';
        $html .= '</ul>';

        return $html;
    }
}
