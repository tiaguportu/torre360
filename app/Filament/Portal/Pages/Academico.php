<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\Matricula;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Academico extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Boletins';

    protected static ?string $slug = 'academico';

    protected string $view = 'filament.portal.pages.academico';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getMatriculasQuery())
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('turma.nome')
                    ->label('Turma'),
                TextColumn::make('periodoLetivo.nome')
                    ->label('Período Letivo'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('baixar_boletim')
                    ->label('Baixar Boletim')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Matricula $record) => route('matriculas.boletim.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('id', 'desc')
            ->stackedOnMobile();
    }

    protected function getMatriculasQuery(): Builder
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Matricula::query()
            ->whereIn('pessoa_id', $idsAcessiveis)
            ->with(['pessoa', 'turma', 'periodoLetivo']);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Boletins', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('📄', 'Boletins', 'Boletim escolar de cada matrícula.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Lista de matrículas', 'Veja aluno, turma, período letivo e situação.'],
                ['⬇️', 'Baixar Boletim', 'Baixe o boletim em PDF pelo botão "Baixar Boletim" de cada matrícula.'],
            ])
            ->dica('O boletim reflete as notas lançadas até o momento.');
    }
}
