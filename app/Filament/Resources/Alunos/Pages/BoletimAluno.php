<?php

namespace App\Filament\Resources\Alunos\Pages;

use App\Filament\Resources\Alunos\AlunoResource;
use App\Filament\Schemas\Components\BoletimeGradesTable;
use App\Filament\Schemas\Components\BoletimeStatsSection;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

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
                Section::make('Informações do Aluno')
                    ->icon('heroicon-o-user-circle')
                    ->description('Dados cadastrais do aluno vinculados ao seu registro acadêmico.')
                    ->schema(AlunoResource::form(new Schema)->getComponents())
                    ->columns(3)
                    ->disabled()
                    ->aside(),

                BoletimeStatsSection::make(),
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
