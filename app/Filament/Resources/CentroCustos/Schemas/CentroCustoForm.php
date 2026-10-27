<?php

namespace App\Filament\Resources\CentroCustos\Schemas;

use App\Models\Curso;
use App\Models\Turma;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CentroCustoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Nome do Centro de Custo')
                    ->required()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true)
                    ->required(),
                Section::make('Resultado por turma')
                    ->description('Como as despesas deste centro entram no resultado realizado de cada turma. Sem vínculo, a despesa é custo indireto e é rateada entre todas as turmas.')
                    ->columns(2)
                    ->schema([
                        Select::make('turma_id')
                            ->label('Custo direto de uma turma')
                            ->options(fn (): array => Turma::query()->orderBy('nome')->pluck('nome', 'id')->all())
                            ->searchable()
                            ->native(false)
                            ->helperText('As despesas deste centro vão inteiras para a turma.'),
                        Select::make('curso_id')
                            ->label('Custo direto de um curso')
                            ->options(fn (): array => Curso::query()->orderBy('nome_interno')->get()->mapWithKeys(fn (Curso $c): array => [$c->id => $c->nome])->all())
                            ->searchable()
                            ->native(false)
                            ->helperText('As despesas deste centro são divididas entre as turmas do curso, pelo número de alunos.'),
                        Toggle::make('fora_do_resultado_das_turmas')
                            ->label('Fora do resultado das turmas')
                            ->helperText('Folha dos professores (o custo docente já vem da apuração de hora-aula), investimentos e impostos.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
