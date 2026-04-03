<?php

namespace App\Filament\Resources\Matriculas\Tables;

use App\Models\DocumentoInserido;
use App\Models\Matricula;
use App\Models\SituacaoDocumentoInserido;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class MatriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bg-danger-500/10 dark:bg-danger-500/20' : null)
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable(),
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bold' : null)
                    ->color(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'danger' : null),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('situacaoMatricula.nome')
                    ->label('Situação')
                    ->sortable(),
                TextColumn::make('data_matricula')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('inserir_documentos')
                    ->label('Documentos')
                    ->tooltip('Inserir Documentos Obrigatórios')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'danger' : 'primary')
                    ->badge(fn (Matricula $record) => $record->getMissingMandatoryDocumentsCount() ?: null)
                    ->badgeColor('danger')
                    ->modalHeading(fn (Matricula $record) => "Inserir Documentos: {$record->pessoa?->nome}")
                    ->modalDescription('Faça o upload dos documentos obrigatórios para esta matrícula.')
                    ->modalSubmitActionLabel('Salvar Documentos')
                    ->slideOver()
                    ->fillForm(fn (Matricula $record): array => $record->documentoInseridos
                        ->pluck('arquivo_path', 'documento_obrigatorio_id')
                        ->mapWithKeys(fn ($path, $id) => ["doc_{$id}" => $path])
                        ->toArray()
                    )
                    ->form(function (Matricula $record) {
                        $curso = $record->turma?->serie?->curso;
                        $documentosObrigatorios = $curso?->documentos;

                        if (! $documentosObrigatorios || $documentosObrigatorios->isEmpty()) {
                            return [
                                Placeholder::make('info_no_docs')
                                    ->label('Atenção')
                                    ->content('Não há tipos de documentos obrigatórios configurados para o curso desta matrícula.'),
                            ];
                        }

                        $fields = [];
                        $docsJaInseridosIds = $record->documentoInseridos()->pluck('documento_obrigatorio_id')->toArray();

                        foreach ($documentosObrigatorios as $doc) {
                            $jaInserido = in_array($doc->id, $docsJaInseridosIds);

                            $fields[] = FileUpload::make("doc_{$doc->id}")
                                ->label($doc->nome)
                                ->directory('documentos-matriculas')
                                ->preserveFilenames()
                                ->required(! $jaInserido)
                                ->helperText($jaInserido ? 'Este documento já foi enviado anteriormente.' : 'Obrigatório o envio (Apenas Imagem ou PDF, máx 2MB).')
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->maxSize(2048)
                                ->openable()
                                ->downloadable();
                        }

                        return [
                            Section::make('Arquivos Solicitados')
                                ->description('Selecione os arquivos correspondentes a cada tipo de documento.')
                                ->schema($fields),
                        ];
                    })
                    ->action(function (array $data, Matricula $record) {
                        $situacaoPendenteId = SituacaoDocumentoInserido::where('nome', 'Pendente')->first()?->id ?? 1;
                        $docsSalvos = 0;

                        foreach ($data as $key => $filePath) {
                            if (str_starts_with($key, 'doc_')) {
                                $docObrigatorioId = (int) str_replace('doc_', '', $key);

                                if ($filePath) {
                                    $nomeOriginal = is_string($filePath) ? basename($filePath) : 'arquivo_enviado';
                                    $hash = is_string($filePath) ? hash_file('sha256', Storage::path($filePath)) : null;

                                    DocumentoInserido::updateOrCreate(
                                        [
                                            'matricula_id' => $record->id,
                                            'documento_obrigatorio_id' => $docObrigatorioId,
                                        ],
                                        [
                                            'arquivo_path' => $filePath,
                                            'nome_arquivo_original' => $nomeOriginal,
                                            'hash_arquivo' => $hash,
                                            'situacao_documento_inserido_id' => $situacaoPendenteId,
                                            'updated_at' => now(),
                                        ]
                                    );
                                    $docsSalvos++;
                                } else {
                                    // Se o campo está vazio, verifica se existia um documento para excluir
                                    $docExistente = DocumentoInserido::where('matricula_id', $record->id)
                                        ->where('documento_obrigatorio_id', $docObrigatorioId)
                                        ->first();

                                    if ($docExistente) {
                                        if ($docExistente->arquivo_path) {
                                            Storage::disk('public')->delete($docExistente->arquivo_path);
                                        }
                                        $docExistente->delete();
                                    }
                                }
                            }
                        }

                        if ($docsSalvos > 0) {
                            Notification::make()
                                ->title('Sucesso')
                                ->body("{$docsSalvos} documentos foram salvos com sucesso.")
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
