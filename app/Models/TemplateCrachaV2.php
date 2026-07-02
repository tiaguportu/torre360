<?php

namespace App\Models;

use App\Enums\TemplateCrachaEntidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateCrachaV2 extends Model
{
    use HasFactory;

    protected $table = 'template_crachas_v2';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tipo_entidade' => TemplateCrachaEntidade::class,
        ];
    }
}
