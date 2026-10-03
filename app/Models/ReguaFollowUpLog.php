<?php

namespace App\Models;

use App\Enums\CanalReguaFollowUp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReguaFollowUpLog extends Model
{
    use HasFactory;

    protected $table = 'regua_follow_up_logs';

    protected $fillable = [
        'regua_follow_up_id',
        'interessado_id',
        'visita_interessado_id',
        'canal',
        'destinatario',
        'assunto_enviado',
        'mensagem_enviada',
        'status_envio',
        'erro',
        'data_envio',
    ];

    protected function casts(): array
    {
        return [
            'canal' => CanalReguaFollowUp::class,
            'data_envio' => 'date',
        ];
    }

    public function regua(): BelongsTo
    {
        return $this->belongsTo(ReguaFollowUp::class, 'regua_follow_up_id');
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function visita(): BelongsTo
    {
        return $this->belongsTo(VisitaInteressado::class, 'visita_interessado_id');
    }
}
