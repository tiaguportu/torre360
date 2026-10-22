<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoAlunoCenso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ajuste manual da situação do aluno no Censo Escolar, feito pela secretaria quando o cálculo automático não reflete o
 * caso (por exemplo, falecimento, que o sistema não registra). Um por matrícula. O log guarda a situação, não a
 * justificativa (que pode trazer dado sensível).
 */
class CensoSituacaoAluno extends Model
{
    use LogsActivity;

    protected $table = 'censo_situacoes_aluno';

    protected $fillable = ['matricula_id', 'situacao', 'justificativa', 'definido_por_user_id'];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoAlunoCenso::class,
            'justificativa' => 'encrypted',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('censo_escolar')
            ->logOnly(['matricula_id', 'situacao'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function definidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'definido_por_user_id');
    }
}
