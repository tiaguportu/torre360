<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessosFuncionario\RelationManagers;

use App\Enums\CategoriaItemProcessoFuncionario;
use App\Enums\ContextoDocumento;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Models\ItemProcessoFuncionario;
use App\Models\ProcessoFuncionario;
use App\Models\TemplateDocumento;
use App\Services\Documentos\AssinaturaDocumentoService;
use App\Services\Documentos\SignatariosSugeridosService;
use App\Services\ProcessoFuncionarioService;
use App\Support\TiposArquivo;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Providências do processo de admissão ou desligamento. Os botões só aparecem enquanto o processo está em andamento.
 */
class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Providências';

    protected static ?string $modelLabel = 'providência';

    protected static ?string $pluralModelLabel = 'providências';

    /**
     * As ações são nossas e conferem a política do processo; a tela de consulta do processo precisa delas ativas.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titulo')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['concluidoPor', 'documentosAssinatura']))
            ->columns([
                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge(),
                TextColumn::make('titulo')
                    ->label('Providência')
                    ->description(fn (ItemProcessoFuncionario $record): ?string => $record->orientacao)
                    ->wrap()
                    ->searchable(),
                IconColumn::make('obrigatorio')
                    ->label('Obrigatória')
                    ->boolean(),
                TextColumn::make('prazo')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn (ItemProcessoFuncionario $record): ?string => $record->estaAtrasado() ? 'danger' : null),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->state(fn (ItemProcessoFuncionario $record): string => self::situacao($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Feito' => 'success',
                        'Não se aplica' => 'gray',
                        'Atrasada' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('concluidoPor.name')
                    ->label('Feito por')
                    ->description(fn (ItemProcessoFuncionario $record): ?string => $record->concluido_em?->format('d/m/Y H:i'))
                    ->placeholder('—'),
                TextColumn::make('observacao')
                    ->label('Observação')
                    ->limit(60)
                    ->tooltip(fn (ItemProcessoFuncionario $record): ?string => $record->observacao)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('categoria')
                    ->label('Categoria')
                    ->options(CategoriaItemProcessoFuncionario::class),
                TernaryFilter::make('resolvida')
                    ->label('Resolvida')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('concluido_em'),
                        false: fn (Builder $query) => $query->whereNull('concluido_em'),
                    ),
            ])
            ->headerActions([
                $this->adicionarAction(),
            ])
            ->recordActions([
                $this->marcarAction(),
                $this->assinaturaEletronicaAction(),
                $this->dispensarAction(),
                $this->anexarAction(),
                $this->reabrirAction(),
                $this->verArquivoAction(),
                $this->verAssinaturaEletronicaAction(),
            ])
            ->defaultSort('ordem')
            ->paginated(false)
            ->stackedOnMobile();
    }

    private static function situacao(ItemProcessoFuncionario $item): string
    {
        return match (true) {
            $item->nao_se_aplica => 'Não se aplica',
            $item->estaResolvido() => 'Feito',
            $item->estaAtrasado() => 'Atrasada',
            default => 'Pendente',
        };
    }

    private function podeMexer(): bool
    {
        /** @var ProcessoFuncionario $processo */
        $processo = $this->getOwnerRecord();

        return (bool) auth()->user()?->can('update', $processo);
    }

    private function arquivoDaAcao(array $data): ?TemporaryUploadedFile
    {
        $arquivo = Arr::first(Arr::wrap($data['arquivo'] ?? null));

        return $arquivo instanceof TemporaryUploadedFile ? $arquivo : null;
    }

    private function campoArquivo(bool $obrigatorio): FileUpload
    {
        return FileUpload::make('arquivo')
            ->label($obrigatorio ? 'Comprovante (PDF ou foto)' : 'Comprovante (PDF ou foto, opcional)')
            ->acceptedFileTypes(TiposArquivo::documentos())
            ->storeFiles(false)
            ->maxSize(10240)
            ->required($obrigatorio);
    }

    private function adicionarAction(): Action
    {
        return Action::make('adicionar')
            ->label('Acrescentar providência')
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => $this->podeMexer())
            ->modalHeading('Acrescentar uma providência')
            ->schema([
                TextInput::make('titulo')
                    ->label('Providência')
                    ->required()
                    ->maxLength(200),
                Select::make('categoria')
                    ->label('Categoria')
                    ->options(CategoriaItemProcessoFuncionario::class)
                    ->default(CategoriaItemProcessoFuncionario::Outro->value)
                    ->required(),
                Textarea::make('orientacao')
                    ->label('Orientação (opcional)')
                    ->rows(2)
                    ->maxLength(300),
                DatePicker::make('prazo')
                    ->label('Prazo (opcional)')
                    ->native(false),
                Toggle::make('obrigatorio')
                    ->label('Obrigatória para concluir o processo')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                try {
                    app(ProcessoFuncionarioService::class)->adicionarItem($this->getOwnerRecord(), $data, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível acrescentar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Providência acrescentada')->success()->send();
            });
    }

    private function marcarAction(): Action
    {
        return Action::make('marcar')
            ->label('Marcar como feito')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ItemProcessoFuncionario $record): bool => ! $record->estaResolvido() && $this->podeMexer())
            ->modalHeading(fn (ItemProcessoFuncionario $record): string => $record->titulo)
            ->schema([
                $this->campoArquivo(false),
                Textarea::make('observacao')
                    ->label('Observação (opcional)')
                    ->rows(2)
                    ->maxLength(300),
            ])
            ->action(function (ItemProcessoFuncionario $record, array $data): void {
                try {
                    app(ProcessoFuncionarioService::class)->marcarItem($record, auth()->user(), $data['observacao'] ?? null, $this->arquivoDaAcao($data));
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível marcar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Providência concluída')->success()->send();
            });
    }

    /**
     * Monta o documento do funcionário (contrato de trabalho, termo) ligado a esta providência e leva à página dele, onde
     * se confere e se envia. Quando todos assinam, a providência é concluída sozinha, com a cópia certificada.
     */
    private function assinaturaEletronicaAction(): Action
    {
        return Action::make('assinatura_eletronica')
            ->label('Assinatura eletrônica')
            ->icon('heroicon-o-pencil')
            ->color('info')
            ->visible(fn (ItemProcessoFuncionario $record): bool => ! $record->estaResolvido()
                && $this->podeMexer()
                && (bool) auth()->user()?->can('Create:DocumentoAssinatura')
                && ! $record->documentosAssinatura->contains(fn ($documento): bool => $documento->emAndamento()))
            ->modalHeading(fn (ItemProcessoFuncionario $record): string => 'Assinatura eletrônica: '.$record->titulo)
            ->modalDescription('O sistema monta o documento com o modelo escolhido e leva você a ele, onde confere o texto e os assinantes antes de enviar. Quando todos assinarem, esta providência é concluída sozinha.')
            ->modalSubmitActionLabel('Montar o documento')
            ->schema([
                Select::make('modelo')
                    ->label('Modelo')
                    ->options(fn (): array => TemplateDocumento::query()
                        ->where('is_ativo', true)
                        ->whereIn('tipo', array_map(fn (TipoTemplateDocumento $tipo): string => $tipo->value, array_filter(TipoTemplateDocumento::cases(), fn (TipoTemplateDocumento $tipo): bool => $tipo->contexto() === ContextoDocumento::Funcionario)))
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->all())
                    ->helperText('Só aparecem modelos de funcionário ativos. Revise e ative o modelo em Modelos de Documentos.')
                    ->required(),
            ])
            ->action(function (ItemProcessoFuncionario $record, array $data) {
                $modelo = TemplateDocumento::query()->find($data['modelo']);

                if (! $modelo || $modelo->contexto() !== ContextoDocumento::Funcionario) {
                    Notification::make()->title('Modelo não encontrado')->danger()->send();

                    return null;
                }

                try {
                    $sugestao = app(SignatariosSugeridosService::class)->para($record);
                    $documento = app(AssinaturaDocumentoService::class)->criar($modelo, $record, $sugestao['assinantes'], auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível montar o documento')->body($e->getMessage())->danger()->persistent()->send();

                    return null;
                }

                if ($sugestao['sem_email'] !== []) {
                    Notification::make()
                        ->title('Pessoas sem e-mail cadastrado')
                        ->body(implode(', ', $sugestao['sem_email']).' não entraram na lista de assinantes.')
                        ->warning()
                        ->send();
                }

                return redirect(DocumentoAssinaturaResource::getUrl('view', ['record' => $documento]));
            });
    }

    private function verAssinaturaEletronicaAction(): Action
    {
        return Action::make('ver_assinatura_eletronica')
            ->label('Ver assinatura eletrônica')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (ItemProcessoFuncionario $record): bool => $record->documentosAssinatura->isNotEmpty() && (bool) auth()->user()?->can('view', $record->documentosAssinatura->first()))
            ->url(fn (ItemProcessoFuncionario $record): string => DocumentoAssinaturaResource::getUrl('view', ['record' => $record->documentosAssinatura->first()]));
    }

    private function dispensarAction(): Action
    {
        return Action::make('dispensar')
            ->label('Não se aplica')
            ->icon('heroicon-o-no-symbol')
            ->color('gray')
            ->visible(fn (ItemProcessoFuncionario $record): bool => ! $record->estaResolvido() && $this->podeMexer())
            ->modalHeading(fn (ItemProcessoFuncionario $record): string => 'Não se aplica: '.$record->titulo)
            ->modalDescription('A providência sai da frente, mesmo sendo obrigatória. Explique o motivo: ele fica registrado.')
            ->schema([
                Textarea::make('justificativa')
                    ->label('Por que não se aplica?')
                    ->required()
                    ->rows(2)
                    ->maxLength(300),
            ])
            ->action(function (ItemProcessoFuncionario $record, array $data): void {
                try {
                    app(ProcessoFuncionarioService::class)->dispensarItem($record, auth()->user(), (string) $data['justificativa']);
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível dispensar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Providência dispensada')->success()->send();
            });
    }

    private function anexarAction(): Action
    {
        return Action::make('anexar')
            ->label('Anexar comprovante')
            ->icon('heroicon-o-paper-clip')
            ->color('info')
            ->visible(fn (ItemProcessoFuncionario $record): bool => $this->podeMexer())
            ->modalHeading(fn (ItemProcessoFuncionario $record): string => 'Comprovante: '.$record->titulo)
            ->modalDescription('O arquivo fica em área privada. Enviar outro substitui o anterior.')
            ->schema([
                $this->campoArquivo(true),
            ])
            ->action(function (ItemProcessoFuncionario $record, array $data): void {
                $arquivo = $this->arquivoDaAcao($data);

                if (! $arquivo) {
                    Notification::make()->title('Arquivo não recebido')->danger()->send();

                    return;
                }

                try {
                    app(ProcessoFuncionarioService::class)->anexarArquivo($record, $arquivo, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível anexar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Comprovante anexado')->success()->send();
            });
    }

    private function reabrirAction(): Action
    {
        return Action::make('reabrir')
            ->label('Reabrir')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (ItemProcessoFuncionario $record): bool => $record->estaResolvido() && $this->podeMexer())
            ->requiresConfirmation()
            ->modalHeading('Reabrir a providência')
            ->action(function (ItemProcessoFuncionario $record): void {
                try {
                    app(ProcessoFuncionarioService::class)->reabrirItem($record, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível reabrir')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Providência reaberta')->success()->send();
            });
    }

    private function verArquivoAction(): Action
    {
        return Action::make('ver_arquivo')
            ->label('Ver comprovante')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (ItemProcessoFuncionario $record): bool => filled($record->arquivo_path))
            ->url(fn (ItemProcessoFuncionario $record): string => route('documentos.visualizar', ['path' => $record->arquivo_path]))
            ->openUrlInNewTab();
    }
}
