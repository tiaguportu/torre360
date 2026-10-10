<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoDocumentos\Schemas;

use App\Enums\CategoriaExigenciaDocumento;
use App\Support\TiposArquivo;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TipoDocumentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Nome do Tipo de Documento')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Radio::make('categoria_exigencia')
                    ->label('Exigência e Visibilidade do Documento')
                    ->options(CategoriaExigenciaDocumento::class)
                    ->descriptions([
                        CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO->value => 'Indispensável para gerar o Contrato Escolar. Sem ele, a matrícula é salva como Pendente.',
                        CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO->value => 'Obrigatório para a pasta acadêmica do aluno (MEC). Não impede a emissão do contrato.',
                        CategoriaExigenciaDocumento::OPCIONAL->value => 'Aparece no Portal da Família como anexo facultativo (ex: laudos médicos, plano de saúde).',
                        CategoriaExigenciaDocumento::INTERNO->value => 'Uso exclusivo da secretaria e arquivo escolar (não aparece no Portal da Família nem nos wizards).',
                    ])
                    ->default(CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO)
                    ->required()
                    ->columnSpanFull(),

                Select::make('cursos')
                    ->label('Vincular a Cursos Específicos (opcional)')
                    ->helperText('Se não selecionar nenhum curso, este documento será exibido para todos os cursos.')
                    ->relationship('cursos', 'nome_externo', modifyQueryUsing: fn ($query) => $query->whereNotNull('nome_externo'))
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Select::make('turmas')
                    ->label('Vincular a Turmas Específicas (opcional)')
                    ->relationship('turmas', 'nome', modifyQueryUsing: fn ($query) => $query->whereNotNull('nome'))
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Select::make('matriculas')
                    ->label('Vincular a Matrículas Específicas (opcional)')
                    ->relationship('matriculas', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->pessoa?->nome ?? 'Aluno Desconhecido')." (ID: {$record->id})")
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),

                FileUpload::make('modelo_arquivo')
                    ->label('Modelo de Arquivo para Download (opcional)')
                    ->helperText('Arquivo em PDF ou imagem que a família pode baixar como exemplo/modelo (máx. 10MB).')
                    ->directory('tipos-documentos-modelos')
                    ->acceptedFileTypes(TiposArquivo::documentos())
                    ->maxSize(10240)
                    ->visibility('public')
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),

                TextInput::make('modelo_link')
                    ->label('Link Externo para Modelo ou Instrução (opcional)')
                    ->url()
                    ->placeholder('https://...')
                    ->columnSpanFull(),
            ]);
    }
}
