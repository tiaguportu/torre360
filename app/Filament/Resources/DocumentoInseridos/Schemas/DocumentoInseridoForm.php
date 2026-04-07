<?php

namespace App\Filament\Resources\DocumentoInseridos\Schemas;

use App\Filament\Resources\Matriculas\Schemas\MatriculaForm;
use App\Models\Matricula;
use App\Models\TipoDocumento;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentoInseridoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matricula_id')
                    ->relationship('matricula', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->pessoa?->nome ?? '').' - Turma '.($record->turma?->nome ?? ''))
                    ->default(fn ($livewire) => $livewire instanceof RelationManager ? $livewire->getOwnerRecord()->id : null)
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager)
                    ->live()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm(fn (Schema $schema) => MatriculaForm::configure($schema)->getComponents())
                    ->columnSpanFull(),

                Select::make('tipo_documento_id')
                    ->label('Tipo de Documento')
                    ->options(function (Get $get) {
                        $matriculaId = $get('matricula_id');
                        if (! $matriculaId) {
                            return [];
                        }

                        $matricula = Matricula::with(['turma.serie.curso', 'tiposDocumentos', 'turma.tiposDocumentos'])->find($matriculaId);
                        if (! $matricula) {
                            return [];
                        }

                        return TipoDocumento::query()
                            ->where(function ($query) use ($matricula) {
                                $query->whereHas('cursos', fn ($q) => $q->where('curso.id', $matricula->turma?->serie?->curso_id))
                                    ->orWhereHas('turmas', fn ($q) => $q->where('turma.id', $matricula->turma_id))
                                    ->orWhereHas('matriculas', fn ($q) => $q->where('matricula.id', $matricula->id));
                            })
                            ->pluck('nome', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->live(),

                Actions::make([
                    Action::make('download_modelo')
                        ->label('Baixar Modelo do Documento')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->visible(fn (Get $get) => $get('tipo_documento_id') &&
                            (TipoDocumento::find($get('tipo_documento_id'))?->modelo_arquivo || TipoDocumento::find($get('tipo_documento_id'))?->modelo_link)
                        )
                        ->url(function (Get $get) {
                            $doc = TipoDocumento::find($get('tipo_documento_id'));
                            if ($doc?->modelo_link) {
                                return $doc->modelo_link;
                            }
                            if ($doc?->modelo_arquivo) {
                                return asset('storage/'.$doc->modelo_arquivo);
                            }

                            return null;
                        }, shouldOpenInNewTab: true),
                ]),

                Select::make('situacao_documento_inserido_id')
                    ->label('Situação')
                    ->relationship('situacao', 'nome')
                    ->default(1) // Assumindo que 1 seja "Enviado" ou "Aguardando Análise"
                    ->required(),

                FileUpload::make('arquivo_path')
                    ->label('Arquivo do Documento')
                    ->helperText('Apenas Imagem ou PDF, máx 2MB.')
                    ->required()
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(2048)
                    ->directory('documentos_alunos')
                    ->visibility('public')
                    ->downloadable()
                    ->openable()
                    ->storeFileNamesIn('nome_arquivo_original')
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, Get $get) {
                        $doc = TipoDocumento::find($get('tipo_documento_id'));
                        $docName = $doc ? Str::slug($doc->nome) : 'documento';
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

                TextInput::make('hash_arquivo')
                    ->label('Hash do Arquivo')
                    ->disabled()
                    ->dehydrated(true)
                    ->hidden(),
            ]);
    }
}
