<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoInserido extends Model
{
    protected $table = 'documento_inserido';

    protected $fillable = [
        'documento_obrigatorio_id',
        'matricula_id',
        'situacao_documento_inserido_id',
        'observacoes',
        'arquivo_path',
        'nome_arquivo_original',
        'hash_arquivo',
    ];

    public function documentoObrigatorio(): BelongsTo
    {
        return $this->belongsTo(DocumentoObrigatorio::class, 'documento_obrigatorio_id');
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
