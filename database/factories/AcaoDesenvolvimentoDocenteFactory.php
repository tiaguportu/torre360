<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusAcaoDesenvolvimento;
use App\Models\AcaoDesenvolvimentoDocente;
use App\Models\PlanoDesenvolvimentoDocente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcaoDesenvolvimentoDocente>
 */
class AcaoDesenvolvimentoDocenteFactory extends Factory
{
    protected $model = AcaoDesenvolvimentoDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plano_id' => PlanoDesenvolvimentoDocente::factory(),
            'criterio_id' => null,
            'descricao' => 'Participar de uma oficina de metodologias ativas e aplicar uma em sala.',
            'prazo' => now()->addDays(60)->toDateString(),
            'status' => StatusAcaoDesenvolvimento::Pendente,
            'concluida_em' => null,
            'resultado' => null,
            'ordem' => 1,
        ];
    }
}
