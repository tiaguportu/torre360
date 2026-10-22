<?php

declare(strict_types=1);

namespace App\Filament\Resources\CancelamentosMatricula\Tables;

use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\StatusCancelamentoMatricula;
use App\Models\CancelamentoMatricula;
use App\Services\CancelamentoMatriculaService;
use App\Support\TiposArquivo;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CancelamentosMatriculaTable
{
    /**
     * @var array<string, string>
     */
    private const ROTULOS_DECISAO = [
        CancelamentoMatriculaService::MANTER => 'Manter (a família paga ou já pagou)',
        CancelamentoMatriculaService::CANCELAR => 'Cancelar a fatura',
        CancelamentoMatriculaService::DEVOLVER => 'Cancelar e devolver o que foi pago',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('matricula.turma.nome')
                    ->label('Turma'),
                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->formatStateUsing(fn (MotivoCancelamentoMatricula $state): string => $state->getLabel()),
                TextColumn::make('data_efetiva')
                    ->label('Saída em')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
                TextColumn::make('valor_devido')
                    ->label('Ainda deve')
                    ->state(fn (CancelamentoMatricula $record): ?float => $record->temDistrato() ? (float) $record->valor_devido : null)
                    ->money('BRL')
                    ->placeholder('—'),
                TextColumn::make('valor_a_devolver')
                    ->label('A devolver')
                    ->state(fn (CancelamentoMatricula $record): ?float => $record->temDistrato() && (float) $record->valor_a_devolver > 0 ? (float) $record->valor_a_devolver : null)
                    ->money('BRL')
                    ->description(fn (CancelamentoMatricula $record): ?string => $record->devolucaoPendente()
                        ? 'Devolução pendente'
                        : ($record->devolucao_realizada_em ? 'Devolvido em '.$record->devolucao_realizada_em->format('d/m/Y') : null))
                    ->placeholder('—'),
                TextColumn::make('origem')
                    ->label('Origem')
                    ->formatStateUsing(fn (OrigemCancelamentoMatricula $state): string => $state->getLabel())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Pedido em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusCancelamentoMatricula::class)
                    ->multiple(),
                SelectFilter::make('origem')
                    ->label('Origem')
                    ->options(OrigemCancelamentoMatricula::class),
            ])
            ->recordActions([
                self::situacaoFinanceiraAction(),
                self::aprovarAction(),
                self::concluirAction(),
                ActionGroup::make([
                    self::recusarAction(),
                    self::distratoAction(),
                    self::anexarDistratoAction(),
                    self::verDistratoAssinadoAction(),
                    self::registrarDevolucaoAction(),
                    self::encerrarAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile();
    }

    // --- Ações -------------------------------------------------------------------------------------------------------

    private static function situacaoFinanceiraAction(): Action
    {
        return Action::make('situacao')
            ->label('Situação financeira')
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->slideOver()
            ->modalHeading(fn (CancelamentoMatricula $record): string => 'Situação financeira — '.($record->matricula->pessoa?->nome ?? 'aluno'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function (CancelamentoMatricula $record) {
                $service = app(CancelamentoMatriculaService::class);
                $linhas = $record->estaAberto() ? $service->proposta($record) : collect();

                return view('filament.cancelamentos.situacao-financeira', [
                    'cancelamento' => $record,
                    'linhas' => $linhas,
                    'decisoes' => $record->decisoes ?? [],
                    'rotulos' => self::ROTULOS_DECISAO,
                    'totais' => $record->estaSolicitado()
                        ? $service->totais($linhas, [])
                        : ['valor_devido' => (float) $record->valor_devido, 'valor_a_devolver' => (float) $record->valor_a_devolver],
                ]);
            });
    }

    private static function aprovarAction(): Action
    {
        return Action::make('aprovar')
            ->label('Aprovar')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (CancelamentoMatricula $record): bool => (bool) auth()->user()?->can('aprovar', $record))
            ->modalHeading(fn (CancelamentoMatricula $record): string => 'Aprovar cancelamento — '.($record->matricula->pessoa?->nome ?? 'aluno'))
            ->modalDescription('Confira o destino de cada fatura. Depois de aprovar, o distrato pode ser impresso e assinado.')
            ->modalWidth('3xl')
            ->schema(fn (CancelamentoMatricula $record): array => self::camposDaAprovacao($record))
            ->action(function (CancelamentoMatricula $record, array $data): void {
                $decisoes = [];

                foreach ($data as $chave => $valor) {
                    if (str_starts_with((string) $chave, 'decisao_')) {
                        $decisoes[(int) substr((string) $chave, 8)] = $valor;
                    }
                }

                try {
                    app(CancelamentoMatriculaService::class)->aprovar($record, auth()->user(), [
                        'data_efetiva' => $data['data_efetiva'] ?? null,
                        'decisoes' => $decisoes,
                        'multa_rescisoria' => $data['multa_rescisoria'] ?? 0,
                        'gerar_transferencia' => (bool) ($data['gerar_transferencia'] ?? false),
                        'escola_destino' => $data['escola_destino'] ?? null,
                        'observacao' => $data['observacao'] ?? null,
                    ]);

                    Notification::make()
                        ->title('Pedido aprovado')
                        ->body('O distrato já pode ser impresso. Colha a assinatura e anexe a cópia assinada.')
                        ->success()
                        ->send();
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível aprovar')->body($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * @return list<mixed>
     */
    private static function camposDaAprovacao(CancelamentoMatricula $record): array
    {
        $service = app(CancelamentoMatriculaService::class);
        $proposta = $service->proposta($record);
        $totais = $service->totais($proposta, []);

        $campos = [
            Placeholder::make('previa')
                ->label('Pelas sugestões')
                ->content(new HtmlString(e(sprintf(
                    'A família ainda deve R$ %s (com multa e juros até hoje) e a escola devolve R$ %s.',
                    number_format($totais['valor_devido'], 2, ',', '.'),
                    number_format($totais['valor_a_devolver'], 2, ',', '.'),
                )))),
            DatePicker::make('data_efetiva')
                ->label('Data de saída')
                ->helperText('O mês da saída é cobrado inteiro. Mudar a data não refaz as sugestões abaixo.')
                ->native(false)
                ->default($record->data_efetiva)
                ->required(),
        ];

        foreach ($proposta as $linha) {
            $campos[] = Select::make('decisao_'.$linha['fatura_id'])
                ->label(sprintf('%s — vence %s (%s)', $linha['descricao'], $linha['vencimento']->format('d/m/Y'), $linha['status']->getLabel()))
                ->helperText(sprintf(
                    'Valor R$ %s · pago R$ %s · em aberto R$ %s%s. %s',
                    number_format($linha['valor'], 2, ',', '.'),
                    number_format($linha['valor_pago'], 2, ',', '.'),
                    number_format($linha['total_em_aberto'], 2, ',', '.'),
                    $linha['encargos'] > 0 ? ' (com multa e juros de R$ '.number_format($linha['encargos'], 2, ',', '.').')' : '',
                    $linha['sugestao'],
                ))
                ->options(array_intersect_key(self::ROTULOS_DECISAO, array_flip($linha['opcoes'])))
                ->default($linha['decisao'])
                ->selectablePlaceholder(false)
                ->required();
        }

        return [
            ...$campos,
            TextInput::make('multa_rescisoria')
                ->label('Multa rescisória (R$)')
                ->helperText('Cobre só se o contrato prever. Multa abusiva pode ser anulada (CDC, art. 51, IV). Gera uma fatura ao concluir.')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->prefix('R$'),
            Toggle::make('gerar_transferencia')
                ->label('Emitir a Declaração de Transferência ao concluir')
                ->default($record->gerar_transferencia)
                ->live(),
            TextInput::make('escola_destino')
                ->label('Escola de destino')
                ->default($record->escola_destino)
                ->maxLength(160)
                ->visible(fn (Get $get): bool => (bool) $get('gerar_transferencia')),
            Textarea::make('observacao')
                ->label('Observação interna (opcional)')
                ->default($record->observacao)
                ->rows(2)
                ->maxLength(2000),
        ];
    }

    private static function recusarAction(): Action
    {
        return Action::make('recusar')
            ->label('Recusar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (CancelamentoMatricula $record): bool => (bool) auth()->user()?->can('aprovar', $record))
            ->modalHeading('Recusar o pedido')
            ->modalDescription('A família lê o motivo no Portal. Seja claro e educado.')
            ->schema([
                Textarea::make('motivo_recusa')
                    ->label('Motivo da recusa')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (CancelamentoMatricula $record, array $data): void {
                try {
                    app(CancelamentoMatriculaService::class)->recusar($record, auth()->user(), (string) $data['motivo_recusa']);

                    Notification::make()->title('Pedido recusado')->success()->send();
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível recusar')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private static function distratoAction(): Action
    {
        return Action::make('distrato')
            ->label('Distrato (PDF)')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn (CancelamentoMatricula $record): bool => $record->temDistrato())
            ->url(fn (CancelamentoMatricula $record): string => route('cancelamentos-matricula.distrato', ['cancelamento' => $record->id]))
            ->openUrlInNewTab();
    }

    private static function anexarDistratoAction(): Action
    {
        return Action::make('anexar_distrato')
            ->label('Anexar distrato assinado')
            ->icon('heroicon-o-paper-clip')
            ->color('info')
            ->visible(fn (CancelamentoMatricula $record): bool => (bool) auth()->user()?->can('anexarDistrato', $record))
            ->modalHeading('Anexar o distrato assinado')
            ->modalDescription('Envie o PDF ou a foto do distrato assinado pela família. O arquivo fica guardado em área privada.')
            ->schema([
                FileUpload::make('arquivo')
                    ->label('Distrato assinado (PDF ou foto)')
                    ->acceptedFileTypes(TiposArquivo::documentos())
                    ->storeFiles(false)
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (CancelamentoMatricula $record, array $data): void {
                $arquivo = Arr::first(Arr::wrap($data['arquivo'] ?? null));

                if (! $arquivo instanceof TemporaryUploadedFile) {
                    Notification::make()->title('Arquivo não recebido')->danger()->send();

                    return;
                }

                try {
                    app(CancelamentoMatriculaService::class)->anexarDistratoAssinado($record, $arquivo, auth()->user());

                    Notification::make()->title('Distrato assinado anexado')->success()->send();
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível anexar')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private static function verDistratoAssinadoAction(): Action
    {
        return Action::make('ver_distrato_assinado')
            ->label('Ver distrato assinado')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (CancelamentoMatricula $record): bool => $record->temDistratoAssinado())
            ->url(fn (CancelamentoMatricula $record): string => route('documentos.visualizar', ['path' => $record->distrato_assinado_path]))
            ->openUrlInNewTab();
    }

    private static function concluirAction(): Action
    {
        return Action::make('concluir')
            ->label('Concluir')
            ->icon('heroicon-o-flag')
            ->color('primary')
            ->visible(fn (CancelamentoMatricula $record): bool => (bool) auth()->user()?->can('concluir', $record))
            ->requiresConfirmation()
            ->modalHeading('Concluir o cancelamento')
            ->modalDescription('Isso cancela a matrícula, cancela as faturas marcadas, cobra a multa rescisória (se houver) e, se marcado, abre a saída em Transferências. Não dá para desfazer por aqui.')
            ->action(function (CancelamentoMatricula $record): void {
                try {
                    $resumo = app(CancelamentoMatriculaService::class)->concluir($record, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

                    return;
                }

                $corpo = "Matrícula cancelada e {$resumo['faturas_canceladas']} fatura(s) cancelada(s).";

                if ($resumo['multa_fatura_id']) {
                    $corpo .= ' A fatura da multa rescisória foi gerada.';
                }

                if ($resumo['transferencia'] === 'emitida') {
                    $corpo .= ' A Declaração de Transferência foi emitida.';
                }

                $notificacao = Notification::make()->title('Cancelamento concluído')->body(trim($corpo.' '.implode(' ', $resumo['avisos'])));
                $resumo['avisos'] === [] ? $notificacao->success() : $notificacao->warning()->persistent();
                $notificacao->send();
            });
    }

    private static function registrarDevolucaoAction(): Action
    {
        return Action::make('registrar_devolucao')
            ->label('Registrar devolução')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (CancelamentoMatricula $record): bool => (bool) auth()->user()?->can('registrarDevolucao', $record))
            ->modalHeading('Registrar a devolução')
            ->modalDescription('Marque a data em que o dinheiro foi devolvido à família. A saída do dinheiro se lança em Movimentação Bancária.')
            ->schema([
                DatePicker::make('data')
                    ->label('Devolvido em')
                    ->native(false)
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
            ])
            ->action(function (CancelamentoMatricula $record, array $data): void {
                try {
                    app(CancelamentoMatriculaService::class)->registrarDevolucao($record, Carbon::parse($data['data']), auth()->user());

                    Notification::make()->title('Devolução registrada')->success()->send();
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível registrar')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private static function encerrarAction(): Action
    {
        return Action::make('encerrar')
            ->label('Encerrar pedido')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (CancelamentoMatricula $record): bool => (bool) auth()->user()?->can('encerrar', $record))
            ->requiresConfirmation()
            ->modalHeading('Encerrar o pedido')
            ->modalDescription('Use quando a família desistiu. A matrícula e as faturas não mudam.')
            ->action(function (CancelamentoMatricula $record): void {
                try {
                    app(CancelamentoMatriculaService::class)->retirar($record, auth()->user());

                    Notification::make()->title('Pedido encerrado')->success()->send();
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível encerrar')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
