<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoricoEscolar extends Model
{
    use HasFactory;

    protected $table = 'historico_escolars';

    protected $fillable = [
        'pessoa_id',
        'curso_id',
        'unidade_id',
        'codigo_autenticidade',
        'situacao',
        'data_conclusao',
        'data_emissao',
        'titulo_certificacao',
        'texto_certificacao',
        'observacoes',
        'emitido_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'data_conclusao' => 'date',
            'data_emissao' => 'date',
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por_user_id');
    }

    public function anos(): HasMany
    {
        return $this->hasMany(HistoricoEscolarAno::class, 'historico_escolar_id')->orderBy('ordem')->orderBy('ano_letivo');
    }

    /**
     * Gera um código de autenticidade único para validação pública e QR Code.
     */
    public static function gerarCodigoAutenticidade(): string
    {
        do {
            $codigo = 'HIST-'.now()->year.'-'.strtoupper(bin2hex(random_bytes(4)));
        } while (static::where('codigo_autenticidade', $codigo)->exists());

        return $codigo;
    }
}
