<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\Matricula;
use App\Models\Pessoa;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
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
                            ->whereHas('perfis', fn ($q) => $q->where('nome', 'like', '%Aluno%'))
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
                    Select::make('situacao_matricula_id')
                        ->label('Situação')
                        ->relationship('situacaoMatricula', 'nome', fn ($query) => $query->whereNotNull('nome'))
                        ->required()
                        ->searchable()
                        ->preload(),
                    DatePicker::make('data_matricula')
                        ->label('Data da Matrícula')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data) {
                    foreach ($data['aluno_ids'] as $pessoaId) {
                        Matricula::create([
                            'pessoa_id' => $pessoaId,
                            'turma_id' => $data['turma_id'],
                            'situacao_matricula_id' => $data['situacao_matricula_id'],
                            'data_matricula' => $data['data_matricula'],
                        ]);
                    }
                })
                ->successNotificationTitle('Matrículas criadas com sucesso!'),
            CreateAction::make(),
        ];
    }
}
