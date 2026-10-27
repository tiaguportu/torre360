<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConviteReuniao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReuniaoParticipante extends Model
{
    public const PAPEL_ORGANIZADOR = 'organizador';

    public const PAPEL_PRESIDENTE = 'presidente';

    public const PAPEL_PARTICIPANTE = 'participante';

    protected $table = 'reuniao_participantes';

    protected $fillable = ['reuniao_id', 'user_id', 'papel', 'convite', 'respondido_em', 'presente'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'convite' => ConviteReuniao::class,
            'respondido_em' => 'datetime',
            'presente' => 'boolean',
        ];
    }

    public function reuniao(): BelongsTo
    {
        return $this->belongsTo(Reuniao::class, 'reuniao_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
