<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusRematricula;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\Matricula;
use App\Models\PeriodoRematricula;
use App\Models\Rematricula as RematriculaModel;
use App\Models\Serie;
use App\Models\Turno;
use App\Services\RematriculaService;
use App\Support\HelpContent;
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
use Illuminate\Support\Facades\Log;
use UnitEnum;

class Rematricula extends Page implements HasTable
{
    use HasAjudaAction;
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
                            'Cancelada' => 'danger',
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

                        // Depois de efetivada (nova matrícula/contrato gerados) não há o que refazer aqui:
                        // a família segue pela assinatura em "Documentos e Contratos".
                        // Rematrícula cancelada pela escola também não reabre pelo Portal: a família fala com a secretaria.
                        return ! $rematricula
                            || ($rematricula->status !== StatusRematricula::Confirmada
                                && ! $rematricula->estaCancelada()
                                && ! $rematricula->nova_matricula_id);
                    })
                    ->modalHeading(fn (Matricula $record) => "Rematrícula: {$record->pessoa?->nome}")
                    ->modalDescription('Informe as preferências para o próximo ano letivo. A secretaria define a turma do seu filho e, em seguida, envia o contrato para assinatura.')
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

                            // Chamada direta ao servidor: efetivada ou cancelada não volta a ser editada pela família.
                            if ($rematricula->foiEfetivada() || $rematricula->estaCancelada()) {
                                Notification::make()
                                    ->title('Esta rematrícula não pode mais ser alterada')
                                    ->body('Fale com a secretaria da escola para qualquer ajuste.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $primeiraConfirmacao = $rematricula->status !== StatusRematricula::DadosConfirmados;

                            // A família só registra a intenção (série, turno e observações). A turma é
                            // definida pela secretaria ao efetivar, que também cria a matrícula e o contrato.
                            $rematricula->update([
                                'serie_destino_id' => $data['serie_destino_id'],
                                'turno_pretendido_id' => $data['turno_pretendido_id'],
                                'observacoes' => $data['observacoes'] ?? null,
                                'status' => StatusRematricula::DadosConfirmados,
                                'data_confirmacao' => now(),
                            ]);

                            // Avisa a equipe uma única vez (atualizar as preferências depois não repete o aviso).
                            if ($primeiraConfirmacao) {
                                try {
                                    $service->notificarEquipe($rematricula);
                                } catch (\Throwable $e) {
                                    Log::warning('Falha ao avisar a equipe sobre rematrícula aguardando turma.', ['rematricula_id' => $rematricula->id, 'erro' => $e->getMessage()]);
                                }
                            }

                            Notification::make()
                                ->title('Preferências registradas!')
                                ->body("Recebemos as preferências de rematrícula do(a) estudante {$record->pessoa?->nome}. A secretaria vai definir a turma e enviar o contrato para assinatura. Depois disso, o contrato ficará disponível em Documentos e Contratos para você assinar.")
                                ->success()
                                ->persistent()
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
            $query->doPeriodo($this->periodoAtivo->periodo_letivo_origem_id);
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

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Rematrícula Online', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🔄', 'Rematrícula Online', 'Garanta a vaga do aluno para o próximo período.')
            ->passos('🚀 Como fazer', [
                'Localize o estudante na lista e veja a situação da rematrícula.',
                'Clique em "Realizar Rematrícula".',
                'Escolha a série pretendida e o turno de preferência.',
                'Se quiser, registre observações e confirme.',
                'Aguarde a secretaria definir a turma: o contrato é enviado para assinatura em seguida (Documentos e Contratos).',
            ])
            ->dica('Depois de confirmar, a situação muda para "Dados Confirmados". A rematrícula só é concluída depois que a secretaria escolhe a turma e o contrato é assinado.');
    }
}
