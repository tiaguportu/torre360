<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoInserido extends Model
{
    protected $table = 'documento_inserido';

    protected $fillable = [
        'tipo_documento_id',
        'matricula_id',
        'situacao_documento_inserido_id',
        'observacoes',
        'arquivo_path',
        'nome_arquivo_original',
        'hash_arquivo',
    ];

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function situacao(): BelongsTo
    {
        return $this->belongsTo(SituacaoDocumentoInserido::class, 'situacao_documento_inserido_id');
    }
}
