<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListaMaterialEscolars\RelationManagers;

use App\Enums\CategoriaItemMaterial;
use App\Exceptions\ListaMaterialException;
use App\Models\ItemListaMaterial;
use App\Models\ListaMaterialEscolar;
use App\Services\ListaMaterialService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Itens da lista. Todo salvamento passa pelo `ListaMaterialService`, que aplica a trava de itens de uso coletivo.
 */
class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Itens';

    private function lista(): ListaMaterialEscolar
    {
        /** @var ListaMaterialEscolar $lista */
        $lista = $this->getOwnerRecord();

        return $lista;
    }

    private function podeEditar(): bool
    {
        // O status é conferido aqui também: o super_admin passa por cima da policy (Gate::before), mas não pode mexer
        // nos itens de uma lista publicada (o serviço também recusa).
        return ! $this->lista()->estaPublicada() && (bool) auth()->user()?->can('editarItens', $this->lista());
    }

    /**
     * @return list<Component>
     */
    private function campos(): array
    {
        $avaliar = fn (Get $get): array => app(ListaMaterialService::class)->avaliar((string) $get('descricao'), (int) ($get('quantidade') ?: 1));

        return [
            TextInput::make('descricao')
                ->label('Item')
                ->placeholder('Ex.: caderno brochura 96 folhas')
                ->required()
                ->minLength(3)
                ->maxLength(160)
                ->live(onBlur: true)
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $avaliacao = app(ListaMaterialService::class)->avaliar((string) $value, (int) ($get('quantidade') ?: 1));

                    if ($avaliacao['nivel'] === ListaMaterialService::BLOQUEADO) {
                        $fail((string) $avaliacao['motivo']);
                    }
                })
                ->helperText('Descreva o que o aluno precisa, sem citar marca nem loja.'),
            TextInput::make('quantidade')
                ->label('Quantidade')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue(999)
                ->required()
                ->live(onBlur: true),
            TextInput::make('unidade')
                ->label('Unidade')
                ->placeholder('unidade, caixa, pacote, folhas...')
                ->maxLength(30),
            Select::make('categoria')
                ->label('Categoria')
                ->options(CategoriaItemMaterial::class)
                ->default(CategoriaItemMaterial::Papelaria->value)
                ->required(),
            TextInput::make('observacao')
                ->label('Observação')
                ->maxLength(255)
                ->helperText('Tamanho, tipo ou como usar. Não cite marca nem loja.'),
            Toggle::make('uso_individual_confirmado')
                ->label('Confirmo que é de uso individual do aluno e em quantidade coerente com as atividades')
                ->visible(fn (Get $get): bool => $avaliar($get)['nivel'] === ListaMaterialService::ATENCAO)
                ->helperText(fn (Get $get): ?string => $avaliar($get)['motivo'])
                ->accepted(fn (Get $get): bool => $avaliar($get)['nivel'] === ListaMaterialService::ATENCAO)
                ->validationMessages(['accepted' => 'Confirme que o item é de uso individual do aluno para incluí-lo.'])
                ->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('descricao')
            ->columns([
                TextColumn::make('descricao')->label('Item')->weight('bold')->wrap(),
                TextColumn::make('quantidade')->label('Qtd.')->numeric(),
                TextColumn::make('unidade')->label('Unidade')->placeholder('—'),
                TextColumn::make('categoria')->label('Categoria')->badge(),
                TextColumn::make('observacao')->label('Observação')->placeholder('—')->wrap()->toggleable(),
                IconColumn::make('uso_individual_confirmado')->label('Uso individual confirmado')->boolean()->toggleable(),
            ])
            ->headerActions([
                Action::make('adicionar')
                    ->label('Adicionar item')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => $this->podeEditar())
                    ->modalHeading('Adicionar item à lista')
                    ->modalSubmitActionLabel('Adicionar')
                    ->schema($this->campos())
                    ->action(function (array $data): void {
                        try {
                            app(ListaMaterialService::class)->salvarItem($this->lista(), $data);
                        } catch (ListaMaterialException $e) {
                            Notification::make()->title('Item não aceito')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Item adicionado')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->podeEditar())
                    ->modalHeading('Editar item')
                    ->fillForm(fn (ItemListaMaterial $record): array => [
                        'descricao' => $record->descricao,
                        'quantidade' => $record->quantidade,
                        'unidade' => $record->unidade,
                        'categoria' => $record->categoria?->value,
                        'observacao' => $record->observacao,
                        'uso_individual_confirmado' => $record->uso_individual_confirmado,
                    ])
                    ->schema($this->campos())
                    ->action(function (array $data, ItemListaMaterial $record): void {
                        try {
                            app(ListaMaterialService::class)->salvarItem($this->lista(), $data, $record);
                        } catch (ListaMaterialException $e) {
                            Notification::make()->title('Item não aceito')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Item atualizado')->success()->send();
                    }),
                Action::make('remover')
                    ->label('Remover')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => $this->podeEditar())
                    ->requiresConfirmation()
                    ->action(function (ItemListaMaterial $record): void {
                        try {
                            app(ListaMaterialService::class)->removerItem($record);
                        } catch (ListaMaterialException $e) {
                            Notification::make()->title('Não foi possível remover')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Item removido')->success()->send();
                    }),
            ])
            ->stackedOnMobile();
    }
}
