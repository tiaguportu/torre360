<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaConhecimento extends Model
{
    protected $table = 'area_conhecimento';
    protected $guarded = [];

    public function disciplinas(): HasMany
    {
        return $this->hasMany(Disciplina::class, 'area_id');
    }
}
