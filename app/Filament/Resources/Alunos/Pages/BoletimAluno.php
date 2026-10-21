<?php

namespace App\Filament\Resources\Alunos\Pages;

use App\Filament\Resources\Alunos\AlunoResource;
use App\Filament\Schemas\Components\BoletimeGradesTable;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ViewField;
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
                BoletimeGradesTable::make()
                    ->stackedOnMobile(),
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Detalhes de Aluno')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Boletim Escolar do Aluno, consolidando notas por etapa avaliativa, médias e frequência.</p>';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Recursos:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li>Consulte o aproveitamento pedagógico do aluno nas disciplinas da matriz.</li>';
        $html .= '<li>Acompanhe o total de presenças e faltas registradas no período letivo.</li>';
        $html .= '</ul>';

        return $html;
    }
}
