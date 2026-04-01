<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    protected $table = 'configuracao';
    
    protected $fillable = [
        'campo',
        'valor',
        'grupo',
        'ordem',
    ];
}
