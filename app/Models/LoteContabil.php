<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormatoExportacaoContabil;
use App\Enums\RegimeContabil;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arquivo entregue à contabilidade (Onda 73): o período, o regime, o leiaute, os totais e a impressão digital do arquivo, que
 * fica guardado para baixar de novo sem refazer a conta (o movimento do período pode mudar depois).
 */
class LoteContabil extends Model
{
    protected $table = 'lotes_contabeis';

    protected $fillable = ['periodo_inicio', 'periodo_fim', 'regime', 'formato', 'lancamentos', 'total', 'arquivo_path', 'arquivo_hash', 'gerado_por_user_id'];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fim' => 'date',
            'regime' => RegimeContabil::class,
            'formato' => FormatoExportacaoContabil::class,
            'lancamentos' => 'integer',
            'total' => 'decimal:2',
        ];
    }

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por_user_id');
    }

    public function nomeDoArquivo(): string
    {
        return sprintf('contabilidade-%s-a-%s-%s.%s', $this->periodo_inicio->format('Ymd'), $this->periodo_fim->format('Ymd'), $this->regime->value, $this->formato->extensao());
    }
}
