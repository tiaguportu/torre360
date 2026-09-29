<?php

namespace App\Filament\Resources\ComunicacaoEmMassas\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Enums\TipoPublicoComunicacao;
use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use App\Models\Turma;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ComunicacaoEmMassaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Comunicação')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome interno')
                            ->helperText('Só para identificar esta comunicação na lista; não aparece para quem recebe.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('tipo_publico')
                            ->label('Público')
                            ->options(TipoPublicoComunicacao::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->disabled(fn (?string $operation) => $operation === 'edit'),

                        Select::make('canal')
                            ->label('Canal')
                            ->options(['email' => 'E-mail'])
                            ->default('email')
                            ->required()
                            ->native(false)
                            ->disabled()
                            ->dehydrated(),

                        Select::make('filtros.status_interessado_ids')
                            ->label('Status do lead')
                            ->multiple()
                            ->options(fn () => StatusInteressado::pluck('nome', 'id'))
                            ->helperText('Deixe vazio para não filtrar por status.')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('tipo_publico') === TipoPublicoComunicacao::Interessados->value)
                            ->columnSpanFull(),

                        Select::make('filtros.origem_interessado_ids')
                            ->label('Origem do lead')
                            ->multiple()
                            ->options(fn () => OrigemInteressado::pluck('nome', 'id'))
                            ->helperText('Deixe vazio para não filtrar por origem. Pelo menos um filtro de status ou origem é necessário.')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('tipo_publico') === TipoPublicoComunicacao::Interessados->value)
                            ->columnSpanFull(),

                        Select::make('filtros.turma_ids')
                            ->label('Turmas')
                            ->multiple()
                            ->required(fn (Get $get) => $get('tipo_publico') === TipoPublicoComunicacao::ResponsaveisTurma->value)
                            ->options(fn () => Turma::pluck('nome', 'id'))
                            ->helperText('Serão avisados os responsáveis dos alunos com matrícula ativa nas turmas selecionadas.')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('tipo_publico') === TipoPublicoComunicacao::ResponsaveisTurma->value)
                            ->columnSpanFull(),

                        TextInput::make('assunto')
                            ->label('Assunto do e-mail')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TinyEditor::make('corpo')
                            ->label('Mensagem')
                            ->helperText('Use [Nome] para inserir o primeiro nome do destinatário.')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
