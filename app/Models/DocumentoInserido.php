<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentoInserido extends Model
{
    use LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tipo_documento_id', 'matricula_id', 'situacao_documento_inserido_id', 'observacoes', 'nome_arquivo_original'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('documento_inserido')
            ->setDescriptionForEvent(function (string $eventName) {
                $tipoNome = $this->tipoDocumento?->nome ?? 'Tipo não identificado';
                $alunoNome = $this->matricula?->pessoa?->nome ?? 'Matrícula não identificada';

                return match ($eventName) {
                    'created' => "O documento '{$tipoNome}' foi enviado para a matrícula de {$alunoNome}.",
                    'updated' => "O documento '{$tipoNome}' da matrícula de {$alunoNome} foi atualizado.",
                    'deleted' => "O documento '{$tipoNome}' da matrícula de {$alunoNome} foi excluído.",
                    default => "Documento '{$tipoNome}': evento {$eventName}",
                };
            });
    }

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
