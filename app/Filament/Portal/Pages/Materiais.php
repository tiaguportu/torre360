<?php

namespace App\Filament\Portal\Pages;

use App\Enums\TipoMaterialAula;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Models\MaterialAula;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Apostilas, vídeo-aulas e links publicados pelos professores para a turma do aluno.
 */
class Materiais extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithMatriculaSelecionada;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Materiais';

    protected static ?string $slug = 'materiais';

    protected string $view = 'filament.portal.pages.materiais';

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getMateriaisQuery())
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->description(fn (MaterialAula $record): ?string => $record->descricao)
                    ->searchable(),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina'),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('data_publicacao')
                    ->label('Publicado em')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoMaterialAula::class),
                SelectFilter::make('disciplina_id')
                    ->label('Disciplina')
                    ->relationship('disciplina', 'nome')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (MaterialAula $record): ?string => $record->url_acesso)
                    ->openUrlInNewTab()
                    ->visible(fn (MaterialAula $record): bool => filled($record->url_acesso)),
            ])
            ->defaultSort('data_publicacao', 'desc')
            ->stackedOnMobile();
    }

    protected function getMateriaisQuery(): Builder
    {
        $turmaId = $this->getMatriculaSelecionada()?->turma_id;

        return MaterialAula::query()
            ->where('turma_id', $turmaId ?? 0)
            ->where('visivel', true)
            ->with(['disciplina']);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Materiais', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('📁', 'Materiais', 'Apostilas, vídeo-aulas e links publicados pelos professores.')
            ->secao('🎯 O que você pode fazer?', [
                ['👧', 'Escolher o aluno', 'Se houver mais de um aluno, selecione de quem quer ver os materiais.'],
                ['📋', 'Lista de materiais', 'Veja título, disciplina, tipo e data de publicação.'],
                ['🔎', 'Filtros', 'Filtre por tipo (apostila, vídeo-aula, link) ou por disciplina.'],
                ['↗️', 'Abrir', 'Clique em "Abrir" para baixar a apostila ou assistir à vídeo-aula/link.'],
            ])
            ->dica('Só aparecem aqui os materiais da turma atual do aluno que o professor marcou como visível.');
    }
}
