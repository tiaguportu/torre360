<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Models\RegistroRotinaDiaria;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AgendaDiaria extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithMatriculaSelecionada;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Agenda Diária';

    protected static ?string $slug = 'agenda-diaria';

    protected string $view = 'filament.portal.pages.agenda-diaria';

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    protected function getRegistrosQuery(): Builder
    {
        return RegistroRotinaDiaria::query()
            ->where('matricula_id', $this->getMatriculaSelecionada()?->id ?? 0)
            ->with('refeicoes');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getRegistrosQuery())
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('humor')
                    ->label('Humor')
                    ->badge(),
                TextColumn::make('hora_inicio_soneca')
                    ->label('Soneca')
                    ->formatStateUsing(fn ($record) => $record->temSoneca()
                        ? substr((string) $record->hora_inicio_soneca, 0, 5).' – '.substr((string) $record->hora_fim_soneca, 0, 5)
                        : '—'),
                TextColumn::make('refeicoes_count')
                    ->label('Refeições')
                    ->counts('refeicoes'),
            ])
            ->recordActions([
                Action::make('ver_detalhes')
                    ->label('Ver Detalhes')
                    ->icon('heroicon-o-eye')
                    ->schema(fn (Schema $schema): Schema => $schema->components([
                        Section::make('Rotina do Dia')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('humor')
                                    ->label('Humor')
                                    ->badge(),
                                TextEntry::make('soneca')
                                    ->label('Soneca')
                                    ->state(fn ($record) => $record->temSoneca()
                                        ? substr((string) $record->hora_inicio_soneca, 0, 5).' – '.substr((string) $record->hora_fim_soneca, 0, 5)
                                        : 'Não registrada'),
                                TextEntry::make('atividades_dia')
                                    ->label('Atividades do Dia')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('higiene_observacoes')
                                    ->label('Higiene')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Refeições')
                            ->schema([
                                RepeatableEntry::make('refeicoes')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('nome')->label('Refeição'),
                                        TextEntry::make('quantidade')->label('Quanto Comeu')->badge(),
                                        TextEntry::make('observacao')->label('Observação')->placeholder('—'),
                                    ])
                                    ->columns(3),
                            ]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
            ])
            ->defaultSort('data', 'desc')
            ->paginated([10, 25]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Agenda Diária', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🌞', 'Agenda Diária', 'Acompanhe o dia a dia do aluno na Educação Infantil: humor, soneca, refeições e atividades.')
            ->secao('🎯 O que você pode fazer?', [
                ['📅', 'Ver o histórico', 'Consulte os registros por data.'],
                ['👁️', 'Ver Detalhes', 'Veja a lista completa de refeições do dia, com o quanto a criança comeu de cada uma.'],
            ])
            ->dica('O registro é feito pelo professor/auxiliar da turma, um por dia.');
    }
}
