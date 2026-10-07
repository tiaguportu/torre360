<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoDocumentos\Tables;

use App\Enums\CategoriaExigenciaDocumento;
use App\Models\Curso;
use App\Models\Turma;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class TipoDocumentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Tipo de Documento')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('categoria_exigencia')
                    ->label('Exigência')
                    ->badge()
                    ->sortable(),

                TextColumn::make('cursos.nome_interno')
                    ->label('Cursos Vinculados')
                    ->badge()
                    ->placeholder('Todos os Cursos')
                    ->toggleable(),

                TextColumn::make('turmas.nome')
                    ->label('Turmas Vinculadas')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('modelo_arquivo')
                    ->label('Arquivo')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('modelo_link')
                    ->label('Link')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('categoria_exigencia')
                    ->label('Categoria de Exigência')
                    ->options(CategoriaExigenciaDocumento::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('editar_lote')
                        ->label('Editar em Lote')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('warning')
                        ->visible(fn () => auth()->user()?->can('Update:TipoDocumento'))
                        ->modalHeading('Editar Tipos de Documentos em Lote')
                        ->modalDescription('Selecione os campos que deseja alterar para todos os tipos de documentos selecionados. Campos deixados em branco permanecerão inalterados.')
                        ->modalSubmitActionLabel('Atualizar Selecionados')
                        ->form([
                            Select::make('categoria_exigencia')
                                ->label('Exigência e Visibilidade')
                                ->options(CategoriaExigenciaDocumento::class)
                                ->placeholder('Manter inalterado')
                                ->native(false),

                            Select::make('modo_cursos')
                                ->label('Cursos Vinculados')
                                ->options([
                                    'manter' => 'Manter cursos inalterados',
                                    'substituir' => 'Definir cursos específicos',
                                    'limpar' => 'Remover restrições (aplicar a todos os cursos)',
                                ])
                                ->default('manter')
                                ->live()
                                ->native(false),

                            Select::make('cursos')
                                ->label('Selecionar Cursos')
                                ->options(fn () => Curso::query()
                                    ->orderBy('nome_externo')
                                    ->get()
                                    ->mapWithKeys(fn (Curso $c) => [$c->id => $c->nome_externo ?: $c->nome_interno ?: "Curso #{$c->id}"]))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('modo_cursos') === 'substituir')
                                ->required(fn (Get $get): bool => $get('modo_cursos') === 'substituir'),

                            Select::make('modo_turmas')
                                ->label('Turmas Vinculadas')
                                ->options([
                                    'manter' => 'Manter turmas inalteradas',
                                    'substituir' => 'Definir turmas específicas',
                                    'limpar' => 'Remover restrições de turma',
                                ])
                                ->default('manter')
                                ->live()
                                ->native(false),

                            Select::make('turmas')
                                ->label('Selecionar Turmas')
                                ->options(fn () => Turma::query()
                                    ->orderBy('nome')
                                    ->pluck('nome', 'id'))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('modo_turmas') === 'substituir')
                                ->required(fn (Get $get): bool => $get('modo_turmas') === 'substituir'),

                            TextInput::make('modelo_link')
                                ->label('Link Externo para Modelo ou Instrução')
                                ->url()
                                ->placeholder('https://... (Deixe em branco para manter inalterado)'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $atributosSimples = [];
                            if (! empty($data['categoria_exigencia'])) {
                                $atributosSimples['categoria_exigencia'] = $data['categoria_exigencia'];
                            }
                            if (isset($data['modelo_link']) && $data['modelo_link'] !== '') {
                                $atributosSimples['modelo_link'] = $data['modelo_link'];
                            }

                            $alterouCursos = match ($data['modo_cursos'] ?? 'manter') {
                                'substituir', 'limpar' => true,
                                default => false,
                            };

                            $alterouTurmas = match ($data['modo_turmas'] ?? 'manter') {
                                'substituir', 'limpar' => true,
                                default => false,
                            };

                            if (empty($atributosSimples) && ! $alterouCursos && ! $alterouTurmas) {
                                Notification::make()
                                    ->title('Nenhuma alteração selecionada')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            foreach ($records as $record) {
                                if (! empty($atributosSimples)) {
                                    $record->update($atributosSimples);
                                }

                                if (($data['modo_cursos'] ?? 'manter') === 'substituir') {
                                    $record->cursos()->sync($data['cursos'] ?? []);
                                } elseif (($data['modo_cursos'] ?? 'manter') === 'limpar') {
                                    $record->cursos()->detach();
                                }

                                if (($data['modo_turmas'] ?? 'manter') === 'substituir') {
                                    $record->turmas()->sync($data['turmas'] ?? []);
                                } elseif (($data['modo_turmas'] ?? 'manter') === 'limpar') {
                                    $record->turmas()->detach();
                                }
                            }

                            $count = $records->count();
                            Notification::make()
                                ->title("{$count} tipo(s) de documento atualizado(s) com sucesso!")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
