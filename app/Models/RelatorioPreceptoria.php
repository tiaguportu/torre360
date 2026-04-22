<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelatorioPreceptoria extends Model
{
    use HasFactory;

    protected $table = 'relatorio_preceptoria';

    protected $guarded = [];

    public function casts(): array
    {
        return [
            'publico' => 'boolean',
        ];
    }

    public function preceptoria(): BelongsTo
    {
        return $this->belongsTo(Preceptoria::class);
    }
}
