<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SituacaoDocumentoInserido extends Model
{
    protected $table = 'situacao_documento_inserido';

    protected $fillable = [
        'nome',
    ];
}
