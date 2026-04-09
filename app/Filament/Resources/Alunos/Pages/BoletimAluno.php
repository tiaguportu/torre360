<?php

namespace App\Filament\Resources\Alunos\Pages;

use App\Filament\Resources\Alunos\AlunoResource;
use App\Filament\Schemas\Components\BoletimeGradesTable;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class BoletimAluno extends Page implements HasSchemas
{
    use InteractsWithRecord;
    use InteractsWithSchemas;

    protected static string $resource = AlunoResource::class;

    protected string $view = 'filament.alunos.boletim';

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->model($this->record)
            ->components([
                Section::make('Informações da Matrícula')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Placeholder::make('aluno_info')
                            ->label('Nome do Aluno(a)')
                            ->content(fn (?Model $record): string => $record?->nome ?? '-'),
                        Placeholder::make('codigo_info')
                            ->label('Matrícula / RA')
                            ->content(fn (?Model $record): string => $record?->matriculas?->first()?->turma?->nome ?? '-'),
                        Placeholder::make('turma_info')
                            ->label('Turma Atual')
                            ->content(fn (?Model $record): string => $record?->matriculas?->first()?->turma?->nome ?? '-'),
                        Placeholder::make('curso_info')
                            ->label('Curso / Nível de Ensino')
                            ->content(fn (?Model $record): string => $record?->matriculas?->first()?->turma?->serie?->curso?->nome ?? '-'),
                        Placeholder::make('ano_info')
                            ->label('Ano Pedagógico')
                            ->content(fn (?Model $record): string => $record?->matriculas?->first()?->turma?->periodoLetivo?->ano ?? now()->year),
                        Placeholder::make('emissao_info')
                            ->label('Data Emissão')
                            ->content(now()->format('d/m/Y')),
                    ])
                    ->columns(['md' => 2, 'default' => 1]),
                BoletimeGradesTable::make(),
            ]);
    }

    protected static ?string $title = 'Boletim Escolar';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getBreadcrumbs(): array
    {
        return [
            AlunoResource::getUrl() => 'Alunos',
            '#' => 'Boletim',
        ];
    }
}
