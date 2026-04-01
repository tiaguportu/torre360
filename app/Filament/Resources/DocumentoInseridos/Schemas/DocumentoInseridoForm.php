<?php

namespace App\Filament\Resources\DocumentoInseridos\Schemas;

use App\Filament\Resources\Matriculas\Schemas\MatriculaForm;
use App\Models\Matricula;
use App\Models\DocumentoObrigatorio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Str;

class DocumentoInseridoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matricula_id')
                    ->relationship('matricula', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->pessoa?->nome ?? '') . ' - Turma ' . ($record->turma?->nome ?? ''))
                    ->default(fn ($livewire) => $livewire instanceof \Filament\Resources\RelationManagers\RelationManager ? $livewire->getOwnerRecord()->id : null)
                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\RelationManagers\RelationManager)
                    ->live()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm(fn (Schema $schema) => MatriculaForm::configure($schema)->getComponents())
                    ->columnSpanFull(),

                Select::make('documento_obrigatorio_id')
                    ->label('Documento Obrigatório')
                    ->options(function (Get $get) {
                        $matriculaId = $get('matricula_id');
                        if (!$matriculaId) return [];
                        
                        $matricula = Matricula::with('turma.serie.curso.documentos')->find($matriculaId);
                        $curso = $matricula?->turma?->serie?->curso;
                        if (!$curso) return [];
                        
                        return DocumentoObrigatorio::where('curso_id', $curso->id)->pluck('nome', 'id');
                    })
                    ->searchable()
                    ->required(),

                Select::make('situacao_documento_inserido_id')
                    ->label('Situação')
                    ->relationship('situacao', 'nome')
                    ->default(1) // Assumindo que 1 seja "Enviado" ou "Aguardando Análise"
                    ->required(),

                FileUpload::make('arquivo_path')
                    ->label('Arquivo do Documento')
                    ->required()
                    ->directory('documentos_alunos')
                    ->storeFileNamesIn('nome_arquivo_original')
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, Get $get) {
                        $doc = DocumentoObrigatorio::find($get('documento_obrigatorio_id'));
                        $docName = $doc ? Str::slug($doc->nome) : 'documento';
                        $hash = md5_file($file->getRealPath());
                        $idStr = uniqid();
                        return "Doc-{$docName}-{$idStr}-{$hash}." . $file->getClientOriginalExtension();
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
