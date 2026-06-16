<?php

namespace App\Filament\Resources\Questionarios\Pages;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditQuestionario extends EditRecord
{
    protected static string $resource = QuestionarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            Action::make('responder')
                ->label('Responder Questionário')
                ->icon('heroicon-o-pencil-square')
                ->color('success')
                ->url(fn () => QuestionarioResource::getUrl('responder', ['record' => $this->record]))
                ->openUrlInNewTab(),
            Action::make('ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $html = '<div class="space-y-6">';

        // ── Visão geral ──────────────────────────────────────────────────────
        $html .= '<p>Nesta página você configura a estrutura completa do questionário: blocos temáticos, perguntas, público-alvo e permissões de acesso.</p>';

        // ── Funcionalidades gerais ───────────────────────────────────────────
        $html .= '<h3 class="text-lg font-bold mt-4">⚙️ Funcionalidades Gerais</h3>';
        $html .= '<ul class="list-disc ml-6 space-y-1">';
        $html .= '<li><strong>Drag-and-Drop:</strong> Arraste os blocos ou perguntas pelos ícones de ordenação para reorganizá-los.</li>';
        $html .= '<li><strong>Colapso:</strong> Clique no cabeçalho de um bloco ou pergunta para expandir ou recolher o conteúdo.</li>';
        $html .= '<li><strong>Clonagem:</strong> Use os botões de <strong>Clonar Bloco</strong> e <strong>Clonar Pergunta</strong> (ícone de duas folhas) para duplicar blocos inteiros (incluindo suas perguntas) ou perguntas individuais.</li>';
        $html .= '<li><strong>Tipos de Pergunta:</strong> Discursiva (texto livre), Objetiva (única escolha), Múltipla Escolha, Escala Likert (1–5), Lista de Usuários do Sistema, Lista de Alunos de uma Turma e Lista de Pessoas Cadastradas.</li>';

        if ($user->can('Update:Questionario')) {
            $html .= '<li>✅ Você tem permissão para editar todas as informações deste questionário.</li>';
        }

        $html .= '</ul>';

        // ── Exibição condicional de perguntas ────────────────────────────────
        $html .= '<hr class="my-4">';
        $html .= '<h3 class="text-lg font-bold">🔀 Exibição Condicional de Perguntas</h3>';
        $html .= '<p class="mt-1">Cada pergunta pode ficar <strong>oculta por padrão</strong> e só aparecer ao respondente quando a resposta de outra pergunta satisfizer uma regra que você defina. Use isso para criar formulários ramificados e dinâmicos.</p>';

        $html .= '<h4 class="font-semibold mt-3">Como configurar:</h4>';
        $html .= '<ol class="list-decimal ml-6 space-y-1">';
        $html .= '<li>Expanda uma pergunta e clique na seção <strong>"Condição de Exibição"</strong> (está recolhida por padrão).</li>';
        $html .= '<li>Escolha a <strong>Pergunta de Referência</strong> — a pergunta cujo valor será avaliado. Deixe vazio para que a pergunta seja sempre exibida.</li>';
        $html .= '<li>Escolha o <strong>Operador</strong> (veja tabela abaixo).</li>';
        $html .= '<li>Informe o <strong>Valor Esperado</strong> quando necessário.</li>';
        $html .= '</ol>';

        // ── Tabela de operadores ─────────────────────────────────────────────
        $html .= '<h4 class="font-semibold mt-4">Operadores disponíveis:</h4>';
        $html .= '<table class="w-full text-sm border border-gray-300 rounded mt-2">';
        $html .= '<thead class="bg-gray-100"><tr>';
        $html .= '<th class="text-left px-3 py-2 border-b">Operador</th>';
        $html .= '<th class="text-left px-3 py-2 border-b">A pergunta aparece quando…</th>';
        $html .= '<th class="text-left px-3 py-2 border-b">Exige "Valor Esperado"?</th>';
        $html .= '</tr></thead><tbody>';

        $operadores = [
            ['É igual a', 'A resposta for exatamente igual ao valor informado.', 'Sim'],
            ['É diferente de', 'A resposta for diferente do valor informado.', 'Sim'],
            ['Contém', 'A resposta contiver o trecho ou opção informada.', 'Sim'],
            ['Não contém', 'A resposta não contiver o trecho ou opção informada.', 'Sim'],
            ['Foi preenchida (qualquer valor)', 'O respondente preencher qualquer coisa (não deixar em branco).', 'Não'],
            ['Não foi preenchida', 'O respondente deixar o campo em branco / sem resposta.', 'Não'],
            ['É maior que', 'A resposta for um número maior que o valor informado.', 'Sim'],
            ['É menor que', 'A resposta for um número menor que o valor informado.', 'Sim'],
        ];

        foreach ($operadores as $i => [$nome, $desc, $exige]) {
            $bg = ($i % 2 === 0) ? '' : 'bg-gray-50';
            $html .= "<tr class=\"{$bg}\">";
            $html .= "<td class=\"px-3 py-2 font-medium border-b\">{$nome}</td>";
            $html .= "<td class=\"px-3 py-2 border-b\">{$desc}</td>";
            $html .= "<td class=\"px-3 py-2 border-b text-center\">{$exige}</td>";
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        // ── Exemplos práticos ────────────────────────────────────────────────
        $html .= '<h4 class="font-semibold mt-5">📋 Exemplos Práticos</h4>';

        $exemplos = [
            [
                'titulo' => 'Exemplo 1 — "É igual a" (Sim/Não)',
                'desc' => 'Mostrar "Quantos filhos você tem?" somente quando a resposta de "Você tem filhos?" for <em>Sim</em>.',
                'config' => [
                    'Pergunta de Referência' => '"Você tem filhos?" (Objetiva: Sim / Não)',
                    'Operador' => 'É igual a',
                    'Valor Esperado' => 'Sim',
                ],
            ],
            [
                'titulo' => 'Exemplo 2 — "É diferente de" (filtrar uma opção)',
                'desc' => 'Mostrar "Qual outro meio de transporte você usa?" somente quando a resposta de "Como você vai à escola?" <em>não for</em> "A pé".',
                'config' => [
                    'Pergunta de Referência' => '"Como você vai à escola?" (Objetiva)',
                    'Operador' => 'É diferente de',
                    'Valor Esperado' => 'A pé',
                ],
            ],
            [
                'titulo' => 'Exemplo 3 — "Contém" (múltipla escolha)',
                'desc' => 'Mostrar "Qual atividade esportiva você pratica?" somente quando em "Quais suas preferências de lazer?" o respondente tiver marcado a opção <em>Esportes</em> (entre outras opções).',
                'config' => [
                    'Pergunta de Referência' => '"Quais suas preferências de lazer?" (Múltipla Escolha)',
                    'Operador' => 'Contém',
                    'Valor Esperado' => 'Esportes',
                ],
            ],
            [
                'titulo' => 'Exemplo 4 — "Não contém" (múltipla escolha ou texto)',
                'desc' => 'Mostrar "Você gostaria de receber informações sobre natação?" somente quando a resposta de "Quais esportes você já pratica?" <em>não incluir</em> "Natação".',
                'config' => [
                    'Pergunta de Referência' => '"Quais esportes você já pratica?" (Múltipla Escolha)',
                    'Operador' => 'Não contém',
                    'Valor Esperado' => 'Natação',
                ],
            ],
            [
                'titulo' => 'Exemplo 5 — "Foi preenchida" (qualquer valor)',
                'desc' => 'Mostrar "Você gostaria de dar mais detalhes sobre sua sugestão?" somente quando o respondente tiver digitado <em>qualquer coisa</em> no campo "Deixe sua sugestão".',
                'config' => [
                    'Pergunta de Referência' => '"Deixe sua sugestão" (Discursiva)',
                    'Operador' => 'Foi preenchida (qualquer valor)',
                    'Valor Esperado' => '(não necessário)',
                ],
            ],
            [
                'titulo' => 'Exemplo 6 — "Não foi preenchida" (campo em branco)',
                'desc' => 'Mostrar "Por que você não tem e-mail?" somente quando o campo "Informe seu e-mail" tiver sido deixado em branco pelo respondente.',
                'config' => [
                    'Pergunta de Referência' => '"Informe seu e-mail" (Discursiva)',
                    'Operador' => 'Não foi preenchida',
                    'Valor Esperado' => '(não necessário)',
                ],
            ],
            [
                'titulo' => 'Exemplo 7 — "É maior que" (numérico)',
                'desc' => 'Mostrar "Quais foram as dificuldades?" somente quando a nota atribuída em "Avalie de 1 a 10" for maior que 7.',
                'config' => [
                    'Pergunta de Referência' => '"Avalie de 1 a 10" (Discursiva/Numérica)',
                    'Operador' => 'É maior que',
                    'Valor Esperado' => '7',
                ],
            ],
        ];

        foreach ($exemplos as $ex) {
            $html .= '<div class="border border-gray-200 rounded p-3 mt-3 bg-gray-50">';
            $html .= "<h5 class=\"font-semibold text-sm\">{$ex['titulo']}</h5>";
            $html .= "<p class=\"text-sm mt-1\">{$ex['desc']}</p>";
            $html .= '<ul class="list-none mt-2 text-sm space-y-0.5">';
            foreach ($ex['config'] as $campo => $valor) {
                $html .= "<li>→ <strong>{$campo}:</strong> {$valor}</li>";
            }
            $html .= '</ul></div>';
        }

        // ── Dica final ───────────────────────────────────────────────────────
        $html .= '<div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">';
        $html .= '<strong>💡 Dica:</strong> As perguntas com condição não satisfeita são <strong>ocultadas em tempo real</strong> durante o preenchimento. ';
        $html .= 'Além disso, suas respostas <strong>não são salvas</strong> no banco de dados, garantindo a integridade dos relatórios.';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}
