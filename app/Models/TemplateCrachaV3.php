<?php

namespace App\Models;

use App\Enums\TemplateCrachaEntidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateCrachaV3 extends Model
{
    use HasFactory;

    protected $table = 'template_crachas_v3';

    protected $fillable = ['nome', 'tipo_entidade', 'largura', 'altura', 'dados_json'];

    protected function casts(): array
    {
        return [
            'dados_json' => 'array',
            'tipo_entidade' => TemplateCrachaEntidade::class,
        ];
    }
}
