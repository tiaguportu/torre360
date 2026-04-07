<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Schemas\Components\BoletimeGradesTable;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class BoletimMatricula extends Page implements HasSchemas
{
    use InteractsWithRecord;
    use InteractsWithSchemas;

    protected static string $resource = MatriculaResource::class;

    protected string $view = 'filament.matriculas.boletim';

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->model($this->record)
            ->components([
                Section::make('Informações da Matrícula')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextEntry::make('aluno_info')
                            ->label('Nome do Aluno(a)')
                            ->state(fn (?Model $record): string => $record?->pessoa?->nome ?? '-'),
                        TextEntry::make('codigo_info')
                            ->label('Matrícula / RA')
                            ->state(fn (?Model $record): string => $record?->codigo ?? '-'),
                        TextEntry::make('turma_info')
                            ->label('Turma Atual')
                            ->state(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                        TextEntry::make('curso_info')
                            ->label('Curso / Nível de Ensino')
                            ->state(fn (?Model $record): string => $record?->turma?->serie?->curso?->nome ?? '-'),
                        TextEntry::make('ano_info')
                            ->label('Ano Pedagógico')
                            ->state(fn (?Model $record): string => $record?->turma?->periodoLetivo?->ano ?? now()->year),
                        TextEntry::make('emissao_info')
                            ->label('Data Emissão')
                            ->state(now()->format('d/m/Y')),
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
            MatriculaResource::getUrl() => 'Matrículas',
            '#' => 'Boletim',
        ];
    }
}
