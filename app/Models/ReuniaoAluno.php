<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um aluno na pauta do conselho de classe: a fotografia dos indicadores no momento em que o conselho foi aberto e a deliberação
 * da turma. A deliberação nunca altera nota, frequência nem situação da matrícula: ela só registra o que ficou decidido.
 */
class ReuniaoAluno extends Model
{
    protected $table = 'reuniao_alunos';

    protected $fillable = ['reuniao_id', 'matricula_id', 'ordem', 'indicadores', 'deliberacao', 'observacao', 'deliberado_por_user_id', 'deliberado_em'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'indicadores' => 'array',
            'deliberado_em' => 'datetime',
        ];
    }

    public function reuniao(): BelongsTo
    {
        return $this->belongsTo(Reuniao::class, 'reuniao_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function deliberadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deliberado_por_user_id');
    }

    public function encaminhamentos(): HasMany
    {
        return $this->hasMany(ReuniaoEncaminhamento::class, 'reuniao_aluno_id');
    }

    public function foiDeliberado(): bool
    {
        return filled($this->deliberacao);
    }

    public function rotuloDaDeliberacao(): ?string
    {
        return $this->foiDeliberado() ? (config('reunioes.deliberacoes')[$this->deliberacao] ?? $this->deliberacao) : null;
    }
}
