<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrigemInteressado extends Model
{
    use HasFactory;

    protected $table = 'origem_interessado';

    protected $guarded = [];

    public function interessados(): HasMany
    {
        return $this->hasMany(Interessado::class, 'origem_interessado_id');
    }
}
