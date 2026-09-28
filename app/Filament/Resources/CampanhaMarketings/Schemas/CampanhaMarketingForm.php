<?php

namespace App\Filament\Resources\CampanhaMarketings\Schemas;

use App\Models\CampanhaMarketing;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CampanhaMarketingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campanha')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome da Campanha')
                            ->required()
                            ->maxLength(255),
                        Select::make('canal')
                            ->label('Canal')
                            ->options(CampanhaMarketing::CANAIS)
                            ->searchable(),
                        TextInput::make('codigo_utm')
                            ->label('Código UTM (utm_campaign)')
                            ->helperText('Use este código no link da campanha: /quero-matricular?utm_source=instagram&utm_medium=cpc&utm_campaign=CÓDIGO. Os leads que chegarem por esse link serão atribuídos automaticamente a esta campanha.')
                            ->alphaDash()
                            ->maxLength(191)
                            ->unique(ignoreRecord: true),
                        Toggle::make('ativa')
                            ->label('Campanha ativa')
                            ->default(true)
                            ->helperText('Campanhas inativas não recebem novos leads pelo link UTM.')
                            ->inline(false),
                        DatePicker::make('data_inicio')
                            ->label('Início')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('data_fim')
                            ->label('Término')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('data_inicio'),
                        TextInput::make('custo')
                            ->label('Investimento (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Usado para calcular custo por lead e por matrícula.'),
                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
