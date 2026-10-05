<?php

namespace App\Models;

use App\Enums\StatusListaEspera;
use App\Notifications\ListaEspera\VagaDisponivelNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ListaEsperaMatricula extends Model
{
    use HasFactory;

    protected $table = 'lista_espera_matriculas';

    protected $fillable = [
        'turma_id',
        'periodo_letivo_id',
        'pessoa_id',
        'interessado_id',
        'interessado_dependente_id',
        'status',
        'observacoes',
        'notificado_em',
        'criado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusListaEspera::class,
            'notificado_em' => 'datetime',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class, 'periodo_letivo_id');
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class, 'interessado_id');
    }

    public function dependente(): BelongsTo
    {
        return $this->belongsTo(InteressadoDependente::class, 'interessado_dependente_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    /**
     * Usuários a notificar quando uma vaga abrir: o próprio aluno, seus
     * responsáveis e quem registrou a entrada na lista (mesmo padrão de
     * `Preceptoria::getNotificationRecipients()`).
     */
    public function getNotificationRecipients(): Collection
    {
        $pessoas = collect([$this->pessoa])
            ->merge($this->pessoa?->responsaveis ?? [])
            ->filter();

        $destinatarios = $pessoas->flatMap(fn (Pessoa $pessoa) => $pessoa->users);

        if ($this->criadoPor) {
            $destinatarios->push($this->criadoPor);
        }

        return $destinatarios->unique('id');
    }

    /**
     * Marca a entrada como notificada e envia o aviso de vaga disponível.
     */
    public function notificarVagaDisponivel(): void
    {
        $this->update([
            'status' => StatusListaEspera::Notificado,
            'notificado_em' => now(),
        ]);

        foreach ($this->getNotificationRecipients() as $user) {
            $user->notify(new VagaDisponivelNotification($this));
        }
    }
}
