<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormatoRelatorio;
use App\Enums\OrigemExecucaoRelatorio;
use App\Enums\StatusExecucaoRelatorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma rodada de um relatório salvo (Onda 72): quando, por quem ou por qual agendamento, quantas linhas, o arquivo (apagado ao
 * fim da retenção) e, se falhou, o motivo.
 */
class ExecucaoRelatorio extends Model
{
    protected $table = 'execucoes_relatorio';

    protected $fillable = ['relatorio_salvo_id', 'origem', 'status', 'formato', 'linhas', 'truncado', 'arquivo_path', 'arquivo_hash', 'erro', 'executado_por_user_id', 'expira_em'];

    protected function casts(): array
    {
        return [
            'origem' => OrigemExecucaoRelatorio::class,
            'status' => StatusExecucaoRelatorio::class,
            'formato' => FormatoRelatorio::class,
            'linhas' => 'integer',
            'truncado' => 'boolean',
            'expira_em' => 'date',
        ];
    }

    public function relatorio(): BelongsTo
    {
        return $this->belongsTo(RelatorioSalvo::class, 'relatorio_salvo_id');
    }

    public function executadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executado_por_user_id');
    }

    public function temArquivo(): bool
    {
        return $this->status === StatusExecucaoRelatorio::Sucesso && filled($this->arquivo_path);
    }
}
