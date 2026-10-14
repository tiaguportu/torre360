<?php

namespace App\Models;

use App\Enums\TipoMaterialAula;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MaterialAula extends Model
{
    use HasFactory;

    protected $fillable = [
        'turma_id',
        'disciplina_id',
        'professor_id',
        'titulo',
        'descricao',
        'tipo',
        'arquivo_path',
        'url',
        'data_publicacao',
        'visivel',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMaterialAula::class,
            'data_publicacao' => 'date',
            'visivel' => 'boolean',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }

    /**
     * URL para acessar o material: link externo/vídeo já é a própria URL; apostila
     * resolve para o arquivo armazenado.
     */
    public function getUrlAcessoAttribute(): ?string
    {
        if ($this->tipo === TipoMaterialAula::Apostila) {
            return $this->arquivo_path ? Storage::url($this->arquivo_path) : null;
        }

        return $this->url;
    }
}
