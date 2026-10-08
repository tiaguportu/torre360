<?php

namespace App\Filament\Resources\Livros\Tables;

use App\Models\Livro;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class LivrosTable
{
    public const SESSION_VISUALIZACAO = 'livros.visualizacao';

    public const VISUALIZACAO_LISTA = 'lista';

    public const VISUALIZACAO_GRADE = 'grade';

    public static function visualizacaoAtual(): string
    {
        return session(self::SESSION_VISUALIZACAO) === self::VISUALIZACAO_GRADE
            ? self::VISUALIZACAO_GRADE
            : self::VISUALIZACAO_LISTA;
    }

    public static function configure(Table $table): Table
    {
        $emGrade = self::visualizacaoAtual() === self::VISUALIZACAO_GRADE;

        return $table
            ->columns($emGrade ? self::colunasGrade() : self::colunasLista())
            ->contentGrid($emGrade ? ['md' => 2, 'lg' => 3, 'xl' => 4] : null)
            ->paginationPageOptions($emGrade ? [12, 24, 48, 96] : [5, 10, 25, 50, 100])
            ->defaultPaginationPageOption($emGrade ? 12 : 10)
            ->filters([
                SelectFilter::make('faixa_etaria')
                    ->label('Faixa Etária')
                    ->options([
                        'Livre' => 'Livre',
                        '0 a 3 anos' => '0 a 3 anos',
                        '4 a 6 anos' => '4 a 6 anos',
                        '7 a 9 anos' => '7 a 9 anos',
                        '10 a 12 anos' => '10 a 12 anos',
                        '13 a 15 anos' => '13 a 15 anos',
                        '16+ anos' => '16+ anos',
                    ]),
            ])
            ->recordActions([
                Action::make('imprimirEtiqueta')
                    ->label('Etiqueta')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->url(fn (Livro $record): string => route('biblioteca.etiquetas.imprimir', ['livros' => $record->id, 'autoprint' => 1]))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('imprimirEtiquetas')
                        ->label('Imprimir Etiquetas em Lote')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->action(function (Collection $records) {
                            $ids = $records->pluck('id')->join(',');

                            return redirect()->away(route('biblioteca.etiquetas.imprimir', ['livros' => $ids, 'autoprint' => 1]));
                        }),
                    DeleteBulkAction::make()
                        ->modalDescription('O histórico de empréstimos já devolvidos das obras selecionadas também será excluído. Obras com empréstimos em aberto são mantidas.')
                        ->failureNotificationTitle(fn (int $successCount, int $totalCount): string => $successCount > 0
                            ? "{$successCount} de {$totalCount} obra(s) excluída(s); as demais têm empréstimos em aberto (registre as devoluções antes)."
                            : 'Nenhuma obra excluída: todas as selecionadas têm empréstimos em aberto. Registre as devoluções antes.'),
                ]),
            ])
            ->defaultSort('titulo')
            ->stackedOnMobile();
    }

    /**
     * @return array<int, Column>
     */
    private static function colunasLista(): array
    {
        return [
            ImageColumn::make('capa')
                ->label('Capa')
                ->disk('public')
                ->visibility('public')
                ->square()
                ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Livro&background=e2e8f0&color=64748b')
                ->toggleable(),
            TextColumn::make('codigo')
                ->label('Tombo')
                ->badge()
                ->color('gray')
                ->searchable()
                ->sortable(),
            TextColumn::make('titulo')
                ->label('Título')
                ->searchable()
                ->sortable(),
            TextColumn::make('autor')
                ->label('Autor')
                ->searchable(),
            TextColumn::make('faixa_etaria')
                ->label('Faixa Etária')
                ->badge()
                ->color('info')
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('editora')
                ->label('Editora')
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('categoria')
                ->label('Categoria')
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('isbn')
                ->label('ISBN')
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('quantidade_disponivel')
                ->label('Disponíveis')
                ->suffix(fn (Livro $record) => ' / '.$record->quantidade_total)
                ->color(fn (Livro $record) => $record->quantidade_disponivel > 0 ? 'success' : 'danger')
                ->sortable(),
        ];
    }

    /**
     * Cartão de cada obra na visualização em grade: capa em destaque, título,
     * autor, tombo, faixa etária e disponibilidade.
     *
     * @return array<int, Stack>
     */
    private static function colunasGrade(): array
    {
        return [
            Stack::make([
                ImageColumn::make('capa')
                    ->label('Capa')
                    ->disk('public')
                    ->visibility('public')
                    ->imageHeight('13rem')
                    ->imageWidth('100%')
                    ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 0.5rem;'])
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Livro&background=e2e8f0&color=64748b&size=256'),
                Stack::make([
                    TextColumn::make('titulo')
                        ->label('Título')
                        ->weight(FontWeight::SemiBold)
                        ->lineClamp(2)
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('autor')
                        ->label('Autor')
                        ->color('gray')
                        ->lineClamp(1)
                        ->searchable(),
                    Split::make([
                        TextColumn::make('codigo')
                            ->label('Tombo')
                            ->prefix('Tombo ')
                            ->badge()
                            ->color('gray')
                            ->searchable()
                            ->sortable()
                            ->grow(false),
                        TextColumn::make('faixa_etaria')
                            ->label('Faixa Etária')
                            ->badge()
                            ->color('info')
                            ->placeholder('—')
                            ->grow(false),
                    ]),
                    TextColumn::make('quantidade_disponivel')
                        ->label('Disponíveis')
                        ->formatStateUsing(fn (Livro $record): string => "{$record->quantidade_disponivel} de {$record->quantidade_total} disponíveis")
                        ->color(fn (Livro $record) => $record->quantidade_disponivel > 0 ? 'success' : 'danger')
                        ->sortable(),
                ])->space(1),
            ])->space(3),
        ];
    }
}
