<?php

namespace App\Filament\Resources\Funcionarios\RelationManagers;

use App\Filament\Resources\Funcionarios\Schemas\CamposDeContrato;
use App\Models\ContratoTrabalho;
use App\Services\VinculoFuncionarioService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContratosTrabalhoRelationManager extends RelationManager
{
    protected static string $relationship = 'contratosTrabalho';

    protected static ?string $title = 'Contratos de Trabalho';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('vigencia_inicio')
                    ->label('Início da Vigência')
                    ->native(false)
                    ->default(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->data_admissao)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Definido automaticamente a partir da data de admissão.'),
                CamposDeContrato::salario('salario', 'Salário (R$)')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('vigencia_inicio')
            ->description(fn (RelationManager $livewire): string => $livewire->getOwnerRecord()->resumoVinculo())
            ->defaultSort('vigencia_inicio', 'desc')
            ->columns([
                TextColumn::make('vigencia_inicio')
                    ->label('Início')
                    ->date('d/m/Y'),
                TextColumn::make('vigencia_fim')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->placeholder(fn (RelationManager $livewire): string => $livewire->getOwnerRecord()->isAtivo() ? 'Vigente' : 'Sem encerramento')
                    ->color(function ($state, RelationManager $livewire) {
                        if ($state !== null) {
                            return null;
                        }

                        return $livewire->getOwnerRecord()->isAtivo() ? 'success' : 'warning';
                    }),
                TextColumn::make('salario')
                    ->label('Salário')
                    ->money('BRL'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar contrato inicial')
                    ->icon('heroicon-o-document-plus')
                    ->visible(fn (RelationManager $livewire): bool => $livewire->getOwnerRecord()->isAtivo()
                        && ! $livewire->getOwnerRecord()->contratosTrabalho()->exists()
                        && (bool) auth()->user()?->can('create', ContratoTrabalho::class))
                    ->using(function (array $data, RelationManager $livewire): ContratoTrabalho {
                        return app(VinculoFuncionarioService::class)->abrirContratoInicial(
                            $livewire->getOwnerRecord(),
                            $data['salario'],
                            auth()->user()
                        );
                    }),
            ])
            ->actions([
                Action::make('aditivo')
                    ->label('Registrar Aditivo')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('warning')
                    ->visible(fn (ContratoTrabalho $record, RelationManager $livewire): bool => $record->vigencia_fim === null
                        && $livewire->getOwnerRecord()->isAtivo()
                        && (bool) auth()->user()?->can('update', $record))
                    ->schema([
                        CamposDeContrato::salario('novo_salario', 'Novo Salário (R$)')
                            ->required(),
                        DatePicker::make('data_aditivo')
                            ->label('Data do Aditivo')
                            ->native(false)
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data, ContratoTrabalho $record): void {
                        try {
                            $record->registrarAditivo((string) $data['novo_salario'], (string) $data['data_aditivo']);
                            Notification::make()->title('Aditivo registrado com sucesso')->success()->send();
                        } catch (\DomainException $e) {
                            Notification::make()->title('Não foi possível registrar o aditivo')->body($e->getMessage())->danger()->send();
                        }
                    }),
                DeleteAction::make()
                    ->visible(function (ContratoTrabalho $record, RelationManager $livewire): bool {
                        if (! $livewire->getOwnerRecord()->isAtivo()) {
                            return false;
                        }

                        if (! auth()->user()?->can('delete', $record)) {
                            return false;
                        }

                        $maisRecente = $livewire->getOwnerRecord()->contratosTrabalho()
                            ->reorder()
                            ->orderByDesc('vigencia_inicio')
                            ->orderByDesc('id')
                            ->first();

                        return $maisRecente?->id === $record->id;
                    })
                    ->using(function (ContratoTrabalho $record): void {
                        app(VinculoFuncionarioService::class)->removerUltimaVigencia($record);
                    }),
            ])
            ->bulkActions([])
            ->stackedOnMobile();
    }
}
