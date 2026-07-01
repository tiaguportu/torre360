<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateCracha extends Model
{
    use HasFactory;

    protected $table = 'template_crachas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dados_layout' => 'array',
        ];
    }
}
