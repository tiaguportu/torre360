<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusRsvp;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\EventoConfirmacao;
use App\Models\EventoEscolar;
use App\Models\Matricula;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class EventosEscolares extends Page
{
    use HasAjudaAction;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Eventos e Atividades (RSVP)';

    protected static ?string $slug = 'eventos';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.portal.pages.eventos-escolares';

    public ?int $selectedEventoId = null;

    public ?int $selectedMatriculaId = null;

    public string $rsvpStatus = 'confirmado';

    public int $quantidadeAcompanhantes = 0;

    public bool $autorizado = false;

    public ?string $observacoes = null;

    public bool $showModal = false;

    public function getEventoSelecionadoProperty(): ?EventoEscolar
    {
        return $this->selectedEventoId ? EventoEscolar::find($this->selectedEventoId) : null;
    }

    public function getEventosProperty(): Collection
    {
        $turmaIds = $this->getTurmaIdsAcessiveis();

        return EventoEscolar::query()
            ->where('ativo', true)
            ->where(function (Builder $q) use ($turmaIds) {
                $q->where('publico_alvo', 'todos')
                    ->orWhereHas('turmas', fn (Builder $sub) => $sub->whereIn('turma.id', $turmaIds));
            })
            ->with(['turmas', 'confirmacoes.matricula.pessoa'])
            ->orderBy('data_inicio', 'asc')
            ->get();
    }

    public function getMatriculasAcessiveisProperty(): Collection
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Matricula::query()
            ->whereIn('pessoa_id', $idsAcessiveis)
            ->with(['pessoa', 'turma.serie'])
            ->get();
    }

    protected function getTurmaIdsAcessiveis(): array
    {
        return $this->matriculasAcessiveis->pluck('turma_id')->filter()->unique()->toArray();
    }

    public function getConfirmacaoPara(EventoEscolar $evento, Matricula $matricula): ?EventoConfirmacao
    {
        return $evento->confirmacoes->firstWhere('matricula_id', $matricula->id);
    }

    public function abrirModalRsvp(int $eventoId, int $matriculaId, string $status = 'confirmado'): void
    {
        $evento = EventoEscolar::findOrFail($eventoId);
        $matricula = $this->matriculasAcessiveis->firstWhere('id', $matriculaId);

        if (! $matricula) {
            Notification::make()->title('Acesso negado.')->danger()->send();

            return;
        }

        if ($evento->isEncerrado()) {
            Notification::make()->title('Este evento já foi encerrado.')->warning()->send();

            return;
        }

        if ($evento->isPrazoExpirado()) {
            Notification::make()->title('O prazo limite para confirmação (RSVP) já expirou.')->warning()->send();

            return;
        }

        $existente = $this->getConfirmacaoPara($evento, $matricula);

        $this->selectedEventoId = $eventoId;
        $this->selectedMatriculaId = $matriculaId;
        $this->rsvpStatus = $status;
        $this->quantidadeAcompanhantes = $existente?->quantidade_acompanhantes ?? 0;
        $this->autorizado = (bool) ($existente?->autorizado ?? false);
        $this->observacoes = $existente?->observacoes ?? null;
        $this->showModal = true;
    }

    public function salvarRsvp(): void
    {
        if (! $this->selectedEventoId || ! $this->selectedMatriculaId) {
            return;
        }

        $evento = EventoEscolar::findOrFail($this->selectedEventoId);
        $matricula = $this->matriculasAcessiveis->firstWhere('id', $this->selectedMatriculaId);

        if (! $matricula) {
            Notification::make()->title('Acesso negado.')->danger()->send();

            return;
        }

        if ($this->rsvpStatus === 'confirmado' && $evento->exige_autorizacao && ! $this->autorizado) {
            Notification::make()
                ->title('Autorização Obrigatória')
                ->body('Para confirmar a presença em passeios externos, é obrigatório assinar o termo de autorização.')
                ->danger()
                ->send();

            return;
        }

        // Verifica lotação máxima se for nova confirmação
        if ($this->rsvpStatus === 'confirmado' && $evento->limite_vagas !== null) {
            $vagas = $evento->vagas_restantes;
            $necessarias = 1 + $this->quantidadeAcompanhantes;
            $existente = $this->getConfirmacaoPara($evento, $matricula);
            $jaOcupadas = $existente && $existente->status === StatusRsvp::Confirmado ? (1 + $existente->quantidade_acompanhantes) : 0;

            if (($vagas + $jaOcupadas) < $necessarias) {
                Notification::make()
                    ->title('Capacidade Esgotada')
                    ->body('Desculpe, o evento não possui vagas suficientes para essa quantidade de participantes.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $responsavelId = auth()->user()->pessoa?->id ?: auth()->user()->pessoasAcessiveis()->first()?->id;

        EventoConfirmacao::updateOrCreate(
            [
                'evento_escolar_id' => $evento->id,
                'matricula_id' => $matricula->id,
            ],
            [
                'responsavel_id' => $responsavelId,
                'status' => $this->rsvpStatus,
                'quantidade_acompanhantes' => $this->rsvpStatus === 'confirmado' ? $this->quantidadeAcompanhantes : 0,
                'autorizado' => $this->rsvpStatus === 'confirmado' ? $this->autorizado : false,
                'data_resposta' => now(),
                'ip_resposta' => request()->ip(),
                'observacoes' => $this->observacoes,
            ]
        );

        $this->showModal = false;

        $msg = $this->rsvpStatus === 'confirmado'
            ? 'Presença e autorização confirmadas com sucesso!'
            : 'Sua resposta foi registrada. Agradecemos o aviso.';

        Notification::make()
            ->title('Resposta Registrada!')
            ->body($msg)
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Eventos e Atividades (RSVP)', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🎉', 'Eventos e Atividades', 'Confirme a presença do aluno nos eventos da escola.')
            ->secao('🎯 O que você pode fazer?', [
                ['📅', 'Ver eventos', 'Consulte os eventos abertos para as turmas dos seus alunos.'],
                ['🙋', 'Confirmar presença', 'Responda se o aluno vai participar (RSVP).'],
                ['✍️', 'Autorizações', 'Alguns eventos exigem autorização expressa do responsável.'],
            ])
            ->alerta('Há prazo para responder, e alguns eventos têm vagas limitadas. Depois do prazo ou do encerramento, não é possível alterar a resposta.');
    }
}
