<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Enums\SituacaoDocumento;
use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\DocumentoInserido;
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
            $resource::getUrl('edit', ['record' => $this->record]) => ($this->record->turma?->nome ?? 'Sem Turma')." - {$this->record->pessoa->nome}",
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

                TextColumn::make('status')
                    ->label('Situação')
                    ->placeholder('Pendente de Envio')
                    ->getStateUsing(function (TipoDocumento $record) {
                        return DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->first()
                            ?->status;
                    })
                    ->badge(),

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
            ->stackedOnMobile()
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
                            $extension = pathinfo($inserido->arquivo_path, PATHINFO_EXTENSION);
                            $studentName = $this->record->pessoa->nome;
                            $typeName = $record->nome;

                            $safeStudentName = Str::replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $studentName);
                            $safeTypeName = Str::replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $typeName);
                            $safeTurmaName = Str::replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', ($this->record->turma?->nome ?? 'Sem Turma'));

                            $newFileName = "{$safeTurmaName} - {$safeStudentName} - {$safeTypeName}.{$extension}";

                            return Storage::disk('public')->download($inserido->arquivo_path, $newFileName);
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
                        $jaInserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->first();

                        DocumentoInserido::updateOrCreate(
                            [
                                'matricula_id' => $this->record->id,
                                'tipo_documento_id' => $record->id,
                            ],
                            [
                                'arquivo_path' => $data['arquivo_path'],
                                'nome_arquivo_original' => $data['nome_arquivo_original'] ?? 'arquivo_enviado',
                                'hash_arquivo' => $data['hash_arquivo'] ?? null,
                                'status' => SituacaoDocumento::PENDENTE,
                                'observacoes' => $data['observacoes'] ?? null,
                                'updated_at' => now(),
                            ]
                        );

                        activity()
                            ->performedOn($this->record)
                            ->withProperties([
                                'tipo_documento' => $record->nome,
                                'arquivo' => $data['nome_arquivo_original'] ?? 'arquivo_enviado',
                            ])
                            ->log($jaInserido ? 'Substituiu documento de matrícula' : 'Enviou documento de matrícula');

                        Notification::make()
                            ->title('Documento enviado')
                            ->body("O documento '{$record->nome}' foi enviado com sucesso.")
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (TipoDocumento $record) => "Enviar Documento: {$record->nome}")
                    ->modalWidth('xl'),

                Action::make('excluir')
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (TipoDocumento $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('tipo_documento_id', $record->id)
                            ->first();

                        if ($inserido) {
                            $tipoNome = $record->nome;
                            if ($inserido->arquivo_path) {
                                Storage::disk('public')->delete($inserido->arquivo_path);
                            }
                            $inserido->delete();

                            activity()
                                ->performedOn($this->record)
                                ->withProperties([
                                    'tipo_documento' => $tipoNome,
                                ])
                                ->log('Excluiu documento de matrícula');

                            Notification::make()
                                ->title('Documento excluído')
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn (TipoDocumento $record) => DocumentoInserido::where('matricula_id', $this->record->id)
                        ->where('tipo_documento_id', $record->id)
                        ->exists()),
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

        $zipName = 'documentos_'.Str::slug($this->record->pessoa->nome).'.zip';
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
                    $safeTurmaName = Str::replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', ($this->record->turma?->nome ?? 'Sem Turma'));

                    $newFileName = "{$safeTurmaName} - {$safeStudentName} - {$safeTypeName}.{$extension}";
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

            if (! $this->manualFileUpload) {
                throw new \Exception('Arquivo não recebido pelo servidor.');
            }

            $file = $this->manualFileUpload;
            $fileContents = $file->get();
            $hash = md5($fileContents);

            $extension = $file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
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

            // Limpar a propriedade de upload
            $this->manualFileUpload = null;

            DocumentoInserido::updateOrCreate(
                [
                    'matricula_id' => $this->record->id,
                    'tipo_documento_id' => $tipoDocumentoId,
                ],
                [
                    'arquivo_path' => $finalPath,
                    'nome_arquivo_original' => $originalName,
                    'hash_arquivo' => $hash,
                    'status' => SituacaoDocumento::PENDENTE,
                    'updated_at' => now(),
                ]
            );

            activity()
                ->performedOn($this->record)
                ->withProperties([
                    'tipo_documento' => $tipoDocumento->nome,
                    'arquivo' => $originalName,
                ])
                ->log('Enviou documento de matrícula via dropzone');

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
