<?php

$resourcesDir = 'app/Filament/Resources';
$it = new RecursiveDirectoryIterator($resourcesDir);
foreach (new RecursiveIteratorIterator($it) as $file) {
    if ($file->getExtension() === 'php' && str_contains($file->getFilename(), 'Resource')) {
        $content = file_get_contents($file->getPathname());
        
        // Use the EXACT union types that parent Filament Resource uses to avoid strict type mismatch in PHP 8.2+
        $content = str_replace('protected static string|BackedEnum|null $navigationGroup = ', 'protected static string|\UnitEnum|null $navigationGroup = ', $content);
        $content = str_replace('protected static string|BackedEnum|null $navigationIcon = ', 'protected static string|\Illuminate\Contracts\Support\Htmlable|null|\BackedEnum $navigationIcon = ', $content);
        
        file_put_contents($file->getPathname(), $content);
        echo "Fixed typing to absolute match: {$file->getFilename()}\n";
    }
}
