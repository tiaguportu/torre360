<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModoAnulacaoQuestao;
use Database\Factories\AvaliacaoGabaritoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvaliacaoGabarito extends Model
{
    /** @use HasFactory<AvaliacaoGabaritoFactory> */
    use HasFactory;

    protected $table = 'avaliacao_gabaritos';

    protected $fillable = [
        'avaliacao_id',
        'total_questoes',
        'alternativas',
        'layout_versao',
        'layout',
        'anulacao_modo',
        'travado_em',
    ];

    protected function casts(): array
    {
        return [
            'total_questoes' => 'integer',
            'alternativas' => 'integer',
            'layout_versao' => 'integer',
            'layout' => 'array',
            'anulacao_modo' => ModoAnulacaoQuestao::class,
            'travado_em' => 'datetime',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class);
    }

    public function questoes(): HasMany
    {
        return $this->hasMany(AvaliacaoGabaritoQuestao::class, 'avaliacao_gabarito_id')->orderBy('numero');
    }

    public function estaTravado(): bool
    {
        return $this->travado_em !== null;
    }
}
