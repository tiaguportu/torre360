<?php

$resourcesDir = 'app/Filament/Resources';
$it = new RecursiveDirectoryIterator($resourcesDir);
foreach (new RecursiveIteratorIterator($it) as $file) {
    if ($file->getExtension() === 'php' && str_contains($file->getFilename(), 'Resource')) {
        $content = file_get_contents($file->getPathname());

        // Final attempt at fixing typing for Filament v5 compatibility
        // Using the exact union types from the error messages
        $content = preg_replace('/protected static .* \$navigationGroup = /', 'protected static string|\UnitEnum|null $navigationGroup = ', $content);
        $content = preg_replace('/protected static .* \$navigationIcon = /', 'protected static string|\BackedEnum|null $navigationIcon = ', $content);
        $content = preg_replace('/protected static .* \$navigationSort = /', 'protected static ?int $navigationSort = ', $content);

        file_put_contents($file->getPathname(), $content);
        echo "Final fixed typing: {$file->getFilename()}\n";
    }
}
