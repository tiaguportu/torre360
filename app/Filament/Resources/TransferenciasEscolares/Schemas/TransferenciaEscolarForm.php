<?php

namespace App\Filament\Resources\TransferenciasEscolares\Schemas;

use App\Enums\TipoTransferencia;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TransferenciaEscolarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transferência Escolar')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Matrícula / Aluno')
                            ->relationship('matricula', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->pessoa?->nome} — {$record->turma?->nome}")
                            ->searchable(['pessoa.nome'])
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoTransferencia::class)
                            ->required()
                            ->live(),
                        DatePicker::make('data')
                            ->label(fn (Get $get): string => $get('tipo') === TipoTransferencia::Entrada->value ? 'Data de Entrada' : 'Data de Saída')
                            ->native(false)
                            ->default(now())
                            ->required(),
                        TextInput::make('escola_externa_nome')
                            ->label(fn (Get $get): string => $get('tipo') === TipoTransferencia::Entrada->value ? 'Escola de Origem' : 'Escola de Destino')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('escola_externa_cidade')
                            ->label('Cidade'),
                        TextInput::make('escola_externa_uf')
                            ->label('UF')
                            ->maxLength(2),
                        Textarea::make('motivo')
                            ->label('Motivo')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
