<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Schemas\Components\BoletimEdicaoGradesTable;
use App\Models\Nota;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda - Editar Notas')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $content = "<div class='space-y-4'>";
        $content .= '<p>Esta página permite o lançamento e a edição das notas do aluno em cada disciplina e avaliação.</p>';

        $content .= "<h3 class='font-bold'>Instruções:</h3>";
        $content .= "<ul class='list-disc ml-4'>";
        $content .= '<li>As notas devem ser inseridas no formato decimal (ex: 7.5 ou 7,5).</li>';
        $content .= '<li>Valores permitidos são de 0 a 10.</li>';
        $content .= '<li>Campos vazios indicam que a nota ainda não foi lançada.</li>';
        $content .= "<li>Clique em <strong>'Salvar Alterações'</strong> ao final da página para gravar os dados.</li>";
        $content .= '</ul>';
        $content .= '</div>';

        return $content;
    }

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
