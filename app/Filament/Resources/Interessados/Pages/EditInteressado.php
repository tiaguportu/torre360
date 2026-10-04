<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\Actions\CopilotoMensagemIaAction;
use App\Filament\Resources\Interessados\Actions\DossieIaAction;
use App\Filament\Resources\Interessados\Actions\ResumoConversaIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Pessoa;
use App\Services\LeadScoreService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditInteressado extends EditRecord
{
    protected static string $resource = InteressadoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['pessoa_id'])) {
            Pessoa::whereKey($data['pessoa_id'])->update([
                'email' => $data['pessoa_email'] ?? null,
                'telefone' => $data['pessoa_telefone'] ?? null,
            ]);
        }

        unset($data['pessoa_email'], $data['pessoa_telefone']);

        return $data;
    }

    protected function afterSave(): void
    {
        LeadScoreService::recalcular($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('termometroVagas')
                ->label('Termômetro de Vagas')
                ->icon('heroicon-o-chart-bar')
                ->color('warning')
                ->modalHeading('📊 Termômetro de Ocupação e Vagas por Série')
                ->modalWidth(Width::Large)
                ->modalContent(fn () => view('filament.crm.modal-termometro-vagas', [
                    'destaqueSerieId' => $this->record->dependentes()->first()?->serie_pretendida_id,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
            DossieIaAction::make(),
            CopilotoMensagemIaAction::make(),
            ResumoConversaIaAction::make(),
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Interessado')
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

        $html = '<p>Nesta página você pode editar os dados de um interessado (lead) no sistema CRM.</p>';
        $html .= '<h3>O que você pode fazer:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>📊 Termômetro de Vagas:</strong> Consulte a ocupação real de cada série/turma em tempo real e o nível de escassez para negociar com urgência e segurança.</li>';
        $html .= '<li><strong>✨ Dossiê IA do Lead:</strong> Clique no botão roxo no cabeçalho para gerar uma análise profunda em tempo real com o Gemini, avaliando dores, momento familiar, temperatura e plano de ação comercial.</li>';
        $html .= '<li><strong>💬 Copiloto WhatsApp IA:</strong> Redija mensagens persuasivas sob medida para este lead com base em objetivos (primeiro contato, tour presencial, superar objeção, fechamento) e envie no WhatsApp com 1 clique.</li>';
        $html .= '<li><strong>🤖 Resumir WhatsApp IA:</strong> Cole conversas longas trocadas no WhatsApp com a família. O Gemini sintetiza perfil, dores, acordos e temperatura, gravando na timeline e agendando o retorno ideal.</li>';
        $html .= '<li><strong>Dados do Negócio:</strong> Atualize o status, origem, consultor responsável, temperatura e valor estimado.</li>';
        $html .= '<li><strong>Dependentes:</strong> Gerencie os alunos vinculados ao interessado.</li>';
        $html .= '<li><strong>⭐ Tour Escolar & Pesquisa NPS:</strong> Na aba "Visitas à Escola", agende visitas presenciais. Ao marcar como Realizada, o sistema gera automaticamente a pesquisa de satisfação pós-tour (NPS), permitindo o envio do link via WhatsApp e a leitura dos feedbacks da família.</li>';
        $html .= '<li><strong>📑 Documentos de Pré-Admissão com Validador IA:</strong> Na aba inferior, acompanhe o checklist de documentos. O sistema conta com pré-análise assíncrona por IA (OCR pericial com Gemini Vision) que afere legibilidade, extrai dados cruciais (CPF, RG, Data de Nascimento, Filiação), aponta divergências e permite sincronizar os dados cadastrais da família com 1 único clique, sem travamentos e em total conformidade com a LGPD.</li>';
        $html .= '<li><strong>Histórico:</strong> Na aba inferior, registre e visualize todas as interações com este lead.</li>';

        if ($user->can('Delete:Interessado')) {
            $html .= '<li><strong>Excluir:</strong> Use o botão vermelho "Excluir" para remover o lead.</li>';
        }

        $html .= '</ul>';
        $html .= '<p><strong>Dica:</strong> Mantenha o campo "Próximo Contato" sempre atualizado. O sistema alerta automaticamente quando o contato está atrasado.</p>';

        return $html;
    }
}
