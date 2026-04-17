<?php

namespace App\Filament\Resources\Interessados\Schemas;

use App\Filament\Resources\Pessoas\Schemas\PessoaForm;
use App\Models\Interessado;
use App\Models\Pessoa;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InteressadoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('CRM')
                    ->tabs([
                        Tab::make('Dados do Negócio')
                            ->schema([
                                Actions::make([
                                    Action::make('alerta_contato')
                                        ->label('ATENÇÃO: Este interessado precisa de um novo contato! A data agendada é anterior ao último contato realizado.')
                                        ->icon('heroicon-m-exclamation-triangle')
                                        ->color('danger')
                                        ->badge()
                                        ->url(fn (Interessado $record) => '#') // Apenas visual ou scroll para histórico
                                        ->disabled()
                                        ->extraAttributes([
                                            'class' => 'w-full justify-center py-4 text-lg font-bold animate-pulse',
                                        ]),
                                ])
                                    ->columnSpanFull()
                                    ->visible(fn (?Interessado $record) => $record?->precisaDeContato() ?? false),

                                Select::make('pessoa_id')
                                    ->label('Pessoa / Interessado')
                                    ->relationship('pessoa', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $pessoa = Pessoa::find($state);
                                            $set('pessoa_email', $pessoa?->email);
                                            $set('pessoa_telefone', $pessoa?->telefone);
                                        } else {
                                            $set('pessoa_email', null);
                                            $set('pessoa_telefone', null);
                                        }
                                    })
                                    ->createOptionForm(fn (Schema $schema) => PessoaForm::configure($schema)->getComponents()),

                                TextInput::make('pessoa_email')
                                    ->label('E-mail')
                                    ->afterStateHydrated(fn (Set $set, Get $get) => $set('pessoa_email', Pessoa::find($get('pessoa_id'))?->email))
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('pessoa_telefone')
                                    ->label('Telefone')
                                    ->afterStateHydrated(fn (Set $set, Get $get) => $set('pessoa_telefone', Pessoa::find($get('pessoa_id'))?->telefone))
                                    ->disabled()
                                    ->dehydrated(false),

                                Select::make('status_interessado_id')
                                    ->label('Status')
                                    ->relationship('status', 'nome')
                                    ->required()
                                    ->native(false),

                                Select::make('origem_interessado_id')
                                    ->label('Origem')
                                    ->relationship('origem', 'nome')
                                    ->required()
                                    ->native(false),

                                Select::make('usuario_id')
                                    ->label('Consultor Responsável')
                                    ->relationship('usuario', 'name')
                                    ->required()
                                    ->native(false),

                                DateTimePicker::make('data_proximo_contato')
                                    ->label('Próximo Contato')
                                    ->native(false),

                                Textarea::make('observacoes')
                                    ->label('Observações')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Tab::make('Dependentes')
                            ->schema([
                                Repeater::make('dependentes')
                                    ->relationship('dependentes')
                                    ->schema([
                                        TextInput::make('nome_crianca')
                                            ->label('Nome da Criança')
                                            ->required(),
                                        Select::make('serie_id')
                                            ->label('Série de Interesse')
                                            ->relationship('serie', 'nome')
                                            ->required(),
                                        DatePicker::make('data_nascimento')
                                            ->label('Data de Nascimento')
                                            ->native(false),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->itemLabel(fn (array $state): ?string => $state['nome_crianca'] ?? null),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
