<?php

namespace App\Filament\Resources\TemplateContratos\Pages;

use App\Filament\Resources\TemplateContratos\TemplateContratoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplateContrato extends CreateRecord
{
    protected static string $resource = TemplateContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Template de Contrato')
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
        $html = '<p>Esta página permite criar um novo modelo de contrato.</p>';

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

        $html .= '<h3>Macros e Variáveis Disponíveis</h3>';


        $html .= '<h4>1. Macros Estáticas (Substituição Direta)</h4>';
        $html .= '<ul>';
        $html .= '<li><code>{{CONTRATO.ID}}</code>: ID do contrato.</li>';
        $html .= '<li><code>{{CONTRATO.VALOR}}</code>: Valor total formatado (ex: R$ 1.200,00).</li>';
        $html .= '<li><code>{{CONTRATO.DATA}}</code>: Data de hoje formatada por extenso.</li>';
        $html .= '<li><code>{{UNIDADE.NOME}}</code>: Nome da Unidade.</li>';
        $html .= '<li><code>{{UNIDADE.CNPJ}}</code>: CNPJ da Unidade.</li>';
        $html .= '<li><code>{{UNIDADE.REPRESENTANTES}}</code>: Nomes dos representantes legais da unidade formatados gramaticalmente.</li>';
        $html .= '<li><code>{{ALUNO.TABELA}}</code>: Tabela HTML pré-formatada com as informações básicas do Aluno.</li>';
        $html .= '<li><code>{{RESPONSAVEIS.INFO}}</code>: Informações em texto e CPF dos responsáveis financeiros do contrato.</li>';
        $html .= '<li><code>{{FATURAS.TABELA}}</code>: Tabela HTML pré-formatada contendo as parcelas, vencimentos e valores do contrato.</li>';
        $html .= '<li><code>{{ASSINATURA.REPRESENTANTES}}</code>: Linhas de assinatura para os representantes legais da Unidade.</li>';
        $html .= '<li><code>{{ASSINATURA.RESPONSAVEIS}}</code>: Linhas de assinatura para os responsáveis financeiros do contrato.</li>';
        $html .= '<li><code>{{ASSINATURA.PAI}}</code>: Linha de assinatura específica para o Pai.</li>';
        $html .= '<li><code>{{ASSINATURA.MAE}}</code>: Linha de assinatura específica para a Mãe.</li>';
        $html .= '</ul>';

        $html .= '<h4>2. Objetos e Variáveis do Blade (Escopo)</h4>';
        $html .= '<ul>';
        $html .= '<li><code>$contrato</code>: Modelo do Contrato (ex: <code>{{ $contrato->valor_total }}</code>).</li>';
        $html .= '<li><code>$aluno</code>: Cadastro do Aluno (Pessoa) (ex: <code>{{ $aluno->nome }}</code>, <code>{{ $aluno->cpf }}</code>).</li>';
        $html .= '<li><code>$unidade</code>: Unidade de Ensino (ex: <code>{{ $unidade->nome }}</code>).</li>';
        $html .= '<li><code>$responsaveis</code>: Coleção dos responsáveis financeiros do contrato. Cada item possui a relação <code>$rf->pessoa</code>.</li>';
        $html .= '<li><code>$faturas</code>: Coleção de faturas geradas para o contrato.</li>';
        $html .= '</ul>';

        return $html;
    }
}
