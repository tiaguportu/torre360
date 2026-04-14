<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Schemas\Components\BoletimEdicaoGradesTable;
use App\Models\Nota;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()->can('BoletimEditar:Matricula');
    }

    public array $notas = [];

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

        // Carrega as notas atuais
        $this->notas = Nota::where('matricula_id', $this->record->id)
            ->pluck('valor', 'avaliacao_id')
            ->toArray();
    }

    public function submit(): void
    {
        foreach ($this->notas as $avaliacaoId => $valor) {
            $valorFormatado = ($valor === '' || $valor === null) ? null : (float) str_replace(',', '.', $valor);

            if ($valorFormatado === null) {
                Nota::where('matricula_id', $this->record->id)
                    ->where('avaliacao_id', $avaliacaoId)
                    ->delete();

                continue;
            }

            if ($valorFormatado < 0 || $valorFormatado > 10) {
                continue;
            }

            Nota::updateOrCreate(
                [
                    'matricula_id' => $this->record->id,
                    'avaliacao_id' => $avaliacaoId,
                ],
                [
                    'valor' => $valorFormatado,
                ]
            );
        }

        Notification::make()
            ->title('Notas salvas com sucesso!')
            ->success()
            ->send();

        $this->redirect(MatriculaResource::getUrl('boletim', ['record' => $this->record]));
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
