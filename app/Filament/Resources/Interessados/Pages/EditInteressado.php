<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\Actions\BattlecardAction;
use App\Filament\Resources\Interessados\Actions\CopilotoMensagemIaAction;
use App\Filament\Resources\Interessados\Actions\DossieIaAction;
use App\Filament\Resources\Interessados\Actions\ResumoConversaIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\Pessoa;
use App\Services\LeadDuplicadoDetectorService;
use App\Services\LeadMesclagemService;
use App\Services\LeadScoreService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
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
            BattlecardAction::make(),
            DossieIaAction::make(),
            CopilotoMensagemIaAction::make(),
            ResumoConversaIaAction::make(),
            Action::make('mesclarLead')
                ->label('Mesclar Duplicados')
                ->icon('heroicon-o-arrows-pointing-in')
                ->color('warning')
                ->badge(fn (): ?int => ($c = app(LeadDuplicadoDetectorService::class)->contarDuplicados($this->record)) > 0 ? $c : null)
                ->visible(fn (): bool => auth()->user()?->can('Update:Interessado') ?? false)
                ->modalHeading('Mesclar Lead Duplicado')
                ->modalDescription('Esta ação une os cadastros duplicados, preservando todos os contatos, visitas, pesquisas de satisfação, documentos, dependentes e tokens sem perda de dados.')
                ->form([
                    Select::make('lead_origem_id')
                        ->label('Lead a ser absorvido')
                        ->helperText('Selecione qual lead será mesclado neste registro.')
                        ->options(function (): array {
                            $duplicados = app(LeadDuplicadoDetectorService::class)->detectar($this->record);
                            if ($duplicados->isNotEmpty()) {
                                return $duplicados->mapWithKeys(function ($item) {
                                    $outro = $item['interessado'];
                                    $motivosTxt = implode(', ', $item['motivos']);
                                    $statusTxt = $outro->status?->nome ?? 'Sem etapa';
                                    $dataTxt = $outro->created_at?->format('d/m/Y') ?? '';

                                    return [$outro->id => "Lead #{$outro->id} - {$outro->pessoa?->nome} ({$statusTxt} | Criado: {$dataTxt} | Motivo: {$motivosTxt})"];
                                })->all();
                            }

                            return Interessado::query()
                                ->where('id', '!=', $this->record->id)
                                ->with(['pessoa', 'status'])
                                ->latest('id')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Interessado $outro) => [
                                    $outro->id => "Lead #{$outro->id} - {$outro->pessoa?->nome} (".($outro->status?->nome ?? 'Sem etapa').')',
                                ])
                                ->all();
                        })
                        ->searchable()
                        ->required(),
                    Select::make('preferir_destino')
                        ->label('Qual lead deve ser preservado como principal?')
                        ->options([
                            'mais_antigo' => 'Preservar o lead mais antigo (Padrão)',
                            'este' => "Preservar este lead (#{$this->record->id})",
                            'outro' => 'Preservar o outro lead selecionado',
                        ])
                        ->default('mais_antigo')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $outroLead = Interessado::findOrFail($data['lead_origem_id']);

                    $preferir = match ($data['preferir_destino']) {
                        'este' => $this->record,
                        'outro' => $outroLead,
                        default => null,
                    };

                    try {
                        $preservado = app(LeadMesclagemService::class)->mesclar(
                            $this->record,
                            $outroLead,
                            auth()->id(),
                            $preferir
                        );

                        Notification::make()
                            ->title('Leads mesclados com sucesso!')
                            ->body("Os registros foram consolidados no Lead #{$preservado->id}. Visitas, documentos e históricos foram unificados.")
                            ->success()
                            ->send();

                        if ($preservado->id !== $this->record->id) {
                            $this->redirect(InteressadoResource::getUrl('edit', ['record' => $preservado]));
                        } else {
                            $this->refreshFormData(['dependentes', 'observacoes']);
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erro ao mesclar leads')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
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
        $html .= '<li><strong>🛡️ Battlecards & Objeções:</strong> Acesse a inteligência competitiva da escola contra colégios concorrentes, matriz de contorno de objeções com scripts verbais, perguntas de virada e o radar de motivos de perda.</li>';
        $html .= '<li><strong>✨ Dossiê IA do Lead:</strong> Clique no botão roxo no cabeçalho para gerar uma análise profunda em tempo real com o Gemini, avaliando dores, momento familiar, temperatura e plano de ação comercial.</li>';
        $html .= '<li><strong>💬 Copiloto WhatsApp IA:</strong> Redija mensagens persuasivas sob medida para este lead com base em objetivos (primeiro contato, tour presencial, superar objeção, fechamento) e envie no WhatsApp com 1 clique.</li>';
        $html .= '<li><strong>🤖 Resumo IA de Conversa (WhatsApp):</strong> Cole conversas longas trocadas no WhatsApp com a família e/ou anexe os <strong>áudios</strong> (mensagens de voz: .opus, .ogg, .mp3, .m4a, .wav, .aac, .flac ou .aiff; até 5 arquivos de 10 MB, na ordem em que ocorreram). Com áudio, o texto colado é opcional. O Gemini ouve os áudios, sintetiza perfil, dores, acordos e temperatura (considerando também o tom de voz), gravando na timeline e agendando o retorno ideal. Os arquivos são apagados logo após a análise, e a análise com áudio demora mais que com texto.</li>';
        $html .= '<li><strong>Dados do Negócio:</strong> Atualize o status, origem, consultor responsável, temperatura e valor estimado.</li>';
        $html .= '<li><strong>Dependentes:</strong> Gerencie os alunos vinculados ao interessado.</li>';
        $html .= '<li><strong>📱 Linha do Tempo Omnichannel 360°:</strong> Visualize a jornada completa da família em um feed interativo unificado (contatos, WhatsApp, visitas escolares, NPS, documentos periciados por IA e mudanças no funil). Permite gravar interações rápidas no topo e recalcula o Lead Score na hora!</li>';
        $html .= '<li><strong>⭐ Tour Escolar & Pesquisa NPS:</strong> Na aba "Visitas à Escola", agende visitas presenciais. Ao marcar como Realizada, o sistema gera automaticamente a pesquisa de satisfação pós-tour (NPS), permitindo o envio do link via WhatsApp e a leitura dos feedbacks da família.</li>';
        $html .= '<li><strong>📑 Documentos de Pré-Admissão com Validador IA:</strong> Na aba inferior, acompanhe o checklist de documentos. O sistema conta com pré-análise assíncrona por IA (OCR pericial com Gemini Vision) que afere legibilidade, extrai dados cruciais (CPF, RG, Data de Nascimento, Filiação), aponta divergências e permite sincronizar os dados cadastrais da família com 1 único clique, sem travamentos e em total conformidade com a LGPD.</li>';
        $html .= '<li><strong>Histórico Tradicional:</strong> Na aba "Histórico de Contatos", acesse a listagem tabular detalhada de todas as interações do lead.</li>';

        if ($user->can('Update:Interessado')) {
            $html .= '<li><strong>🔀 Detecção de Duplicados & Mesclagem:</strong> O sistema detecta automaticamente possíveis duplicados com base em telefone, CPF, e-mail ou aluno dependente em comum (nome e nascimento). O botão "Mesclar Duplicados" no topo permite consolidar os cadastros de forma atômica e segura, preservando todo o histórico, visitas, pesquisas NPS, documentos e dependentes sem perda de dados.</li>';
            $html .= '<li><strong>⏱️ SLA de 1ª Resposta Comercial:</strong> O sistema monitora o tempo até o primeiro contato humano, considerando estritamente o expediente comercial (segunda a sexta, 08h às 18h). Prazos customizados podem ser ajustados por lead (padrão de 120 min úteis). Ao estourar, o consultor recebe um alerta imediato no sino do painel.</li>';
        }

        if ($user->can('Delete:Interessado')) {
            $html .= '<li><strong>Excluir:</strong> Use o botão vermelho "Excluir" para remover o lead.</li>';
        }

        $html .= '</ul>';
        $html .= '<p><strong>Dica:</strong> Mantenha o campo "Próximo Contato" sempre atualizado. O sistema alerta automaticamente quando o contato está atrasado.</p>';

        return $html;
    }
}
