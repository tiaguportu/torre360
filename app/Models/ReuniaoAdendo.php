<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correção ou complemento de uma ata já fechada. A ata em si não muda; o adendo fica registrado com quem escreveu e quando.
 */
class ReuniaoAdendo extends Model
{
    protected $table = 'reuniao_adendos';

    protected $fillable = ['reuniao_id', 'texto', 'user_id'];

    public function reuniao(): BelongsTo
    {
        return $this->belongsTo(Reuniao::class, 'reuniao_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
