<?php

namespace App\Filament\Resources\DocumentoRegulatorios\Schemas;

use App\Models\DocumentoRegulatorio;
use App\Models\Unidade;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentoRegulatorioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação do Documento')
                    ->components([
                        Grid::make(3)
                            ->components([
                                Select::make('unidade_id')
                                    ->label('Unidade Escolar')
                                    ->options(Unidade::pluck('nome', 'id'))
                                    ->searchable()
                                    ->placeholder('Toda a Instituição (Geral)')
                                    ->nullable(),

                                Select::make('tipo_documento')
                                    ->label('Tipo de Documento')
                                    ->options(DocumentoRegulatorio::TIPOS)
                                    ->required()
                                    ->searchable(),

                                TextInput::make('nome')
                                    ->label('Nome / Título')
                                    ->placeholder('Ex: Alvará de Funcionamento 2026')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Grid::make(3)
                            ->components([
                                TextInput::make('numero_registro')
                                    ->label('Número / Protocolo / Processo')
                                    ->placeholder('Ex: PROC-2026/0491')
                                    ->maxLength(100),

                                TextInput::make('orgao_emissor')
                                    ->label('Órgão Emissor / Expedidor')
                                    ->placeholder('Ex: Corpo de Bombeiros / Prefeitura')
                                    ->maxLength(150),

                                TextInput::make('responsavel_renovacao')
                                    ->label('Responsável pela Renovação')
                                    ->placeholder('Ex: Setor Jurídico / Secretaria')
                                    ->maxLength(150),
                            ]),
                    ]),

                Section::make('Prazos e Alertas de Validade')
                    ->components([
                        Grid::make(4)
                            ->components([
                                DatePicker::make('data_emissao')
                                    ->label('Data de Emissão')
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),

                                DatePicker::make('data_validade')
                                    ->label('Data de Validade')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->helperText('Deixe vazio para validade indeterminada'),

                                TextInput::make('dias_antecedencia_alerta')
                                    ->label('Alerta Prévio (Dias)')
                                    ->numeric()
                                    ->default(30)
                                    ->suffix('dias')
                                    ->required(),

                                Select::make('status')
                                    ->label('Situação')
                                    ->options(DocumentoRegulatorio::STATUS)
                                    ->default('vigente')
                                    ->required(),
                            ]),

                        FileUpload::make('arquivo_path')
                            ->label('Arquivo Digitalizado (PDF ou Imagem)')
                            ->directory('documentos_regulatorios')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(15360)
                            ->openable()
                            ->downloadable(),

                        Textarea::make('observacoes')
                            ->label('Observações e Instruções de Renovação')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
