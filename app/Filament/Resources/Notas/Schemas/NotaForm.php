<?php

namespace App\Filament\Resources\Notas\Schemas;

use App\Models\Avaliacao;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NotaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('avaliacao_id')
                    ->relationship('avaliacao', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->label_exibicao)
                    ->searchable()
                    ->preload()
                    ->label('Avaliação')
                    ->required()
                    ->live(),
                Select::make('matricula_id')
                    ->relationship('matricula', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->periodoLetivo?->nome} - {$record->turma?->nome} - {$record->pessoa?->nome}")
                    ->searchable()
                    ->preload()
                    ->label('Matrícula')
                    ->required(),
                TextInput::make('valor')
                    ->label('Nota')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(function (callable $get) {
                        $avaliacaoId = $get('avaliacao_id');
                        if ($avaliacaoId) {
                            $avaliacao = Avaliacao::find($avaliacaoId);

                            return $avaliacao?->nota_maxima ?? 10;
                        }

                        return 10;
                    })
                    ->validationMessages([
                        'max' => 'A nota não pode ser maior que a nota máxima da avaliação (:max).',
                    ]),
            ]);
    }
}
