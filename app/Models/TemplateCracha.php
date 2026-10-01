<?php

namespace App\Models;

use App\Enums\TemplateCrachaEntidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateCracha extends Model
{
    use HasFactory;

    protected $table = 'template_crachas';

    protected $fillable = ['nome', 'tipo_entidade', 'largura', 'altura', 'imagem_fundo', 'dados_layout'];

    protected function casts(): array
    {
        return [
            'dados_layout' => 'array',
            'tipo_entidade' => TemplateCrachaEntidade::class,
        ];
    }
}
