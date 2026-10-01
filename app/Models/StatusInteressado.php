<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusInteressado extends Model
{
    use HasFactory;

    protected $table = 'status_interessado';

    protected $fillable = ['nome', 'cor', 'ordem', 'is_final', 'is_ganho'];

    protected function casts(): array
    {
        return [
            'is_final' => 'boolean',
            'is_ganho' => 'boolean',
        ];
    }

    public function interessados(): HasMany
    {
        return $this->hasMany(Interessado::class, 'status_interessado_id');
    }
}
