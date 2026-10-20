<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CriterioAvaliacaoDocenteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterioAvaliacaoDocente extends Model
{
    /** @use HasFactory<CriterioAvaliacaoDocenteFactory> */
    use HasFactory;

    protected $table = 'criterios_avaliacao_docente';

    protected $fillable = ['instrumento_id', 'nome', 'descricao', 'peso', 'ordem'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
            'ordem' => 'integer',
        ];
    }

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(InstrumentoAvaliacaoDocente::class, 'instrumento_id');
    }
}
