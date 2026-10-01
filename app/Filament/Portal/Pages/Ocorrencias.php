<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\OcorrenciaEscolar;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Ocorrencias extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Ocorrências';

    protected static ?string $slug = 'ocorrencias';

    protected string $view = 'filament.portal.pages.ocorrencias';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getOcorrenciasQuery())
            ->columns([
                TextColumn::make('data_hora')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('tipoOcorrencia.nome')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (OcorrenciaEscolar $record) => match ($record->tipoOcorrencia?->gravidade) {
                        'positiva' => 'success',
                        'leve' => 'info',
                        'media' => 'warning',
                        'grave' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('descricao')
                    ->label('Relato')
                    ->limit(60)
                    ->wrap(),
                IconColumn::make('notificar_responsaveis')
                    ->label('Notificado')
                    ->boolean(),
            ])
            ->defaultSort('data_hora', 'desc')
            ->stackedOnMobile();
    }

    protected function getOcorrenciasQuery(): Builder
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return OcorrenciaEscolar::query()
            ->whereHas('matricula', fn (Builder $q) => $q->whereIn('pessoa_id', $idsAcessiveis))
            ->with(['matricula.pessoa', 'tipoOcorrencia']);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Ocorrências', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('📢', 'Ocorrências', 'Registros da rotina escolar do aluno.')
            ->secao('🎯 O que você encontra aqui?', [
                ['🕒', 'Data e hora', 'Quando a ocorrência foi registrada.'],
                ['🏷️', 'Tipo e relato', 'O tipo da ocorrência e a descrição feita pela escola.'],
                ['🔔', 'Notificado', 'Indica se a família já foi avisada.'],
            ])
            ->dica('Quer conversar sobre um registro? Abra um chamado na Central de Atendimento.');
    }
}
