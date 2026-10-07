<?php

namespace App\Filament\Resources\Cursos\RelationManagers;

use App\Enums\CategoriaExigenciaDocumento;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos Específicos do Curso';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Tipo de Documento')
                    ->required()
                    ->maxLength(255),

                Select::make('categoria_exigencia')
                    ->label('Exigência do Documento')
                    ->options(CategoriaExigenciaDocumento::class)
                    ->default(CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome')
                    ->label('Tipo de Documento')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('categoria_exigencia')
                    ->label('Exigência')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Novo Documento do Curso'),
                AssociateAction::make()
                    ->label('Vincular Documento Existente'),
                Action::make('ajuda')
                    ->label('Ajuda')
                    ->icon('heroicon-o-question-mark-circle')
                    ->color('gray')
                    ->modalHeading('Ajuda: Documentos do Curso')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->form([
                        ViewField::make('help_content')
                            ->view('filament.components.help-content')
                            ->viewData([
                                'content' => $this->getHelpContent(),
                            ]),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }

    private function getHelpContent(): string
    {
        $html = '<div class="space-y-3 text-sm">';
        $html .= '<p>Este painel gerencia os documentos específicos e restritos a este curso.</p>';
        $html .= '<p>Documentos vinculados aqui <strong>só serão exigidos</strong> de alunos e interessados matriculados ou com interesse neste curso. Documentos sem nenhum curso associado no sistema são tratados como de exigência geral para todos os cursos.</p>';
        $html .= '</div>';

        return $html;
    }
}
