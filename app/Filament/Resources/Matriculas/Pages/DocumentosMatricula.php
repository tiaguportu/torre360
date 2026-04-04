<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\DocumentoInserido;
use App\Models\DocumentoObrigatorio;
use App\Models\SituacaoDocumentoInserido;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentosMatricula extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

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
        $cursoId = $this->record->turma?->serie?->curso_id;

        return $table
            ->query(
                DocumentoObrigatorio::query()
                    ->where('curso_id', $cursoId)
                    ->where('flag_ativo', true)
            )
            ->columns([
                TextColumn::make('nome')
                    ->label('Tipo de Documento')
                    ->description(fn (DocumentoObrigatorio $record) => $record->flag_obrigatorio ? 'Obrigatório' : 'Opcional')
                    ->weight('bold'),

                TextColumn::make('modelo')
                    ->label('Modelo')
                    ->placeholder('Nenhum modelo cadastrado')
                    ->state(fn (DocumentoObrigatorio $record) => $record->modelo_arquivo || $record->modelo_link ? 'Disponível' : null)
                    ->formatStateUsing(fn ($state) => $state ? 'Ver Modelo' : '-')
                    ->color(fn ($state) => $state === 'Ver Modelo' ? 'primary' : 'gray')
                    ->icon(fn ($state) => $state === 'Ver Modelo' ? 'heroicon-o-arrow-down-tray' : null)
                    ->url(function (DocumentoObrigatorio $record) {
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
                    ->getStateUsing(function (DocumentoObrigatorio $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('documento_obrigatorio_id', $record->id)
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
                    ->getStateUsing(function (DocumentoObrigatorio $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('documento_obrigatorio_id', $record->id)
                            ->first();

                        return $inserido?->created_at?->format('d/m/Y H:i');
                    })
                    ->placeholder('-'),
            ])
            ->actions([
                Action::make('visualizar_enviado')
                    ->label('Ver Documento')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(function (DocumentoObrigatorio $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('documento_obrigatorio_id', $record->id)
                            ->first();

                        return $inserido?->arquivo_path ? asset('storage/'.$inserido->arquivo_path) : null;
                    }, shouldOpenInNewTab: true)
                    ->visible(fn (DocumentoObrigatorio $record) => DocumentoInserido::where('matricula_id', $this->record->id)
                        ->where('documento_obrigatorio_id', $record->id)
                        ->exists()),

                Action::make('inserir')
                    ->label(function (DocumentoObrigatorio $record) {
                        $jaInserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('documento_obrigatorio_id', $record->id)
                            ->exists();

                        return $jaInserido ? 'Substituir' : 'Enviar';
                    })
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color(function (DocumentoObrigatorio $record) {
                        return DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('documento_obrigatorio_id', $record->id)
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
                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, DocumentoObrigatorio $record) {
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
                    ->fillForm(function (DocumentoObrigatorio $record) {
                        $inserido = DocumentoInserido::where('matricula_id', $this->record->id)
                            ->where('documento_obrigatorio_id', $record->id)
                            ->first();

                        return [
                            'observacoes' => $inserido?->observacoes,
                        ];
                    })
                    ->action(function (array $data, DocumentoObrigatorio $record) {
                        $situacaoPendenteId = SituacaoDocumentoInserido::where('nome', 'Pendente')->first()?->id ?? 1;

                        DocumentoInserido::updateOrCreate(
                            [
                                'matricula_id' => $this->record->id,
                                'documento_obrigatorio_id' => $record->id,
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
                    ->modalHeading(fn (DocumentoObrigatorio $record) => "Enviar Documento: {$record->nome}")
                    ->modalWidth('xl'),
            ]);
    }
}
