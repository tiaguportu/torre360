<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoDesenvolvimentoDocentes\RelationManagers;

use App\Enums\StatusAcaoDesenvolvimento;
use App\Exceptions\DesenvolvimentoDocenteException;
use App\Models\AcaoDesenvolvimentoDocente;
use App\Models\PlanoDesenvolvimentoDocente;
use App\Services\DesenvolvimentoDocenteService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Ações do plano de desenvolvimento. A coordenação cria e edita enquanto o plano não está concluído; o professor
 * registra o andamento das próprias ações.
 */
class AcoesRelationManager extends RelationManager
{
    protected static string $relationship = 'acoes';

    protected static ?string $title = 'Ações do plano';

    private function plano(): PlanoDesenvolvimentoDocente
    {
        /** @var PlanoDesenvolvimentoDocente $plano */
        $plano = $this->getOwnerRecord();

        return $plano;
    }

    private function podeEditarPlano(): bool
    {
        return (bool) auth()->user()?->can('update', $this->plano());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('descricao')
                    ->label('O que será feito')
                    ->helperText('Uma prática observável e como o avanço será acompanhado. Guardado cifrado.')
                    ->required()
                    ->rows(3)
                    ->maxLength(3000)
                    ->columnSpanFull(),
                Select::make('criterio_id')
                    ->label('Critério relacionado (opcional)')
                    ->options(fn (): array => $this->plano()->avaliacao?->ciclo?->instrumento?->criterios?->pluck('nome', 'id')->all() ?? [])
                    ->searchable(),
                DatePicker::make('prazo')
                    ->label('Prazo')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->minDate(fn (?AcaoDesenvolvimentoDocente $record) => $record?->prazo?->lt(today()) ? $record->prazo : today()),
                TextInput::make('ordem')
                    ->label('Ordem')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->default(fn (): int => ((int) $this->plano()->acoes()->max('ordem')) + 1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('criterio'))
            ->recordTitleAttribute('ordem')
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('ordem')->label('#')->sortable(),
                TextColumn::make('descricao')->label('Ação')->wrap()->limit(160),
                TextColumn::make('criterio.nome')->label('Critério')->placeholder('—')->toggleable(),
                TextColumn::make('prazo')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->color(fn (AcaoDesenvolvimentoDocente $record): ?string => $record->estaAtrasada() ? 'danger' : null)
                    ->weight(fn (AcaoDesenvolvimentoDocente $record): ?string => $record->estaAtrasada() ? 'bold' : null),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('resultado')->label('Resultado')->wrap()->limit(120)->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nova ação')
                    ->visible(fn (): bool => $this->podeEditarPlano())
                    ->authorize(fn (): bool => $this->podeEditarPlano()),
            ])
            ->recordActions([
                Action::make('andamento')
                    ->label('Registrar andamento')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (AcaoDesenvolvimentoDocente $record): bool => $record->status->emAberto() && (bool) auth()->user()?->can('atualizarAcao', $this->plano()))
                    ->authorize(fn (): bool => (bool) auth()->user()?->can('atualizarAcao', $this->plano()))
                    ->schema([
                        Select::make('status')
                            ->label('Nova situação')
                            ->options(function (AcaoDesenvolvimentoDocente $record): array {
                                $opcoes = [];

                                if ($record->status === StatusAcaoDesenvolvimento::Pendente) {
                                    $opcoes[StatusAcaoDesenvolvimento::EmAndamento->value] = StatusAcaoDesenvolvimento::EmAndamento->getLabel();
                                }

                                $opcoes[StatusAcaoDesenvolvimento::Concluida->value] = StatusAcaoDesenvolvimento::Concluida->getLabel();

                                if ($this->podeEditarPlano()) {
                                    $opcoes[StatusAcaoDesenvolvimento::Cancelada->value] = 'Cancelada (só a coordenação)';
                                }

                                return $opcoes;
                            })
                            ->required(),
                        Textarea::make('resultado')
                            ->label('Resultado ou justificativa')
                            ->helperText('Obrigatório para concluir ou cancelar (pelo menos 10 caracteres). Guardado cifrado.')
                            ->rows(3)
                            ->maxLength(3000),
                    ])
                    ->action(function (array $data, AcaoDesenvolvimentoDocente $record): void {
                        try {
                            app(DesenvolvimentoDocenteService::class)->atualizarAcao($record, StatusAcaoDesenvolvimento::from($data['status']), $data['resultado'] ?? null, auth()->user());
                        } catch (DesenvolvimentoDocenteException $e) {
                            Notification::make()->title('Não foi possível registrar')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Andamento registrado')->success()->send();
                    }),
                EditAction::make()
                    ->visible(fn (AcaoDesenvolvimentoDocente $record): bool => $record->status->emAberto() && $this->podeEditarPlano())
                    ->authorize(fn (): bool => $this->podeEditarPlano()),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->podeEditarPlano() && $this->plano()->status->value === 'rascunho')
                    ->authorize(fn (): bool => $this->podeEditarPlano()),
            ])
            ->stackedOnMobile();
    }
}
