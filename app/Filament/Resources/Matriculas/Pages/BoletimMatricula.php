<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Schemas\Components\BoletimeGradesTable;
use App\Models\EtapaAvaliativa;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
                        TextEntry::make('aluno')
                            ->label('Nome do Aluno(a)')
                            ->state(fn (?Model $record): string => $record?->pessoa?->nome ?? '-'),
                        TextEntry::make('matricula')
                            ->label('Matrícula')
                            ->state(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                        TextEntry::make('Turma')
                            ->label('Turma Atual')
                            ->state(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                        TextEntry::make('curso')
                            ->label('Curso')
                            ->state(fn (?Model $record): string => $record?->turma?->serie?->curso?->nome_externo ?? $record?->turma?->serie?->curso?->nome_interno ?? '-'),
                        TextEntry::make('periodo_letivo')
                            ->label('Período Letivo')
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimir_pdf')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->modalHeading('Imprimir Boletim')
                ->modalDescription('Selecione se deseja imprimir uma etapa específica ou o boletim completo.')
                ->modalSubmitActionLabel('Baixar PDF')
                ->form([
                    Select::make('etapa_id')
                        ->label('Etapa Avaliativa')
                        ->options(function () {
                            $etapas = EtapaAvaliativa::query()
                                ->whereHas('avaliacoes.notas', fn ($q) => $q->where('matricula_id', $this->record->id)->whereNotNull('valor'))
                                ->orderBy('id')
                                ->pluck('nome', 'id')
                                ->toArray();

                            return [0 => 'Todas as Etapas que já ocorreram'] + $etapas;
                        })
                        ->default(0)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $params = ['record' => $this->record];
                    if ($data['etapa_id'] > 0) {
                        $params['etapa_id'] = $data['etapa_id'];
                    }

                    return redirect()->route('matriculas.boletim.download', $params);
                }),

            Action::make('editar_notas')
                ->label('Editar Notas')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->url(fn (): string => MatriculaResource::getUrl('boletim.editar', ['record' => $this->record]))
                ->visible(fn (): bool => auth()->user()->can('boletim_editar_matricula')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            MatriculaResource::getUrl() => 'Matrículas',
            '#' => 'Boletim',
        ];
    }
}
