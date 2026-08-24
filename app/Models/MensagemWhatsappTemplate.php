<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MensagemWhatsappTemplate extends Model
{
    use HasFactory;

    protected $table = 'mensagem_whatsapp_template';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
