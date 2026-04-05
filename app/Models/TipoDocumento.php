<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TipoDocumento extends Model
{
    protected $table = 'tipo_documento';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'flag_obrigatorio' => 'boolean',
        ];
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'tipo_documento_curso');
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'tipo_documento_turma');
    }

    public function matriculas(): BelongsToMany
    {
        return $this->belongsToMany(Matricula::class, 'tipo_documento_matricula');
    }
}
