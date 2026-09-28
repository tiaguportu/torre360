<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusRematricula;
use App\Models\Matricula;
use App\Models\PeriodoRematricula;
use App\Models\Rematricula as RematriculaModel;
use App\Models\Serie;
use App\Models\Turno;
use App\Services\RematriculaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Rematricula extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Rematrícula Online';

    protected static ?string $slug = 'rematricula';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.portal.pages.rematricula';

    public ?PeriodoRematricula $periodoAtivo = null;

    public function mount(RematriculaService $service): void
    {
        $this->periodoAtivo = $service->obterPeriodoAtivo();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getMatriculasElegiveisQuery())
            ->emptyStateHeading('Nenhum estudante com rematrícula pendente')
            ->emptyStateDescription('Todos os seus dependentes já estão com rematrícula encaminhada ou não há campanha vigente.')
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Estudante')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('turma.serie.nome')
                    ->label('Série / Ano Atual'),

                TextColumn::make('turma.nome')
                    ->label('Turma Atual'),

                TextColumn::make('status_rematricula')
                    ->label('Situação da Rematrícula')
                    ->state(function (Matricula $record): string {
                        $rematricula = $this->getRematriculaDoAluno($record);
                        if (! $rematricula) {
                            return 'Aguardando Início';
                        }

                        return $rematricula->status?->getLabel() ?? 'Iniciada';
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'Rematrícula Confirmada' => 'success',
                            'Aguardando Assinatura do Contrato' => 'warning',
                            'Dados Confirmados' => 'info',
                            'Aguardando Início' => 'gray',
                            default => 'primary',
                        };
                    }),

                TextColumn::make('data_rematricula')
                    ->label('Data de Confirmação')
                    ->state(function (Matricula $record): ?string {
                        $rematricula = $this->getRematriculaDoAluno($record);

                        return $rematricula?->data_confirmacao?->format('d/m/Y H:i');
                    })
                    ->placeholder('Pendente'),
            ])
            ->recordActions([
                Action::make('iniciar_rematricula')
                    ->label('Realizar Rematrícula')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->visible(function (Matricula $record): bool {
                        if (! $this->periodoAtivo) {
                            return false;
                        }
                        $rematricula = $this->getRematriculaDoAluno($record);

                        return ! $rematricula || $rematricula->status !== StatusRematricula::Confirmada;
                    })
                    ->modalHeading(fn (Matricula $record) => "Rematrícula: {$record->pessoa?->nome}")
                    ->modalDescription('Confirme as preferências para o próximo ano letivo. Ao prosseguir, os dados serão encaminhados para a secretaria.')
                    ->form([
                        Select::make('serie_destino_id')
                            ->label('Série / Ano Pretendido para o Próximo Período')
                            ->options(Serie::pluck('nome', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('turno_pretendido_id')
                            ->label('Turno de Preferência')
                            ->options(Turno::pluck('nome', 'id'))
                            ->required(),

                        Textarea::make('observacoes')
                            ->label('Observações ou solicitações especiais da família')
                            ->placeholder('Ex: Preferência por colegas de turma, restrições ou necessidades específicas.')
                            ->rows(2),
                    ])
                    ->action(function (Matricula $record, array $data, RematriculaService $service) {
                        try {
                            $rematricula = $service->iniciarOuObter($record, $this->periodoAtivo, auth()->user());

                            $rematricula->update([
                                'serie_destino_id' => $data['serie_destino_id'],
                                'turno_pretendido_id' => $data['turno_pretendido_id'],
                                'observacoes' => $data['observacoes'] ?? null,
                                'status' => StatusRematricula::DadosConfirmados,
                                'data_confirmacao' => now(),
                            ]);

                            // Efetiva a rematrícula gerando os registros de destino
                            $service->efetivar($rematricula);

                            Notification::make()
                                ->title('Rematrícula Confirmada!')
                                ->body("A rematrícula do(a) estudante {$record->pessoa?->nome} foi registrada com sucesso!")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erro ao processar rematrícula')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->stackedOnMobile();
    }

    protected function getMatriculasElegiveisQuery(): Builder
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        $query = Matricula::query()->whereIn('pessoa_id', $idsAcessiveis);

        if ($this->periodoAtivo) {
            $query->where('periodo_letivo_id', $this->periodoAtivo->periodo_letivo_origem_id);
        }

        return $query->with(['pessoa', 'turma.serie']);
    }

    protected function getRematriculaDoAluno(Matricula $matricula): ?RematriculaModel
    {
        if (! $this->periodoAtivo) {
            return null;
        }

        return RematriculaModel::where('periodo_rematricula_id', $this->periodoAtivo->id)
            ->where('matricula_origem_id', $matricula->id)
            ->first();
    }
}
