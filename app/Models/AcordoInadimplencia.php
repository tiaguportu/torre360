<?php

namespace App\Models;

use App\Enums\StatusAcordoInadimplencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AcordoInadimplencia extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'acordo_inadimplencias';

    protected $fillable = [
        'codigo',
        'contrato_id',
        'matricula_id',
        'responsavel_pessoa_id',
        'criado_por_user_id',
        'valor_original_total',
        'valor_multa_original',
        'valor_juros_original',
        'quantidade_faturas_originais',
        'faturas_originais_ids',
        'percentual_desconto_concedido',
        'valor_desconto',
        'valor_total_acordo',
        'valor_entrada',
        'data_vencimento_entrada',
        'quantidade_parcelas',
        'valor_parcela',
        'dia_vencimento_parcelas',
        'primeiro_vencimento',
        'token_publico',
        'status',
        'termo_confissao_texto',
        'aceito_em',
        'ip_aceite',
        'user_agent_aceite',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusAcordoInadimplencia::class,
            'valor_original_total' => 'decimal:2',
            'valor_multa_original' => 'decimal:2',
            'valor_juros_original' => 'decimal:2',
            'quantidade_faturas_originais' => 'integer',
            'faturas_originais_ids' => 'array',
            'percentual_desconto_concedido' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_total_acordo' => 'decimal:2',
            'valor_entrada' => 'decimal:2',
            'quantidade_parcelas' => 'integer',
            'valor_parcela' => 'decimal:2',
            'dia_vencimento_parcelas' => 'integer',
            'data_vencimento_entrada' => 'date',
            'primeiro_vencimento' => 'date',
            'aceito_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'codigo', 'status', 'valor_total_acordo', 'quantidade_parcelas',
                'aceito_em', 'valor_desconto',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function gerarCodigo(): string
    {
        $ano = now()->year;
        $ultimoId = (int) static::whereYear('created_at', $ano)->max('id') + 1;

        return sprintf('ACD-%d-%05d', $ano, $ultimoId);
    }

    public static function gerarTokenPublico(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('token_publico', $token)->exists());

        return $token;
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function responsavelPessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'responsavel_pessoa_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(AcordoParcela::class, 'acordo_inadimplencia_id')->orderBy('numero_parcela');
    }

    public function urlAceitePublica(): string
    {
        return route('acordo.publico.show', ['token' => $this->token_publico]);
    }

    public function totalPago(): float
    {
        return (float) $this->parcelas()->where('status', 'pago')->sum('valor_pago');
    }

    public function saldoDevedor(): float
    {
        return max(0, (float) $this->valor_total_acordo - $this->totalPago());
    }
}
