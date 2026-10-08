<?php

namespace App\Filament\Resources\Emprestimos\Schemas;

use App\Enums\SituacaoMatricula;
use App\Models\Livro;
use App\Models\Matricula;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmprestimoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Empréstimo')
                    ->columns(2)
                    ->schema([
                        Select::make('livro_id')
                            ->label('Livro')
                            ->options(fn () => Livro::where('quantidade_disponivel', '>', 0)->pluck('titulo', 'id'))
                            ->searchable()
                            ->required()
                            ->helperText('Só aparecem livros com exemplar disponível.'),
                        Select::make('matricula_id')
                            ->label('Aluno')
                            ->options(fn () => Matricula::where('situacao', SituacaoMatricula::ATIVA)->with('pessoa')->get()->mapWithKeys(fn (Matricula $m) => [$m->id => $m->label_exibicao]))
                            ->searchable()
                            ->required(),
                        DatePicker::make('data_emprestimo')
                            ->label('Data do Empréstimo')
                            ->native(false)
                            ->default(now())
                            ->required(),
                        DatePicker::make('data_prevista_devolucao')
                            ->label('Devolução Prevista')
                            ->native(false)
                            ->default(now()->addDays(14))
                            ->afterOrEqual('data_emprestimo')
                            ->required(),
                    ]),
            ]);
    }
}
