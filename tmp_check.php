<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/Filament/Resources'));
foreach($files as $file) {
    if ($file->isDir()) continue;
    if (str_contains($file->getFilename(), 'RelationManager.php')) {
        $c = file_get_contents($file->getPathname());
        // Simple heuristic: if it has Select::make and is missing createOptionForm (globally in the file)
        if (str_contains($c, 'Select::make') && !str_contains($c, 'createOptionForm')) {
            echo $file->getPathname() . PHP_EOL;
        }
    }
}
