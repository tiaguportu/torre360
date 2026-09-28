<?php

namespace App\Models;

use App\Enums\StatusSolicitacaoDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SolicitacaoDocumento extends Model
{
    use HasFactory;

    protected $table = 'solicitacao_documentos';

    protected $guarded = [];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function templateDocumento(): BelongsTo
    {
        return $this->belongsTo(TemplateDocumento::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_user_id');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por_user_id');
    }

    public static function gerarProtocolo(): string
    {
        $ano = date('Y');
        $ultimoId = static::max('id') ?? 0;
        $numero = str_pad((string) ($ultimoId + 1), 6, '0', STR_PAD_LEFT);

        return "DOC-{$ano}-{$numero}";
    }

    public static function gerarCodigoVerificacao(): string
    {
        do {
            $p1 = strtoupper(Str::random(4));
            $p2 = strtoupper(Str::random(4));
            $p3 = strtoupper(Str::random(4));
            $codigo = "TR36-{$p1}-{$p2}-{$p3}";
        } while (static::where('codigo_verificacao', $codigo)->exists());

        return $codigo;
    }

    public function isValido(): bool
    {
        if ($this->status !== StatusSolicitacaoDocumento::Disponivel) {
            return false;
        }

        if ($this->data_validade && $this->data_validade->isPast()) {
            return false;
        }

        return true;
    }

    protected function casts(): array
    {
        return [
            'status' => StatusSolicitacaoDocumento::class,
            'data_solicitacao' => 'datetime',
            'data_emissao' => 'datetime',
            'data_validade' => 'date',
        ];
    }
}
