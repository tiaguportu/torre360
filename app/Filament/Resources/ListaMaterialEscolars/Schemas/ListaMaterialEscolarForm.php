<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListaMaterialEscolars\Schemas;

use App\Models\ListaMaterialEscolar;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListaMaterialEscolarForm
{
    public static function configure(Schema $schema): Schema
    {
        $publicada = fn (?ListaMaterialEscolar $record): bool => $record?->estaPublicada() ?? false;

        return $schema
            ->components([
                Section::make('Lista')
                    ->columns(2)
                    ->schema([
                        Select::make('serie_id')
                            ->label('Série')
                            ->options(fn (): array => Serie::query()->with('curso')->orderBy('nome')->get()
                                ->mapWithKeys(fn (Serie $s): array => [$s->id => trim($s->nome.' — '.($s->curso?->nome_externo ?? $s->curso?->nome_interno ?? ''), ' —')])->all())
                            ->searchable()
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('periodo_letivo_id')
                            ->label('Período letivo')
                            ->options(fn (): array => PeriodoLetivo::query()->orderByDesc('data_inicio')->pluck('nome', 'id')->all())
                            ->searchable()
                            ->required()
                            ->disabledOn('edit')
                            ->helperText('A lista vale para os alunos das turmas desta série neste período.'),
                        Textarea::make('observacao')
                            ->label('Recado para as famílias (opcional)')
                            ->rows(3)
                            ->maxLength(2000)
                            ->disabled($publicada)
                            ->helperText('Aparece no topo da lista. Por exemplo: "Reaproveite o material do ano passado que estiver em bom estado." Não cite marca nem loja.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
