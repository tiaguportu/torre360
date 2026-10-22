<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusListaMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Lista de material escolar de uma série num período letivo (Onda 59). Rascunho até a secretaria publicar; publicada, as
 * famílias dos alunos da série a veem no Portal.
 */
class ListaMaterialEscolar extends Model
{
    use LogsActivity;

    protected $table = 'listas_material_escolar';

    protected $fillable = ['serie_id', 'periodo_letivo_id', 'status', 'observacao', 'publicada_em', 'publicada_por_user_id', 'criada_por_user_id'];

    protected function casts(): array
    {
        return [
            'status' => StatusListaMaterial::class,
            'publicada_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lista_material')
            ->logOnly(['serie_id', 'periodo_letivo_id', 'status', 'publicada_em'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function publicadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicada_por_user_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemListaMaterial::class, 'lista_material_id')->orderBy('ordem')->orderBy('id');
    }

    public function estaPublicada(): bool
    {
        return $this->status === StatusListaMaterial::Publicada;
    }
}
