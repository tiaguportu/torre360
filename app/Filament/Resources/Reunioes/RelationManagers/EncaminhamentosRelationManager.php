<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\RelationManagers;

use App\Filament\Resources\Reunioes\Schemas\ReuniaoForm;
use App\Models\Reuniao;
use App\Models\ReuniaoEncaminhamento;
use App\Services\ReuniaoService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * O que ficou combinado na reunião, com responsável e prazo. Concluir é do responsável ou de quem gerencia a reunião.
 */
class EncaminhamentosRelationManager extends RelationManager
{
    protected static string $relationship = 'encaminhamentos';

    protected static ?string $title = 'Encaminhamentos';

    private function reuniao(): Reuniao
    {
        /** @var Reuniao $reuniao */
        $reuniao = $this->getOwnerRecord();

        return $reuniao;
    }

    private function podeAdicionar(): bool
    {
        $reuniao = $this->reuniao();

        return ! $reuniao->estaCancelada() && ! $reuniao->ataEstaFechada() && (bool) auth()->user()?->can('gerenciar', $reuniao);
    }

    private function podeConcluir(ReuniaoEncaminhamento $encaminhamento): bool
    {
        return $encaminhamento->estaAberto() && (bool) auth()->user()?->can('update', $encaminhamento);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['responsavel', 'matricula.pessoa']))
            ->recordTitleAttribute('descricao')
            ->defaultSort('prazo')
            ->paginated(false)
            ->columns([
                TextColumn::make('descricao')->label('O que foi combinado')->wrap()->weight('bold'),
                TextColumn::make('matricula.pessoa.nome')->label('Aluno')->placeholder('—')->toggleable(),
                TextColumn::make('responsavel.name')->label('Responsável')->placeholder('Sem responsável'),
                TextColumn::make('prazo')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->placeholder('Sem prazo')
                    ->color(fn (ReuniaoEncaminhamento $record): ?string => $record->estaVencido() ? 'danger' : null)
                    ->description(fn (ReuniaoEncaminhamento $record): ?string => $record->estaVencido() ? 'Vencido' : null),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('concluido_em')->label('Encerrado em')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                Action::make('novo')
                    ->label('Novo encaminhamento')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => $this->podeAdicionar())
                    ->modalHeading('Novo encaminhamento')
                    ->schema([
                        Textarea::make('descricao')->label('O que foi combinado')->rows(3)->required()->maxLength(300),
                        Select::make('responsavel_user_id')->label('Responsável')->options(fn (): array => ReuniaoForm::equipe())->searchable()->native(false),
                        DatePicker::make('prazo')->label('Prazo')->native(false)->displayFormat('d/m/Y')->minDate(today()),
                    ])
                    ->action(function (array $data): void {
                        abort_unless($this->podeAdicionar(), 403);

                        try {
                            app(ReuniaoService::class)->adicionarEncaminhamento($this->reuniao(), $data, auth()->user());
                        } catch (\DomainException $e) {
                            Notification::make()->title('Encaminhamento recusado')->body($e->getMessage())->danger()->persistent()->send();

                            return;
                        }

                        Notification::make()->title('Encaminhamento registrado')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('concluir')
                    ->label('Concluir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ReuniaoEncaminhamento $record): bool => $this->podeConcluir($record))
                    ->modalHeading('Concluir o encaminhamento')
                    ->schema([
                        Textarea::make('observacao')->label('Como foi resolvido (opcional)')->rows(3)->maxLength(1000),
                    ])
                    ->action(function (ReuniaoEncaminhamento $record, array $data): void {
                        abort_unless($this->podeConcluir($record), 403);

                        try {
                            app(ReuniaoService::class)->concluirEncaminhamento($record, auth()->user(), $data['observacao'] ?? null);
                        } catch (\DomainException $e) {
                            Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Encaminhamento concluído')->success()->send();
                    }),
                Action::make('cancelar_encaminhamento')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ReuniaoEncaminhamento $record): bool => $record->estaAberto() && (bool) auth()->user()?->can('gerenciar', $this->reuniao()))
                    ->modalHeading('Cancelar o encaminhamento')
                    ->schema([
                        Textarea::make('motivo')->label('Por que deixou de valer')->rows(2)->required()->maxLength(500),
                    ])
                    ->action(function (ReuniaoEncaminhamento $record, array $data): void {
                        abort_unless(auth()->user()?->can('gerenciar', $this->reuniao()), 403);

                        try {
                            app(ReuniaoService::class)->cancelarEncaminhamento($record, auth()->user(), (string) ($data['motivo'] ?? ''));
                        } catch (\DomainException $e) {
                            Notification::make()->title('Não foi possível cancelar')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Encaminhamento cancelado')->success()->send();
                    }),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Nenhum encaminhamento')
            ->emptyStateDescription('Registre o que ficou combinado, com responsável e prazo.');
    }
}
