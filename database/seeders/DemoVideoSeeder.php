<?php

namespace Database\Seeders;

use App\Models\Curso;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder exclusivo para gerar dados fictícios de demonstração
 * (gravação de vídeos explicativos). NÃO usar em produção.
 */
class DemoVideoSeeder extends Seeder
{
    public function run(): void
    {
        // Usuário admin de demonstração
        $admin = User::firstOrCreate(
            ['email' => 'demo@torre360.com.br'],
            [
                'name' => 'Ana Diretoria (Demo)',
                'password' => Hash::make('demo12345'),
                'email_verified_at' => now(),
                'activated_at' => now(),
            ]
        );
        $admin->syncRoles(['super_admin']);

        // Consultores de vendas fictícios
        $consultores = collect(['Marina Souza', 'Rafael Lima'])->map(function ($nome) {
            $consultor = User::firstOrCreate(
                ['email' => str_replace(' ', '.', strtolower($nome)).'@torre360.com.br'],
                [
                    'name' => $nome,
                    'password' => Hash::make('demo12345'),
                    'email_verified_at' => now(),
                    'activated_at' => now(),
                ]
            );
            $consultor->syncRoles(['secretaria']);

            return $consultor;
        });

        // Unidade / Curso / Série mínimos para os dependentes aparecerem no card
        $unidade = Unidade::firstOrCreate(['nome' => 'Torre 360 - Unidade Sede'], [
            'situacao_funcionamento' => '1',
        ]);

        $curso = Curso::firstOrCreate(
            ['unidade_id' => $unidade->id, 'nome_interno' => 'Ensino Fundamental I'],
            ['nome_externo' => 'Ensino Fundamental I', 'minutos_por_periodo' => 50]
        );

        $series = collect(['1º Ano', '2º Ano', '3º Ano', '4º Ano', '5º Ano'])->map(
            fn ($nome) => Serie::firstOrCreate(
                ['curso_id' => $curso->id, 'nome' => $nome],
                ['sistema_avaliacao' => 'Nota', 'emite_certificado' => true]
            )
        );

        $origens = OrigemInteressado::pluck('id')->all();
        $statuses = StatusInteressado::orderBy('ordem')->get();
        $tiposContato = TipoContatoInteressado::pluck('id')->all();

        $leads = [
            ['nome' => 'Camila Andrade', 'crianca' => 'Pedro Andrade', 'status' => 'Novo', 'temp' => 'quente'],
            ['nome' => 'Bruno Ferreira', 'crianca' => 'Sofia Ferreira', 'status' => 'Novo', 'temp' => 'morno'],
            ['nome' => 'Juliana Pires', 'crianca' => 'Miguel Pires', 'status' => 'Novo', 'temp' => 'frio'],
            ['nome' => 'Diego Martins', 'crianca' => 'Laura Martins', 'status' => 'Contato Realizado', 'temp' => 'quente'],
            ['nome' => 'Patrícia Nunes', 'crianca' => 'Davi Nunes', 'status' => 'Contato Realizado', 'temp' => 'morno'],
            ['nome' => 'Fernando Costa', 'crianca' => 'Alice Costa', 'status' => 'Visita Agendada', 'temp' => 'quente'],
            ['nome' => 'Renata Duarte', 'crianca' => 'Heitor Duarte', 'status' => 'Visita Agendada', 'temp' => 'quente'],
            ['nome' => 'Gustavo Rocha', 'crianca' => 'Isabela Rocha', 'status' => 'Em Negociação', 'temp' => 'quente'],
            ['nome' => 'Larissa Teixeira', 'crianca' => 'Bernardo Teixeira', 'status' => 'Em Negociação', 'temp' => 'morno'],
            ['nome' => 'Marcelo Vieira', 'crianca' => 'Valentina Vieira', 'status' => 'Matriculado', 'temp' => 'quente'],
            ['nome' => 'Aline Barbosa', 'crianca' => 'Lucas Barbosa', 'status' => 'Matriculado', 'temp' => 'quente'],
            ['nome' => 'Thiago Moreira', 'crianca' => 'Manuela Moreira', 'status' => 'Desistente', 'temp' => 'frio'],
        ];

        foreach ($leads as $i => $lead) {
            $pessoa = Pessoa::firstOrCreate(
                ['email' => 'lead'.($i + 1).'@exemplo.com'],
                [
                    'nome' => $lead['nome'],
                    'telefone' => '5581'.str_pad((string) random_int(900000000, 999999999), 9, '0', STR_PAD_LEFT),
                    'tipo_nacionalidade' => '1',
                ]
            );

            $status = $statuses->firstWhere('nome', $lead['status']);

            $interessado = Interessado::firstOrCreate(
                ['pessoa_id' => $pessoa->id],
                [
                    'usuario_id' => $consultores->random()->id,
                    'origem_interessado_id' => $origens[array_rand($origens)],
                    'status_interessado_id' => $status->id,
                    'data_proximo_contato' => now()->addDays(random_int(-2, 7)),
                    'observacoes' => 'Lead de demonstração gerado automaticamente para gravação de vídeo.',
                    'valor_estimado' => random_int(8, 25) * 100,
                    'temperatura' => $lead['temp'],
                    'lead_score' => random_int(20, 95),
                    'data_primeiro_contato' => now()->subDays(random_int(1, 20)),
                    'data_conversao' => $lead['status'] === 'Matriculado' ? now()->subDays(random_int(0, 5)) : null,
                ]
            );

            InteressadoDependente::firstOrCreate(
                ['interessado_id' => $interessado->id, 'nome_crianca' => $lead['crianca']],
                [
                    'serie_id' => $series->random()->id,
                    'vinculo' => 'Pai',
                    'data_nascimento' => now()->subYears(random_int(6, 10))->subDays(random_int(0, 300)),
                ]
            );

            HistoricoContato::firstOrCreate(
                ['interessado_id' => $interessado->id, 'data_contato' => now()->subDays(random_int(1, 15))],
                [
                    'usuario_id' => $consultores->random()->id,
                    'tipo_contato_interessado_id' => $tiposContato[array_rand($tiposContato)],
                    'relato' => 'Primeiro contato realizado, família demonstrou interesse na proposta pedagógica.',
                    'duracao_minutos' => random_int(3, 20),
                    'resultado' => 'Positivo',
                ]
            );
        }

        $this->command?->info('Dados de demonstração criados. Login: demo@torre360.com.br / demo12345');
    }
}
