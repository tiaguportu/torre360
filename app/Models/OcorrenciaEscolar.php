<?php

namespace App\Models;

use App\Notifications\OcorrenciaRegistradaNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;

class OcorrenciaEscolar extends Model
{
    use HasFactory;

    protected $table = 'ocorrencia_escolars';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'notificar_responsaveis' => 'boolean',
            'notificacao_enviada_em' => 'datetime',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function tipoOcorrencia(): BelongsTo
    {
        return $this->belongsTo(TipoOcorrencia::class, 'tipo_ocorrencia_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function enviarNotificacaoResponsaveis(): bool
    {
        if (! $this->notificar_responsaveis) {
            return false;
        }

        $aluno = $this->matricula?->pessoa;
        if (! $aluno) {
            return false;
        }

        $responsaveisPessoas = $aluno->responsaveis;
        $usersParaNotificar = collect();

        foreach ($responsaveisPessoas as $resp) {
            foreach ($resp->users as $u) {
                $usersParaNotificar->push($u);
            }
        }

        $usersParaNotificar = $usersParaNotificar->unique('id');

        if ($usersParaNotificar->isNotEmpty()) {
            Notification::send($usersParaNotificar, new OcorrenciaRegistradaNotification($this));
            $this->updateQuietly(['notificacao_enviada_em' => now()]);

            return true;
        }

        return false;
    }

    protected static function booted(): void
    {
        static::created(function (OcorrenciaEscolar $ocorrencia) {
            if ($ocorrencia->notificar_responsaveis) {
                $ocorrencia->enviarNotificacaoResponsaveis();
            }
        });
    }
}
