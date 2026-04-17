<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Enums\SituacaoMatricula;
use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\Matricula;
use App\Models\Pessoa;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListMatriculas extends ListRecords
{
    protected static string $resource = MatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('matriculaEmLote')
                ->label('Matrícula em Lote')
                ->icon('heroicon-o-users')
                ->color('info')
                ->form([
                    Select::make('turma_id')
                        ->label('Turma')
                        ->relationship('turma', 'nome', fn ($query) => $query->whereNotNull('nome'))
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('aluno_ids')
                        ->label('Alunos')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Pessoa::query()
                            ->where('nome', 'like', "%{$search}%")
                            ->whereHas('users', fn ($q) => $q->role('aluno'))
                            ->limit(50)
                            ->pluck('nome', 'id')
                            ->toArray()
                        )
                        ->getOptionLabelsUsing(fn (array $values): array => Pessoa::query()
                            ->whereIn('id', $values)
                            ->pluck('nome', 'id')
                            ->toArray()
                        )
                        ->required(),
                    Select::make('situacao')
                        ->label('Situação')
                        ->options(SituacaoMatricula::class)
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data) {
                    foreach ($data['aluno_ids'] as $pessoaId) {
                        Matricula::create([
                            'pessoa_id' => $pessoaId,
                            'turma_id' => $data['turma_id'],
                            'situacao' => $data['situacao'],
                        ]);
                    }
                })
                ->successNotificationTitle('Matrículas criadas com sucesso!'),
            CreateAction::make(),
        ];
    }
}
