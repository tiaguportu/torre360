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

        $html .= '<h4>Exemplo de Tabela Dinâmica no Editor Visual (Sem Código Fonte)</h4>';
        $html .= '<p>Para criar uma tabela de tamanho variável (que cresce de acordo com o número de faturas) diretamente pelo editor visual, crie uma tabela de 4 linhas:</p>';
        $html .= '<ul style="margin-left: 20px; list-style-type: disc; margin-bottom: 10px;">';
        $html .= '  <li><strong>Linha 1 (Cabeçalho):</strong> Digite os títulos das colunas (ex: Parcela | Vencimento | Valor).</li>';
        $html .= '  <li><strong>Linha 2 (Abertura do Loop):</strong> Mescle as células da linha e digite: <code>@foreach($faturas-&gt;sortBy(\'vencimento\') as $index =&gt; $f)</code></li>';
        $html .= '  <li><strong>Linha 3 (Dados):</strong> Digite as variáveis nas respectivas células de dados:';
        $html .= '    <ul style="margin-left: 20px; list-style-type: circle; margin-top: 5px; margin-bottom: 5px;">';
        $html .= '      <li>Célula 1: <code>{{ $index + 1 }}</code></li>';
        $html .= '      <li>Célula 2: <code>{{ \Carbon\Carbon::parse($f-&gt;vencimento)-&gt;format(\'d/m/Y\') }}</code></li>';
        $html .= '      <li>Célula 3: <code>R$ {{ number_format($f-&gt;valor, 2, \',\', \'.\') }}</code></li>';
        $html .= '    </ul>';
        $html .= '  </li>';
        $html .= '  <li><strong>Linha 4 (Fechamento do Loop):</strong> Mescle as células da linha e digite: <code>@endforeach</code></li>';
        $html .= '</ul>';

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

        $html .= '<h3>Macros e Variáveis de Tabela Customizáveis (Uso Simplificado no Editor Visual)</h3>';
        $html .= '<p>Se você não deseja usar o modo Código Fonte ou programar loops no editor, digite as macros abaixo como texto plano diretamente no editor visual (usando a sintaxe de chaves com exclamação dupla <code>{{!! variavel !!}}</code>). Os layouts destas tabelas/assinaturas podem ser editados em <strong>Configurações</strong> do painel administrativo:</p>';
        $html .= '<ul>';
        $html .= '<li><code>{{!! tabela_fatura !!}}</code>: Tabela dinâmica contendo todas as faturas, vencimentos e valores do contrato (configuração: <code>template_contrato_tabela_fatura</code>).</li>';
        $html .= '<li><code>{{!! tabela_aluno !!}}</code>: Tabela estruturada com os dados do Aluno, Turma e Série (configuração: <code>template_contrato_tabela_aluno</code>).</li>';
        $html .= '<li><code>{{!! info_responsaveis !!}}</code>: Texto completo de qualificação dos responsáveis financeiros com endereços (configuração: <code>template_contrato_info_responsaveis</code>).</li>';
        $html .= '<li><code>{{!! assinaturas_representantes !!}}</code>: Linhas de assinatura dos representantes da unidade (configuração: <code>template_contrato_assinaturas_representantes</code>).</li>';
        $html .= '<li><code>{{!! assinaturas_responsaveis !!}}</code>: Linhas de assinatura dos responsáveis financeiros do contrato (configuração: <code>template_contrato_assinaturas_responsaveis</code>).</li>';
        $html .= '<li><code>{{!! assinatura_pai !!}}</code>: Linha de assinatura específica do Pai do aluno (configuração: <code>template_contrato_assinatura_pai</code>).</li>';
        $html .= '<li><code>{{!! assinatura_mae !!}}</code>: Linha de assinatura específica da Mãe do aluno (configuração: <code>template_contrato_assinatura_mae</code>).</li>';
        $html .= '</ul>';

        return $html;
    }
}
