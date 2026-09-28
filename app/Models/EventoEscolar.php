<?php

namespace App\Models;

use App\Enums\TipoEventoEscolar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventoEscolar extends Model
{
    use HasFactory;

    protected $table = 'eventos_escolares';

    protected $fillable = [
        'uuid',
        'unidade_id',
        'titulo',
        'tipo',
        'descricao',
        'local',
        'data_inicio',
        'data_fim',
        'limite_vagas',
        'prazo_confirmacao',
        'exige_autorizacao',
        'termo_autorizacao',
        'valor_por_pessoa',
        'publico_alvo',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoEscolar::class,
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'prazo_confirmacao' => 'datetime',
            'exige_autorizacao' => 'boolean',
            'valor_por_pessoa' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $evento) {
            if (empty($evento->uuid)) {
                $evento->uuid = (string) Str::uuid();
            }
        });
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'evento_escolar_turmas', 'evento_escolar_id', 'turma_id')
            ->withTimestamps();
    }

    public function confirmacoes(): HasMany
    {
        return $this->hasMany(EventoConfirmacao::class, 'evento_escolar_id');
    }

    public function isEncerrado(): bool
    {
        $limite = $this->data_fim ?: $this->data_inicio;

        return $limite && $limite->isPast();
    }

    public function isPrazoExpirado(): bool
    {
        return $this->prazo_confirmacao && $this->prazo_confirmacao->isPast();
    }

    public function getTotalConfirmadosAttribute(): int
    {
        $alunosConfirmados = $this->confirmacoes()->where('status', 'confirmado')->count();
        $acompanhantes = (int) $this->confirmacoes()->where('status', 'confirmado')->sum('quantidade_acompanhantes');

        return $alunosConfirmados + $acompanhantes;
    }

    public function getVagasRestantesAttribute(): ?int
    {
        if ($this->limite_vagas === null) {
            return null;
        }

        return max(0, $this->limite_vagas - $this->total_confirmados);
    }
}
