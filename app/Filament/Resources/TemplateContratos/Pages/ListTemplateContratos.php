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

        $html .= '<h4>3. Exibir Contagens (count)</h4>';
        $html .= '<p>Para exibir o total de itens de uma lista (ex: quantidade de parcelas de faturas do contrato):</p>';
        $html .= '<pre style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-bottom: 10px;">';
        $html .= 'Este contrato possui {{ $faturas->count() }} parcelas no total.';
        $html .= '</pre>';

        $html .= '<h4>4. Tabela Dinâmica (Exemplo: Faturas/Parcelas)</h4>';
        $html .= '<p>Para listar as faturas/parcelas do contrato dinamicamente em uma tabela HTML:</p>';
        $html .= '<pre style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-bottom: 10px;">';
        $html .= '&lt;table&gt;'."\n";
        $html .= '  &lt;thead&gt;'."\n";
        $html .= '    &lt;tr&gt;&lt;th&gt;Parcela&lt;/th&gt;&lt;th&gt;Vencimento&lt;/th&gt;&lt;th&gt;Valor&lt;/th&gt;&lt;/tr&gt;'."\n";
        $html .= '  &lt;/thead&gt;'."\n";
        $html .= '  &lt;tbody&gt;'."\n";
        $html .= '    @foreach($faturas->sortBy(\'vencimento\') as $index => $f)'."\n";
        $html .= '      &lt;tr&gt;'."\n";
        $html .= '        &lt;td&gt;{{ $index + 1 }}&lt;/td&gt;'."\n";
        $html .= '        &lt;td&gt;{{ \Carbon\Carbon::parse($f->vencimento)->format(\'d/m/Y\') }}&lt;/td&gt;'."\n";
        $html .= '        &lt;td&gt;R$ {{ number_format($f->valor, 2, \',\', \'.\') }}&lt;/td&gt;'."\n";
        $html .= '      &lt;/tr&gt;'."\n";
        $html .= '    @endforeach'."\n";
        $html .= '  &lt;/tbody&gt;'."\n";
        $html .= '&lt;/table&gt;';
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

        $html .= '<h3>Variáveis de Tabela e Assinatura Prontas (Uso Simplificado no Editor Visual)</h3>';
        $html .= '<p>Se você não deseja usar o modo Código Fonte ou programar loops no editor, digite as variáveis abaixo como texto plano diretamente no editor visual (obrigatoriamente usando a sintaxe de chaves com exclamação <code>{!! $variavel !!}</code> para que o layout HTML seja renderizado):</p>';
        $html .= '<ul>';
        $html .= '<li><code>{!! $tabelaFaturas !!}</code>: Tabela dinâmica contendo todas as faturas, vencimentos e valores do contrato.</li>';
        $html .= '<li><code>{!! $tabelaAluno !!}</code>: Tabela estruturada com os dados do Aluno, Turma e Série.</li>';
        $html .= '<li><code>{!! $infoResponsaveis !!}</code>: Texto completo de qualificação dos responsáveis financeiros com endereços.</li>';
        $html .= '<li><code>{!! $assinaturasRepresentantes !!}</code>: Linhas de assinatura dos representantes da unidade.</li>';
        $html .= '<li><code>{!! $assinaturasResponsaveis !!}</code>: Linhas de assinatura dos responsáveis financeiros do contrato.</li>';
        $html .= '<li><code>{!! $assinaturaPai !!}</code>: Linha de assinatura específica do Pai do aluno.</li>';
        $html .= '<li><code>{!! $assinaturaMae !!}</code>: Linha de assinatura específica da Mãe do aluno.</li>';
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
