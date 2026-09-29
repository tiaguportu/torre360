<?php

namespace Database\Seeders;

use App\Models\VideoTutorial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Popula a Central de Ajuda com os vídeos tutoriais já gravados.
 *
 * Não é chamado pelo DatabaseSeeder principal (roda isolado, sob demanda):
 * php artisan db:seed --class=VideoTutorialSeeder
 *
 * Reexecutável sem duplicar (updateOrCreate por chave_pagina).
 */
class VideoTutorialSeeder extends Seeder
{
    public function run(): void
    {
        $videos = [
            [
                'chave_pagina' => 'interessados-kanban',
                'titulo' => 'Kanban de Interessados',
                'descricao' => 'Como usar o Funil de Vendas (CRM) para acompanhar leads, arrastar cartões entre etapas e identificar contatos atrasados.',
                'categoria' => 'CRM',
                'ordem' => 1,
                'duracao_segundos' => 61,
                'source' => 'C:\\Users\\tiagu\\AppData\\Local\\Temp\\claude\\c--xampp-htdocs-torre360\\383eb60a-ceb6-4813-b9d1-fe8738a3efba\\scratchpad\\video_kanban\\kanban_interessados_demo.mp4',
            ],
            [
                'chave_pagina' => 'rematriculas-lista',
                'titulo' => 'Rematrícula Online',
                'descricao' => 'O fluxo completo de rematrícula: a confirmação da família pelo Portal e o acompanhamento pela secretaria.',
                'categoria' => 'Secretaria',
                'ordem' => 2,
                'duracao_segundos' => 68,
                'source' => 'C:\\Users\\tiagu\\AppData\\Local\\Temp\\claude\\c--xampp-htdocs-torre360\\383eb60a-ceb6-4813-b9d1-fe8738a3efba\\scratchpad\\video_rematricula\\rematricula_online_demo.mp4',
            ],
            [
                'chave_pagina' => 'cronograma-aula-lancar-frequencia',
                'titulo' => 'Lançamento de Frequência',
                'descricao' => 'Como abrir uma aula do Cronograma e lançar a chamada: conteúdo ministrado, dever de casa e presença dos alunos.',
                'categoria' => 'Acadêmico',
                'ordem' => 3,
                'duracao_segundos' => 70,
                'source' => 'C:\\Users\\tiagu\\AppData\\Local\\Temp\\claude\\c--xampp-htdocs-torre360\\383eb60a-ceb6-4813-b9d1-fe8738a3efba\\scratchpad\\video_frequencia\\frequencia_escolar_demo.mp4',
            ],
            [
                'chave_pagina' => 'avaliacoes-notas',
                'titulo' => 'Criar Avaliação e Lançar Notas',
                'descricao' => 'Como cadastrar uma nova avaliação e, em seguida, lançar as notas de toda a turma na Grade de Notas.',
                'categoria' => 'Avaliações',
                'ordem' => 4,
                'duracao_segundos' => 87,
                'source' => 'C:\\Users\\tiagu\\AppData\\Local\\Temp\\claude\\c--xampp-htdocs-torre360\\383eb60a-ceb6-4813-b9d1-fe8738a3efba\\scratchpad\\video_avaliacao\\avaliacao_e_notas_demo.mp4',
            ],
        ];

        Storage::disk('public')->makeDirectory('video-tutoriais');

        foreach ($videos as $v) {
            if (! file_exists($v['source'])) {
                $this->command?->warn("Arquivo não encontrado, pulando: {$v['titulo']} ({$v['source']})");

                continue;
            }

            $relativePath = 'video-tutoriais/'.Str::slug($v['titulo']).'.mp4';
            Storage::disk('public')->put($relativePath, file_get_contents($v['source']));

            VideoTutorial::updateOrCreate(
                ['chave_pagina' => $v['chave_pagina']],
                [
                    'titulo' => $v['titulo'],
                    'descricao' => $v['descricao'],
                    'categoria' => $v['categoria'],
                    'ordem' => $v['ordem'],
                    'duracao_segundos' => $v['duracao_segundos'],
                    'arquivo' => $relativePath,
                    'ativo' => true,
                ]
            );

            $this->command?->info("Vídeo criado/atualizado: {$v['titulo']}");
        }
    }
}
