<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\DocumentoInserido;
use App\Models\SituacaoDocumentoInserido;
use App\Models\TipoDocumento;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DocumentosMatricula extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;
    use WithFileUploads;

    public $manualFileUpload;

    protected static string $resource = MatriculaResource::class;

    protected string $view = 'filament.resources.matriculas.pages.documentos-matricula';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Gerenciar Documentos';
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        return [
            $resource::getUrl('index') => 'Matrículas',
            $resource::getUrl('edit', ['record' => $this->record]) => "{$this->record->codigo} - {$this->record->pessoa->nome}",
            '#' => 'Documentos',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TipoDocumento::query()
                    ->where(function ($query) {
                        $query->whereHas('cursos', fn ($q) => $q->where('curso.id', $this->record->turma?->serie?->curso_id))
                            ->orWhereHas('turmas', fn ($q) => $q->where('turma.id', $this->record->turma_id))
                            ->orWhereHas('matriculas', fn ($q) => $q->where('matricula.id', $this->record->id));
                    })
            )
            ->columns([
                TextColumn::make('nome')
                    ->label('Tipo de Documento')
                    ->description(fn (TipoDocumento $record) => $record->flag_obrigatorio ? 'Obrigatório' : 'Opcional')
                    ->weight('bold'),

                TextColumn::make('modelo')
                    ->label('Modelo')
                    ->placeholder('Nenhum modelo cadastrado')
                    ->state(fn (TipoDocumento $record) => $record->modelo_arquivo || $record->modelo_link ? 'Disponível' : null)
                    ->formatStateUsing(fn ($state) => $state ? 'Ver Modelo' : '-')
                    ->color(fn ($state) => $state === 'Ver Modelo' ? 'primary' : 'gray')
                    ->icon(fn ($state) => $state === 'Ver Modelo' ? 'heroicon-o-arrow-down-tray' : null)
                    ->url(function (TipoDocumento $record) {
                        if ($record->modelo_link) {
                            return $record->modelo_link;
                        }
                        if ($record->modelo_arquivo) {
                            return asset('storage/'.$record->modelo_arquivo);
                        }

                        return null;
                    }, shouldOpenInNewTab: true),

                TextColumn::make('situacao')
                    ->label('Situação')
                    ->placeholder('Não enviado')
                    ->getStateUsing(function (TipoDocumento $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->first();

                        return $inserido?->situacao?->nome ?? 'Pendente de Envio';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aprovado' => 'success',
                        'Recusado' => 'danger',
                        'Pendente', 'Pendente de Envio' => 'warning',
                        'Em Análise' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('inserido_em')
                    ->label('Enviado em')
                    ->getStateUsing(function (TipoDocumento $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->first();

                        return $inserido?->created_at?->format('d/m/Y H:i');
                    })
                    ->placeholder('-'),

                ViewColumn::make('dropzone')
                    ->label('Upload Rápido')
                    ->view('filament.resources.matriculas.columns.dropzone-documento')
                    ->extraAttributes(['class' => 'w-48']),
            ])
            ->actions([
                Action::make('baixar_enviado')
                    ->label('Baixar Documento')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (TipoDocumento $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->first();

                        if ($inserido && $inserido->arquivo_path) {
                            return Storage::disk('public')->download($inserido->arquivo_path);
                        }

                        Notification::make()
                            ->title('Arquivo não encontrado')
                            ->danger()
                            ->send();
                    })
                    ->visible(fn (TipoDocumento $record) => DocumentoInserido::where('matricula_id', $this->record->id)
                        ->where('tipo_documento_id', $record->id)
                        ->exists()),

                Action::make('inserir')
                    ->label(function (TipoDocumento $record) {
                        $jaInserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->exists();

                        return $jaInserido ? 'Substituir' : 'Enviar';
                    })
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color(function (TipoDocumento $record) {
                        return DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->exists() ? 'gray' : 'primary';
                    })
                    ->form([
                        FileUpload::make('arquivo_path')
                            ->label('Arquivo do Documento')
                            ->helperText('Apenas Imagem ou PDF, máx 2MB.')
                            ->required()
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(2048)
                            ->directory('documentos_alunos')
                            ->storeFileNamesIn('nome_arquivo_original')
                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, TipoDocumento $record) {
                                $docName = Str::slug($record->nome);
                                $hash = md5_file($file->getRealPath());
                                $idStr = uniqid();

                                return "Doc-{$docName}-{$idStr}-{$hash}.".$file->getClientOriginalExtension();
                            })
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, $state, Set $set) {
                                $hash = md5_file($file->getRealPath());
                                $set('hash_arquivo', $hash);

                                return $file->store('documentos_alunos', 'public');
                            })
                            ->columnSpanFull(),

                        Textarea::make('observacoes')
                            ->label('Observações Adicionais')
                            ->columnSpanFull(),

                        Hidden::make('hash_arquivo'),
                    ])
                    ->fillForm(function (TipoDocumento $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->first();

                        return [
                            'observacoes' => $inserido?->observacoes,
                        ];
                    })
                    ->action(function (array $data, TipoDocumento $record) {
                        $situacaoPendenteId = SituacaoDocumentoInserido::where('nome', 'Pendente')->first()?->id ?? 1;

                        DocumentoInserido::updateOrCreate(
                            [
                                'matricula_id' => $this->record->id,
                                'tipo_documento_id' => $record->id,
                            ],
                            [
                                'arquivo_path' => $data['arquivo_path'],
                                'nome_arquivo_original' => $data['nome_arquivo_original'] ?? 'arquivo_enviado',
                                'hash_arquivo' => $data['hash_arquivo'] ?? null,
                                'situacao_documento_inserido_id' => $situacaoPendenteId,
                                'observacoes' => $data['observacoes'] ?? null,
                                'updated_at' => now(),
                            ]
                        );

                        Notification::make()
                            ->title('Documento enviado')
                            ->body("O documento '{$record->nome}' foi enviado com sucesso.")
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (TipoDocumento $record) => "Enviar Documento: {$record->nome}")
                    ->modalWidth('xl'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarZip')
                ->label('Exportar Todos (.zip)')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('success')
                ->action(fn () => $this->exportZip()),
        ];
    }

    public function exportZip(): ?BinaryFileResponse
    {
        $documentos = $this->record->documentoInseridos()->with('tipoDocumento')->get();

        if ($documentos->isEmpty()) {
            Notification::make()
                ->title('Nenhum documento encontrado')
                ->warning()
                ->send();

            return null;
        }

        $zipName = "documentos_{$this->record->codigo}.zip";
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');

        $zip = new ZipArchive;
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($documentos as $doc) {
                if (! $doc->arquivo_path) {
                    continue;
                }

                $filePath = Storage::disk('public')->path($doc->arquivo_path);

                if (file_exists($filePath)) {
                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                    $studentName = $this->record->pessoa->nome;
                    $typeName = $doc->tipoDocumento->nome;

                    // Limpar caracteres inválidos para nomes de arquivos
                    $safeStudentName = Str::replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $studentName);
                    $safeTypeName = Str::replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $typeName);

                    $newFileName = "{$this->record->codigo} - {$safeStudentName} - {$safeTypeName}.{$extension}";
                    $zip->addFile($filePath, $newFileName);
                }
            }
            $zip->close();
        }

        return response()->download($tempFile, $zipName)->deleteFileAfterSend(true);
    }

    public function processManualUpload(int $tipoDocumentoId, string $tmpPath, string $originalName): void
    {
        try {
            $tipoDocumento = TipoDocumento::findOrFail($tipoDocumentoId);
            $situacaoPendenteId = SituacaoDocumentoInserido::where('nome', 'Pendente')->first()?->id ?? 1;

            // Obter configurações do disco temporário do Livewire
            $disk = config('livewire.temporary_file_upload.disk') ?: 'local';
            $dir = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';

            $fullTmpPath = $dir.'/'.$tmpPath;

            if (! Storage::disk($disk)->exists($fullTmpPath)) {
                // Tenta sem o prefixo do disco se o disco já apontar para a pasta
                if (! Storage::disk($disk)->exists($tmpPath)) {
                    throw new \Exception("Arquivo temporário não encontrado no disco {$disk}. Tentado caminhos: {$fullTmpPath} e {$tmpPath}.");
                }
                $fullTmpPath = $tmpPath;
            }

            $fileContents = Storage::disk($disk)->get($fullTmpPath);
            $hash = md5($fileContents);

            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $idStr = uniqid();
            $docSlug = Str::slug($tipoDocumento->nome);

            $newName = "Doc-{$docSlug}-{$idStr}-{$hash}.{$extension}";
            $finalPath = "documentos_alunos/{$newName}";

            // Garantir que o diretório existe no disco público
            if (! Storage::disk('public')->exists('documentos_alunos')) {
                Storage::disk('public')->makeDirectory('documentos_alunos');
            }

            // Salvar no disco público
            Storage::disk('public')->put($finalPath, $fileContents);

            // Deletar o temporário para limpar
            Storage::disk($disk)->delete($fullTmpPath);

            DocumentoInserido::updateOrCreate(
                [
                    'matricula_id' => $this->record->id,
                    'tipo_documento_id' => $tipoDocumentoId,
                ],
                [
                    'arquivo_path' => $finalPath,
                    'nome_arquivo_original' => $originalName,
                    'hash_arquivo' => $hash,
                    'situacao_documento_inserido_id' => $situacaoPendenteId,
                    'updated_at' => now(),
                ]
            );

            Notification::make()
                ->title('Documento enviado')
                ->body("O documento '{$tipoDocumento->nome}' foi enviado com sucesso via arrastar e soltar.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro no upload')
                ->body('Ocorreu um erro ao processar o arquivo: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }
}
