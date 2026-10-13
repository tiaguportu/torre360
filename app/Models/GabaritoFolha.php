<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GabaritoFolhaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GabaritoFolha extends Model
{
    /** @use HasFactory<GabaritoFolhaFactory> */
    use HasFactory;

    protected $table = 'gabarito_folhas';

    protected $fillable = [
        'uuid',
        'avaliacao_id',
        'matricula_id',
        'layout_versao',
        'gerada_por_user_id',
        'corrigida_em',
    ];

    protected function casts(): array
    {
        return [
            'layout_versao' => 'integer',
            'corrigida_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GabaritoFolha $folha): void {
            if (empty($folha->uuid)) {
                $folha->uuid = (string) Str::uuid();
            }
        });
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function geradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerada_por_user_id');
    }

    public function correcoes(): HasMany
    {
        return $this->hasMany(OmrCorrecao::class, 'gabarito_folha_id');
    }
}
