<?php

namespace App\Filament\Resources\MaterialAulas\Schemas;

use App\Enums\TipoMaterialAula;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MaterialAulaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Material de Aula')
                    ->columns(2)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(2)
                            ->columnSpanFull(),
                        Select::make('turma_id')
                            ->label('Turma')
                            ->relationship('turma', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('disciplina_id')
                            ->label('Disciplina')
                            ->relationship('disciplina', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('professor_id')
                            ->label('Professor Responsável')
                            ->relationship('professor', 'nome')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('data_publicacao')
                            ->label('Data de Publicação')
                            ->native(false)
                            ->default(now())
                            ->required(),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoMaterialAula::class)
                            ->required()
                            ->live(),
                        Toggle::make('visivel')
                            ->label('Visível para os Alunos')
                            ->default(true),
                        FileUpload::make('arquivo_path')
                            ->label('Arquivo')
                            ->directory('materiais-aula')
                            ->preserveFilenames()
                            ->visible(fn (Get $get): bool => self::tipoSelecionado($get) === TipoMaterialAula::Apostila)
                            ->required(fn (Get $get): bool => self::tipoSelecionado($get) === TipoMaterialAula::Apostila)
                            ->dehydrated()
                            ->columnSpanFull(),
                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->helperText('Link do vídeo (ex.: YouTube não-listado, Vimeo) ou link externo.')
                            ->visible(fn (Get $get): bool => in_array(self::tipoSelecionado($get), [TipoMaterialAula::VideoAula, TipoMaterialAula::LinkExterno], true))
                            ->required(fn (Get $get): bool => in_array(self::tipoSelecionado($get), [TipoMaterialAula::VideoAula, TipoMaterialAula::LinkExterno], true))
                            ->dehydrated()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Normaliza o valor de `tipo` para a instância do enum, independente de o Select ter
     * entregue a instância ou o valor-string bruto (ex.: durante o preenchimento inicial
     * do formulário).
     */
    private static function tipoSelecionado(Get $get): ?TipoMaterialAula
    {
        $tipo = $get('tipo');

        if ($tipo instanceof TipoMaterialAula) {
            return $tipo;
        }

        return TipoMaterialAula::tryFrom((string) $tipo);
    }
}
