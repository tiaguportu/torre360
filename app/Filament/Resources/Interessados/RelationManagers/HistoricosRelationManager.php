<?php

namespace App\Filament\Resources\Interessados\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HistoricosRelationManager extends RelationManager
{
    protected static string $relationship = 'historicos';

    protected static ?string $title = 'Histórico de Contatos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo_contato_interessado_id')
                    ->label('Tipo de Contato')
                    ->relationship('tipoContato', 'nome')
                    ->required(),
                DateTimePicker::make('data_contato')
                    ->label('Data do Contato')
                    ->default(now())
                    ->required(),
                TextInput::make('duracao_minutos')
                    ->label('Duração (minutos)')
                    ->numeric()
                    ->minValue(1),
                Select::make('resultado')
                    ->label('Resultado')
                    ->options([
                        'agendou_visita' => 'Agendou Visita',
                        'retornar' => 'Retornar depois',
                        'sem_interesse' => 'Sem Interesse',
                        'matriculou' => 'Efetuou Matrícula',
                        'outro' => 'Outro',
                    ]),
                Textarea::make('relato')
                    ->label('Relato do Atendimento')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('relato')
            ->defaultSort('data_contato', 'desc')
            ->columns([
                TextColumn::make('tipoContato.nome')
                    ->label('Tipo'),
                TextColumn::make('data_contato')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('usuario.name')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->icon('heroicon-o-user'),
                TextColumn::make('resultado')
                    ->label('Resultado')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'agendou_visita' => 'success',
                        'matriculou' => 'success',
                        'retornar' => 'warning',
                        'sem_interesse' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'agendou_visita' => 'Agendou Visita',
                        'retornar' => 'Retornar',
                        'sem_interesse' => 'Sem Interesse',
                        'matriculou' => 'Matriculou',
                        'outro' => 'Outro',
                        default => '—',
                    }),
                TextColumn::make('duracao_minutos')
                    ->label('Duração')
                    ->suffix(' min')
                    ->placeholder('—'),
                TextColumn::make('relato')
                    ->label('Relato')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar Contato')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['usuario_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
