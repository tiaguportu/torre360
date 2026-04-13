<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Schemas\Components\BoletimEdicaoGradesTable;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditarBoletimMatricula extends Page implements HasSchemas
{
    use InteractsWithRecord;
    use InteractsWithSchemas;

    protected static string $resource = MatriculaResource::class;

    protected string $view = 'filament.matriculas.editar-boletim';

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->model($this->record)
            ->components([
                Section::make('Informações da Matrícula')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextEntry::make('aluno')
                            ->label('Nome do Aluno(a)')
                            ->state(fn (?Model $record): string => $record?->pessoa?->nome ?? '-'),
                        TextEntry::make('Turma')
                            ->label('Turma Atual')
                            ->state(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                    ])
                    ->columns(['md' => 2, 'default' => 1]),

                BoletimEdicaoGradesTable::make(),
            ]);
    }

    protected static ?string $title = 'Editar Notas do Boletim';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getBreadcrumbs(): array
    {
        return [
            MatriculaResource::getUrl() => 'Matrículas',
            MatriculaResource::getUrl('boletim', ['record' => $this->record]) => 'Boletim',
            '#' => 'Editar Notas',
        ];
    }
}
