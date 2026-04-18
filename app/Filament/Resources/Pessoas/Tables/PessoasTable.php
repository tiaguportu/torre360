<?php

namespace App\Filament\Resources\Pessoas\Tables;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Filament\Exports\PessoaExporter;
use App\Models\Pessoa;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PessoasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')
                    ->circular()
                    ->label('')
                    ->width(40)
                    ->height(40)
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Pessoa&color=7F9CF5&background=EBF4FF'),

                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('data_nascimento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('cpf')
                    ->searchable(),

                TextColumn::make('nacionalidade.nome')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('naturalidade.nome')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('enderecos.logradouro')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sexo')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cor_raca')
                    ->label('Cor / Raça')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('estado_civil')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('profissao')
                    ->label('Profissão')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('identidade')
                    ->label('Identidade (RG)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PessoaExporter::class)
                        ->label('Exportar Selecionados')
                        ->visible(fn (): bool => auth()->user()->can('export', Pessoa::class)),
                    BulkAction::make('editarLote')
                        ->label('Editar em Lote')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Select::make('sexo')
                                ->label('Sexo')
                                ->options(Sexo::class)
                                ->preload()
                                ->searchable(),
                            Select::make('cor_raca')
                                ->label('Cor / Raça')
                                ->options(CorRaca::class)
                                ->preload()
                                ->searchable(),
                            Select::make('nacionalidade_id')
                                ->label('Nacionalidade')
                                ->relationship('nacionalidade', 'nome')
                                ->preload()
                                ->searchable(),
                            Select::make('estado_civil')
                                ->label('Estado Civil')
                                ->options([
                                    'Solteiro(a)' => 'Solteiro(a)',
                                    'Casado(a)' => 'Casado(a)',
                                    'Divorciado(a)' => 'Divorciado(a)',
                                    'Viúvo(a)' => 'Viúvo(a)',
                                    'União Estável' => 'União Estável',
                                ])
                                ->searchable(),
                            TextInput::make('profissao')
                                ->label('Profissão'),
                            TextInput::make('identidade')
                                ->label('Identidade (RG)'),

                        ])
                        ->action(function (Collection $records, array $data): void {
                            $updateData = array_filter([
                                'sexo' => $data['sexo'] ?? null,
                                'cor_raca' => $data['cor_raca'] ?? null,
                                'nacionalidade_id' => $data['nacionalidade_id'] ?? null,
                                'estado_civil' => $data['estado_civil'] ?? null,
                                'profissao' => $data['profissao'] ?? null,
                                'identidade' => $data['identidade'] ?? null,
                            ], fn ($value) => filled($value));

                            try {
                                foreach ($records as $record) {
                                    if (! empty($updateData)) {
                                        $record->update($updateData);
                                    }

                                }

                                Notification::make()
                                    ->title('Atualização em lote concluída')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Log::error('Erro no Bulk Edit de Pessoas: '.$e->getMessage());

                                Notification::make()
                                    ->title('Erro na atualização em lote')
                                    ->body('Verifique os logs do sistema para mais detalhes.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
