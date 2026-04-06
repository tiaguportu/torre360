<?php

$resourcesDir = 'app/Filament/Resources';
$it = new RecursiveDirectoryIterator($resourcesDir);
foreach (new RecursiveIteratorIterator($it) as $file) {
    if ($file->getExtension() === 'php' && str_contains($file->getFilename(), 'Resource')) {
        $content = file_get_contents($file->getPathname());

        // Add exact types that match parent class in Filament Resources
        // We'll use string|BackedEnum|null for Group and string|BackedEnum|null for Icon
        $content = str_replace('protected static $navigationGroup = ', 'protected static string|BackedEnum|null $navigationGroup = ', $content);
        $content = str_replace('protected static $navigationSort = ', 'protected static ?int $navigationSort = ', $content);

        file_put_contents($file->getPathname(), $content);
        echo "Fixed typing to exact match: {$file->getFilename()}\n";
    }
}
