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

        $html .= '<h4>Exemplo de Exibição de Contagens (count)</h4>';
        $html .= '<pre style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-bottom: 10px;">';
        $html .= 'Este contrato possui {{ $faturas->count() }} parcelas no total.';
        $html .= '</pre>';

        $html .= '<h3>Objetos e Variáveis do Blade Disponíveis</h3>';
        $html .= '<ul>';
        $html .= '<li><code>$contrato</code>: O modelo do Contrato (ex: <code>{{ $contrato->valor_total }}</code>, <code>{{ $contrato->id }}</code>).';
        $html .= '  <ul>';
        $html .= '    <li><code>{{ $contrato->matricula->turma->nome }}</code>: Nome da turma do aluno.</li>';
        $html .= '    <li><code>{{ $contrato->matricula->turma->serie->nome }}</code>: Nome da série/ano do aluno.</li>';
        $html .= '    <li><code>{{ $contrato->matricula->pessoa->nome }}</code>: Nome do aluno associado ao contrato.</li>';
        $html .= '  </ul>';
        $html .= '</li>';
        $html .= '<li><code>$aluno</code>: Cadastro do Aluno (objeto Pessoa) contendo os seguintes atributos principais:';
        $html .= '  <ul>';
        $html .= '    <li><code>{{ $aluno->nome }}</code>: Nome completo do aluno.</li>';
        $html .= '    <li><code>{{ $aluno->cpf }}</code>: CPF do aluno (se cadastrado).</li>';
        $html .= '    <li><code>{{ $aluno->identidade }}</code>: Registro Geral (RG) / Identidade do aluno.</li>';
        $html .= '    <li><code>{{ $aluno->data_nascimento }}</code>: Data de nascimento.</li>';
        $html .= '    <li><code>{{ $aluno->responsaveis }}</code>: Lista de todos os contatos/parentes do aluno.</li>';
        $html .= '  </ul>';
        $html .= '</li>';
        $html .= '<li><code>$unidade</code>: Unidade de Ensino (ex: <code>{{ $unidade->nome }}</code>, <code>{{ $unidade->cnpj }}</code>).</li>';
        $html .= '<li><code>$responsaveis</code>: Coleção dos responsáveis financeiros do contrato (cada item contém a pessoa associada via <code>$rf->pessoa</code>).</li>';
        $html .= '<li><code>$faturas</code>: Coleção de todas as faturas/parcelas geradas para o contrato (vencimento, valor bruto, valor líquido).</li>';
        $html .= '</ul>';

        return $html;
    }
}
