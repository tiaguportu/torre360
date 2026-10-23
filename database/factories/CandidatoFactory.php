<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusCandidato;
use App\Models\Candidato;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidato>
 */
class CandidatoFactory extends Factory
{
    protected $model = Candidato::class;

    public function definition(): array
    {
        return [
            'nome_completo' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telefone' => fake()->phoneNumber(),
            'data_nascimento' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'),
            'idade' => fake()->numberBetween(22, 50),
            'cargo_pretendido' => fake()->randomElement([
                'Professor de Matemática',
                'Professora de Língua Portuguesa',
                'Pedagoga / Coordenação',
                'Auxiliar de Sala',
                'Monitor Escolar',
                'Assistente Administrativo',
            ]),
            'escola_atual' => fake()->randomElement(['Colégio São Paulo', 'Escola Santa Maria', 'Instituto Dom Bosco', null]),
            'local_moradia' => fake()->city().' - '.fake()->stateAbbr(),
            'pretensao_salarial' => fake()->randomElement([3500.00, 4200.00, 5000.00, 6500.00]),
            'formacao_academica' => 'Licenciatura Plena em Pedagogia - Universidade Federal',
            'experiencias_profissionais' => '5 anos atuando na Educação Infantil e Fundamental I com metodologias ativas.',
            'habilidades' => 'Comunicação empática, Google Classroom, alfabetização, gestão de sala de aula.',
            'resumo_perfil' => 'Profissional dedicada com histórico sólido no ensino fundamental.',
            'status' => StatusCandidato::Novo,
            'impressoes_entrevistador' => 'Candidata com boa dicção e perfil colaborativo.',
            'audio_transcricao_resumo' => 'Entrevista realizada demonstrando boa postura e alinhamento com a cultura escolar.',
        ];
    }
}
