<?php

function replaceInDir($dir, $search, $replace)
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            $newContent = str_replace($search, $replace, $content);
            if ($newContent !== $content) {
                file_put_contents($file->getPathname(), $newContent);
                echo 'Updated: '.$file->getPathname()."\n";
            }
        }
    }
}

// Etapa -> EtapaAvaliativa
replaceInDir(__DIR__.'/../app/Filament', 'App\Models\Etapa;', 'App\Models\EtapaAvaliativa;');
replaceInDir(__DIR__.'/../app/Filament', 'Etapa::class', 'EtapaAvaliativa::class');
replaceInDir(__DIR__.'/../app/Filament', 'DataAvaliacao::class', 'Avaliacao::class');
replaceInDir(__DIR__.'/../app/Filament', 'App\Models\DataAvaliacao;', 'App\Models\Avaliacao;');

// Note: Etapas table might be looking for relation `etapas`. In Turno/Turma we renamed it to `etapasAvaliativas`.
echo "Done.\n";
