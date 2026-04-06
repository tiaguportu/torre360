<?php

namespace Database\Seeders;

use App\Models\Contrato;
use App\Models\CorRaca;
use App\Models\CronogramaAula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\Perfil;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Serie;
use App\Models\Sexo;
use App\Models\SituacaoMatricula;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use Faker\Factory;
use Illuminate\Database\Seeder;

class GrandeTesteSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create('pt_BR');

        // 1. Dados Básicos
        $brasil = Pais::firstOrCreate(['nome' => 'Brasil'], ['sigla' => 'BR']);
        $sexoM = Sexo::firstOrCreate(['nome' => 'Masculino'], ['sigla' => 'M']);
        $sexoF = Sexo::firstOrCreate(['nome' => 'Feminino'], ['sigla' => 'F']);
        $corRaca = CorRaca::firstOrCreate(['nome' => 'Branca']);
        $situacaoAtiva = SituacaoMatricula::firstOrCreate(['nome' => 'Ativa']);
        $turnoManha = Turno::firstOrCreate(['nome' => 'Manhã']);
        $unidade = Unidade::firstOrCreate(['nome' => 'Unidade Central']);

        // 2. Perfis
        $perfilProfessor = Perfil::firstOrCreate(['nome' => 'Professor']);
        $perfilAluno = Perfil::firstOrCreate(['nome' => 'Aluno']);
        $perfilResponsavel = Perfil::firstOrCreate(['nome' => 'Responsável']);

        // 3. Período Letivo e Etapas
        $periodoLetivo = PeriodoLetivo::firstOrCreate(['nome' => '2026'], [
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-20',
        ]);

        $etapas = [
            ['nome' => '1º Bimestre', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-04-30'],
            ['nome' => '2º Bimestre', 'data_inicio' => '2026-05-01', 'data_fim' => '2026-07-15'],
            ['nome' => '3º Bimestre', 'data_inicio' => '2026-08-01', 'data_fim' => '2026-09-30'],
            ['nome' => '4º Bimestre', 'data_inicio' => '2026-10-01', 'data_fim' => '2026-12-20'],
        ];

        foreach ($etapas as $etapa) {
            EtapaAvaliativa::firstOrCreate(
                ['nome' => $etapa['nome'], 'periodo_letivo_id' => $periodoLetivo->id],
                $etapa
            );
        }

        // 4. Disciplinas (Mínimo 5)
        $disciplinasData = [
            'Matemática' => ['cor' => '#ff4444', 'conteudos' => ['Frações e Decimais', 'Introdução à Álgebra', 'Geometria Espacial', 'Probabilidade Básica']],
            'Português' => ['cor' => '#4444ff', 'conteudos' => ['Análise Sintática', 'Literatura Brasileira', 'Produção de Texto', 'Variação Linguística']],
            'História' => ['cor' => '#ff8800', 'conteudos' => ['Revolução Industrial', 'Brasil Colônia', 'Segunda Guerra Mundial', 'Roma Antiga']],
            'Geografia' => ['cor' => '#008800', 'conteudos' => ['Urbanização', 'Biomas Brasileiros', 'Cartografia Digital', 'Impactos Ambientais']],
            'Ciências' => ['cor' => '#8800ff', 'conteudos' => ['Sistema Solar', 'Células e Tecidos', 'Ecossistemas', 'Tabela Periódica']],
        ];

        $disciplinaModels = [];
        foreach ($disciplinasData as $nome => $data) {
            $disciplinaModels[] = Disciplina::updateOrCreate(['nome' => $nome], ['cor' => $data['cor']]);
        }

        // 5. Professores (Mínimo 5)
        echo "Criando Professores...\n";
        $professores = [];
        for ($i = 1; $i <= 5; $i++) {
            $professor = Pessoa::updateOrCreate(
                ['cpf' => "111.111.111-0$i"],
                [
                    'nome' => "Professor Teste $i",
                    'email' => "professor$i@teste.com",
                    'sexo_id' => $sexoM->id,
                    'nacionalidade_id' => $brasil->id,
                ]
            );
            $professor->perfis()->syncWithoutDetaching([$perfilProfessor->id]);
            $professores[] = $professor;
        }

        // 6. Turmas (Mínimo 5)
        $curso = Curso::updateOrCreate(['nome_interno' => 'Ensino Médio'], [
            'nome_externo' => 'Ensino Médio',
            'unidade_id' => $unidade->id,
            'cor' => '#7c3aed',
        ]);

        $serie = Serie::firstOrCreate(['nome' => '1º Ano Médio'], [
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Bimestral',
        ]);

        $turmas = [];
        $coresTurma = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'];
        for ($i = 1; $i <= 5; $i++) {
            $turmas[] = Turma::updateOrCreate(['nome' => "Turma $i - 1º Médio"], [
                'serie_id' => $serie->id,
                'turno_id' => $turnoManha->id,
                'periodo_letivo_id' => $periodoLetivo->id,
                'vagas_maximas' => 30,
                'cor' => $coresTurma[$i - 1] ?? '#10b981',
            ]);
        }

        // 7. Famílias (Responsáveis e Alunos)
        echo "Criando Famílias...\n";
        for ($i = 1; $i <= 10; $i++) {
            try {
                $responsavel = Pessoa::create([
                    'nome' => $faker->name(),
                    'email' => $faker->email(),
                    'cpf' => $faker->cpf(),
                    'sexo_id' => (rand(0, 1) == 0) ? $sexoF->id : $sexoM->id,
                    'nacionalidade_id' => $brasil->id,
                ]);
                $responsavel->perfis()->sync([$perfilResponsavel->id]);

                $numFilhos = rand(1, 2);
                for ($f = 1; $f <= $numFilhos; $f++) {
                    $aluno = Pessoa::create([
                        'nome' => $faker->name(),
                        'email' => $faker->email(),
                        'cpf' => $faker->cpf(),
                        'data_nascimento' => now()->subYears(rand(14, 17))->format('Y-m-d'),
                        'sexo_id' => (rand(0, 1) == 0) ? $sexoM->id : $sexoF->id,
                        'nacionalidade_id' => $brasil->id,
                    ]);
                    $aluno->perfis()->sync([$perfilAluno->id]);

                    $turmaId = $turmas[array_rand($turmas)]->id;
                    $matricula = Matricula::create([
                        'pessoa_id' => $aluno->id,
                        'turma_id' => $turmaId,
                        'situacao_matricula_id' => $situacaoAtiva->id,
                        'data_matricula' => now(),
                    ]);

                    $contrato = Contrato::create([
                        'matricula_id' => $matricula->id,
                        'valor_total' => rand(15000, 20000),
                        'data_aceite' => now(),
                    ]);

                    ResponsavelFinanceiro::create([
                        'pessoa_id' => $responsavel->id,
                        'contrato_id' => $contrato->id,
                        'percentual' => 100,
                    ]);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // 8. Cronograma de Aulas (Segunda a Sexta)
        echo "Criando Cronograma de Aulas (Segunda a Sexta)...\n";
        $hoje = now();

        foreach ($turmas as $turma) {
            // Para cada dia útil nos próximos 30 dias
            for ($d = 0; $d < 30; $d++) {
                $dataAula = $hoje->copy()->addDays($d);

                // Pula finais de semana
                if ($dataAula->isWeekend()) {
                    continue;
                }

                // Cria 3 aulas por dia para esta turma
                $horarios = [
                    ['ini' => '08:00', 'fim' => '08:50'],
                    ['ini' => '09:00', 'fim' => '09:50'],
                    ['ini' => '10:10', 'fim' => '11:00'],
                ];

                foreach ($horarios as $hora) {
                    $disciplina = $disciplinaModels[array_rand($disciplinaModels)];
                    $professor = $professores[array_rand($professores)];

                    // Conteúdo baseado na disciplina
                    $possiveisConteudos = $disciplinasData[$disciplina->nome]['conteudos'] ?? ['Aula Presencial', 'Exercícios'];
                    $conteudo = $possiveisConteudos[array_rand($possiveisConteudos)];

                    CronogramaAula::create([
                        'turma_id' => $turma->id,
                        'disciplina_id' => $disciplina->id,
                        'pessoa_id' => $professor->id,
                        'data' => $dataAula->format('Y-m-d'),
                        'hora_inicio' => $hora['ini'],
                        'hora_fim' => $hora['fim'],
                        'conteudo_ministrado' => $conteudo,
                    ]);
                }
            }
        }

        echo "Seeder concluído com sucesso e turbinado com Cronograma!\n";
    }
}
