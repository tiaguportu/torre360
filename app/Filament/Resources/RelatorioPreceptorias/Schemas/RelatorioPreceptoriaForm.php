<?php

namespace App\Filament\Resources\RelatorioPreceptorias\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Models\Preceptoria;
use App\Models\TemplateRelatorioPreceptoria;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RelatorioPreceptoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Preceptoria Referenciada')
                    ->schema([
                        Select::make('preceptoria_id')
                            ->label('Preceptoria')
                            ->relationship(
                                'preceptoria',
                                'id',
                                fn ($query, $record) => $query
                                    ->when(! $record, fn ($q) => $q->whereDoesntHave('relatorio'))
                                    ->with(['professor', 'matricula.pessoa'])
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Preceptoria $record) => $record->label_exibicao
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique('relatorio_preceptoria', 'preceptoria_id', ignoreRecord: true),
                    ])
                    ->columnSpanFull(),

                Section::make('Conteúdo do Relatório')
                    ->schema([
                        Select::make('_template_id')
                            ->label('Carregar Template')
                            ->options(TemplateRelatorioPreceptoria::query()->orderBy('nome')->pluck('nome', 'id'))
                            ->placeholder('Selecione um template para carregar...')
                            ->live()
                            ->nullable()
                            ->hintAction(
                                Action::make('aplicarTemplate')
                                    ->label('Aplicar Template')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->action(function (array $data, Set $set, $state) {
                                        if (! $state) {
                                            return;
                                        }
                                        $template = TemplateRelatorioPreceptoria::find($state);
                                        if ($template) {
                                            $set('corpo', $template->corpo);
                                        }
                                    })
                                    ->requiresConfirmation()
                                    ->modalHeading('Substituir conteúdo do relatório?')
                                    ->modalDescription('O conteúdo atual do campo "Corpo" será substituído pelo conteúdo do template selecionado. Deseja continuar?')
                            ),

                        TinyEditor::make('corpo')
                            ->label('Corpo do Relatório')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
