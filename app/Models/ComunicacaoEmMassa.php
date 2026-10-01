<?php

namespace App\Models;

use App\Enums\StatusComunicacaoEmMassa;
use App\Enums\TipoPublicoComunicacao;
use Database\Factories\ComunicacaoEmMassaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComunicacaoEmMassa extends Model
{
    /** @use HasFactory<ComunicacaoEmMassaFactory> */
    use HasFactory;

    protected $table = 'comunicacao_em_massa';

    protected $fillable = ['nome', 'tipo_publico', 'filtros', 'canal', 'assunto', 'corpo', 'status', 'total_destinatarios', 'total_enviados', 'total_falhas', 'enviado_por_user_id', 'enviado_em'];

    protected function casts(): array
    {
        return [
            'tipo_publico' => TipoPublicoComunicacao::class,
            'status' => StatusComunicacaoEmMassa::class,
            'filtros' => 'array',
            'enviado_em' => 'datetime',
        ];
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_user_id');
    }

    public function podeSerEnviada(): bool
    {
        return in_array($this->status, [StatusComunicacaoEmMassa::Rascunho, StatusComunicacaoEmMassa::Falhou], true);
    }
}
