<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        $this->update(['vigencia_fim' => $dataAditivo]);

        return $this->funcionario->contratosTrabalho()->create([
            'vigencia_inicio' => date('Y-m-d', strtotime($dataAditivo.' +1 day')),
            'salario' => $novoSalario,
        ]);
    }
}
