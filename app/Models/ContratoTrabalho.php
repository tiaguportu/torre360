<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContratoTrabalho extends Model
{
    use HasFactory;

    protected $table = 'contratos_trabalho';

    protected $fillable = [
        'funcionario_id',
        'vigencia_inicio',
        'vigencia_fim',
        'salario',
        'motivo_encerramento',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'salario' => 'decimal:2',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    /**
     * Registra um aditivo: encerra a vigência atual nesta data e cria uma nova
     * linha com o novo salário, a partir do dia seguinte.
     */
    public function registrarAditivo(string $novoSalario, string $dataAditivo): self
    {
        return DB::transaction(function () use ($novoSalario, $dataAditivo): self {
            $funcionario = $this->funcionario()->lockForUpdate()->firstOrFail();

            if (! $funcionario->isAtivo()) {
                throw new \DomainException('Não é possível registrar aditivo para funcionário desligado.');
            }

            $dataAditivoCarbon = Carbon::parse($dataAditivo)->startOfDay();

            if ($this->vigencia_inicio && $dataAditivoCarbon->lessThan($this->vigencia_inicio->startOfDay())) {
                throw new \DomainException("A data do aditivo não pode ser anterior ao início da vigência atual ({$this->vigencia_inicio->format('d/m/Y')}).");
            }

            if ($funcionario->data_desligamento && $dataAditivoCarbon->greaterThan($funcionario->data_desligamento->startOfDay())) {
                throw new \DomainException("A data do aditivo não pode ser posterior à data de desligamento ({$funcionario->data_desligamento->format('d/m/Y')}).");
            }

            $this->update(['vigencia_fim' => $dataAditivoCarbon->toDateString()]);

            return $funcionario->contratosTrabalho()->create([
                'vigencia_inicio' => $dataAditivoCarbon->copy()->addDay()->toDateString(),
                'salario' => $novoSalario,
            ]);
        });
    }
}
