<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Enums\SituacaoNota;
use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Models\Avaliacao;
use App\Services\NotaLancamentoService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LancarNotas extends EditRecord
{
    protected static string $resource = AvaliacaoResource::class;

    protected static ?string $title = 'Lançar Notas';

    public function authorizeAccess(): void
    {
        $this->authorize('lancarNotas', $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        /** @var Avaliacao $avaliacao */
        $avaliacao = $this->getRecord();
        $isHomologada = $avaliacao->isHomologada();

        return [
            Action::make('save_notes')
                ->label('Salvar Notas')
                ->icon('heroicon-o-check')
                ->action('saveNotas')
                ->color('primary')
                ->visible(! $isHomologada),

            Action::make('baixar_modelo')
                ->label('Baixar Modelo CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->baixarModeloCsv()),

            Action::make('importar_notas')
                ->label('Importar Notas (CSV)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->visible(fn (): bool => ! $this->getRecord()->isHomologada() && (auth()->user()?->can('lancarNotas', $this->getRecord()) ?? false))
                ->modalHeading('Importar Notas via Planilha CSV')
                ->modalDescription('Faça o upload do arquivo CSV preenchido para lançar ou atualizar as notas dos alunos em lote.')
                ->modalSubmitActionLabel('Processar e Salvar')
                ->form([
                    FileUpload::make('arquivo_csv')
                        ->label('Arquivo CSV de Notas')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'text/x-csv', 'application/csv'])
                        ->disk('local')
                        ->directory('temp_csv_imports')
                        ->required()
                        ->helperText('O arquivo deve conter as colunas: matricula_id, aluno_nome, nota, situacao. Você pode utilizar o "Baixar Modelo CSV" como base.'),
                ])
                ->action(fn (array $data) => $this->processarImportacaoCsv($data['arquivo_csv'] ?? null)),

            Action::make('homologar')
                ->label('Homologar Fechamento')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->visible(fn (): bool => ! $this->getRecord()->isHomologada() && (auth()->user()?->can('homologar', $this->getRecord()) ?? false))
                ->requiresConfirmation()
                ->modalHeading('Homologar Fechamento da Avaliação')
                ->modalDescription('Ao homologar a avaliação, o lançamento de notas será encerrado e bloqueado contra alterações. Deseja continuar?')
                ->modalSubmitActionLabel('Sim, homologar fechamento')
                ->action(function () {
                    /** @var Avaliacao $record */
                    $record = $this->getRecord();
                    $record->homologar(auth()->user());

                    Notification::make()
                        ->title('Avaliação homologada com sucesso!')
                        ->body('O fechamento de notas foi registrado e a edição está bloqueada.')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),

            Action::make('desbloquear')
                ->label('Desbloquear Avaliação')
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->isHomologada() && (auth()->user()?->can('homologar', $this->getRecord()) ?? false))
                ->requiresConfirmation()
                ->modalHeading('Desbloquear Avaliação')
                ->modalDescription('Deseja reabrir o lançamento de notas desta avaliação para correções? O status de homologação será removido temporariamente.')
                ->modalSubmitActionLabel('Sim, desbloquear')
                ->action(function () {
                    /** @var Avaliacao $record */
                    $record = $this->getRecord();
                    $record->desbloquear();

                    Notification::make()
                        ->title('Avaliação desbloqueada com sucesso!')
                        ->body('O lançamento de notas está novamente liberado para edições.')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),

            Action::make('corrigir_gabarito')
                ->label('Corrigir via Gabarito / Câmera')
                ->icon('heroicon-o-camera')
                ->color('gray')
                ->visible(fn (): bool => ! $this->getRecord()->isHomologada() && (auth()->user()?->can('corrigirGabarito', $this->getRecord()) ?? false))
                ->url(fn (): string => AvaliacaoResource::getUrl('corrigir-gabarito', ['record' => $this->getRecord()])),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Lançamento de Notas')
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('🔒 Avaliação Homologada')
                    ->schema([
                        Placeholder::make('alerta_homologacao')
                            ->label('')
                            ->content(fn (?Model $record): string => sprintf(
                                'Esta avaliação foi homologada e encerrada em %s%s. As notas estão protegidas contra alterações.',
                                $record?->homologada_em ? Carbon::parse($record->homologada_em)->format('d/m/Y H:i') : '-',
                                $record?->homologadaPor ? ' por '.$record->homologadaPor->name : ''
                            )),
                    ])
                    ->visible(fn (?Model $record): bool => (bool) $record?->isHomologada())
                    ->collapsible(false),

                Section::make('Informações da Avaliação')
                    ->schema([
                        Placeholder::make('etapa_avaliativa_info')
                            ->label('Etapa Avaliativa')
                            ->content(fn (?Model $record): string => $record?->etapaAvaliativa?->nome ?? '-'),
                        Placeholder::make('turma_info')
                            ->label('Turma')
                            ->content(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                        Placeholder::make('disciplina_info')
                            ->label('Disciplina')
                            ->content(fn (?Model $record): string => $record?->disciplina?->nome ?? '-'),
                        Placeholder::make('professor_info')
                            ->label('Professor')
                            ->content(fn (?Model $record): string => $record?->professor?->nome ?? '-'),
                        Placeholder::make('categoria_info')
                            ->label('Categoria')
                            ->content(fn (?Model $record): string => $record?->categoria?->nome ?? '-'),
                        Placeholder::make('data_prevista_info')
                            ->label('Data Prevista')
                            ->content(fn (?Model $record): string => $record?->data_prevista ? Carbon::parse($record->data_prevista)->format('d/m/Y') : '-'),
                        Placeholder::make('nota_maxima_info')
                            ->label('Nota Máxima')
                            ->content(fn (?Model $record): string => $record?->nota_maxima ?? '-'),
                    ])->columns(3),

                Section::make('Estatísticas da Turma e Desempenho')
                    ->schema([
                        Placeholder::make('stat_media')
                            ->label('Média da Turma')
                            ->content(function (?Model $record): string {
                                if (! $record) {
                                    return '-';
                                }
                                $stats = app(NotaLancamentoService::class)->calcularEstatisticas($record);

                                return $stats['media'] !== null ? number_format($stats['media'], 2, ',', '.') : 'Sem notas lançadas';
                            }),
                        Placeholder::make('stat_participacao')
                            ->label('Taxa de Participação')
                            ->content(function (?Model $record): string {
                                if (! $record) {
                                    return '-';
                                }
                                $stats = app(NotaLancamentoService::class)->calcularEstatisticas($record);

                                return sprintf('%s%% (%d de %d alunos)', number_format($stats['taxa_participacao'], 1, ',', '.'), $stats['total_resolvidas'], $stats['total_alunos']);
                            }),
                        Placeholder::make('stat_extremos')
                            ->label('Maior / Menor Nota')
                            ->content(function (?Model $record): string {
                                if (! $record) {
                                    return '-';
                                }
                                $stats = app(NotaLancamentoService::class)->calcularEstatisticas($record);

                                if ($stats['maior_nota'] === null) {
                                    return '-';
                                }

                                return sprintf('Maior: %s | Menor: %s', number_format($stats['maior_nota'], 2, ',', '.'), number_format($stats['menor_nota'], 2, ',', '.'));
                            }),
                        Placeholder::make('stat_pendentes')
                            ->label('Pendências')
                            ->content(function (?Model $record): string {
                                if (! $record) {
                                    return '-';
                                }
                                $stats = app(NotaLancamentoService::class)->calcularEstatisticas($record);

                                return $stats['total_pendentes'] > 0
                                    ? sprintf('%d aluno(s) sem lançamento', $stats['total_pendentes'])
                                    : 'Todas as notas resolvidas ✅';
                            }),
                    ])
                    ->columns(4),

                Section::make('Notas dos Alunos')
                    ->schema([
                        Repeater::make('notas_alunos')
                            ->label('')
                            ->schema([
                                TextInput::make('aluno_nome')
                                    ->label('Aluno')
                                    ->disabled()
                                    ->columnSpan(2),
                                Hidden::make('matricula_id'),
                                Select::make('situacao')
                                    ->label('Situação')
                                    ->options(SituacaoNota::class)
                                    ->placeholder('Com nota')
                                    ->disabled(fn () => $this->getRecord()->isHomologada())
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, $state) => $state ? $set('valor', null) : null)
                                    ->columnSpan(1),
                                TextInput::make('valor')
                                    ->label('Nota')
                                    ->disabled(fn (Get $get) => $this->getRecord()->isHomologada() || filled($get('situacao')))
                                    ->dehydrated()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn () => $this->getRecord()->nota_maxima ?? 10)
                                    ->validationMessages([
                                        'max' => 'A nota não pode ser maior que a nota máxima da avaliação (:max).',
                                    ])
                                    ->columnSpan(1)
                                    ->live()
                                    ->extraInputAttributes(['wire:keydown.enter' => 'saveNotas']),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['notas_alunos'] = app(NotaLancamentoService::class)->estadoNotasParaGrade($this->getRecord());

        return $data;
    }

    public function saveNotas(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $avaliacao = $this->getRecord();

        if ($avaliacao->isHomologada()) {
            Notification::make()
                ->title('Avaliação homologada')
                ->body('Esta avaliação está encerrada e homologada. O lançamento de notas está bloqueado.')
                ->danger()
                ->send();

            return;
        }

        $notasAlunos = $this->data['notas_alunos'] ?? [];

        try {
            app(NotaLancamentoService::class)->salvarNotas($avaliacao, $notasAlunos);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Erro ao salvar nota')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        if ($shouldSendSavedNotification) {
            Notification::make()
                ->title('Notas salvas com sucesso!')
                ->success()
                ->send();
        }

        if ($shouldRedirect) {
            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    private function baixarModeloCsv(): StreamedResponse
    {
        /** @var Avaliacao $avaliacao */
        $avaliacao = $this->getRecord();
        $linhas = app(NotaLancamentoService::class)->estadoNotasParaGrade($avaliacao);
        $turmaNome = Str::slug($avaliacao->turma?->nome ?? 'turma');

        return response()->streamDownload(function () use ($linhas) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM para compatibilidade com Microsoft Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['matricula_id', 'aluno_nome', 'nota', 'situacao'], ';');

            foreach ($linhas as $linha) {
                $valor = $linha['valor'] !== null ? str_replace('.', ',', (string) $linha['valor']) : '';
                fputcsv($handle, [
                    $linha['matricula_id'],
                    $linha['aluno_nome'],
                    $valor,
                    $linha['situacao'] ?? '',
                ], ';');
            }

            fclose($handle);
        }, "modelo_notas_avaliacao_{$avaliacao->id}_{$turmaNome}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function processarImportacaoCsv(?string $arquivoCaminho): void
    {
        if (! $arquivoCaminho) {
            Notification::make()
                ->title('Nenhum arquivo enviado')
                ->danger()
                ->send();

            return;
        }

        $caminhoCompleto = Storage::disk('local')->path($arquivoCaminho);
        $avaliacao = $this->getRecord();

        try {
            $service = app(NotaLancamentoService::class);
            $linhas = $service->lerLinhasCsv($caminhoCompleto);

            if (empty($linhas)) {
                throw new \InvalidArgumentException('O arquivo CSV enviado está vazio.');
            }

            $total = $service->importarNotasCsv($avaliacao, $linhas);

            Notification::make()
                ->title('Importação concluída com sucesso!')
                ->body("{$total} notas de alunos foram importadas e salvas.")
                ->success()
                ->send();

            $this->fillForm();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao importar CSV')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        } finally {
            if (Storage::disk('local')->exists($arquivoCaminho)) {
                Storage::disk('local')->delete($arquivoCaminho);
            }
        }
    }

    protected function getFormActions(): array
    {
        return [];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        /** @var Avaliacao $record */
        $record = $this->getRecord();

        $canLancar = $user->can('lancarNotas', $record);
        $canHomologar = $user->can('homologar', $record);

        $html = '<p>Esta página é destinada ao lançamento e acompanhamento das notas dos alunos da turma para esta avaliação.</p>';

        $html .= '<h3>Painel de Estatísticas e Desempenho</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Média da Turma:</strong> Exibe a média aritmética calculada com base em todas as notas numéricas lançadas.</li>';
        $html .= '<li><strong>Taxa de Participação:</strong> Percentual de alunos que já possuem nota ou justificativa lançada.</li>';
        $html .= '<li><strong>Maior / Menor Nota:</strong> Aponta a maior e a menor nota atribuídas na turma.</li>';
        $html .= '<li><strong>Pendências:</strong> Quantidade de matrículas ativas que ainda necessitam de lançamento.</li>';
        $html .= '</ul>';

        $html .= '<h3>Importação e Exportação por Planilha (CSV)</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Baixar Modelo CSV:</strong> Gera uma planilha com a lista pré-preenchida de todos os alunos da turma para digitação offline das notas.</li>';
        $html .= '<li><strong>Importar Notas (CSV):</strong> Envia o arquivo CSV preenchido para gravação em lote de todas as notas e justificativas no sistema.</li>';
        $html .= '</ul>';

        $html .= '<h3>Homologação e Fechamento</h3>';
        $html .= '<ul>';
        if ($canHomologar) {
            $html .= '<li><strong>Homologar Fechamento:</strong> Permite trancar a avaliação após a conclusão, protegendo as notas contra alterações inadvertidas.</li>';
            $html .= '<li><strong>Desbloquear Avaliação:</strong> Caso haja necessidade de retificação após homologação, um coordenador ou administrador pode reabrir o lançamento.</li>';
        }
        $html .= '</ul>';

        $html .= '<h3>Instruções de Preenchimento Manual:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Planilha de Notas:</strong> Digite a nota de cada aluno respeitando a nota máxima permitida. Pressionar Enter salva e valida a linha.</li>';

        if ($canLancar) {
            $html .= '<li><strong>Salvar Notas:</strong> Grava as alterações manuais e redireciona para a listagem.</li>';
        }

        $html .= '<li><strong>Situação (Faltou / Não se aplica):</strong> Encerra a pendência do aluno justificadamente, sem atribuir nota numérica nem afetar a média.</li>';
        $html .= '</ul>';

        return $html;
    }
}
