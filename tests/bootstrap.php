<?php

$loader = require __DIR__.'/../vendor/autoload.php';
$base = dirname(__DIR__);

$_ENV['APP_BASE_PATH'] = $base;
$_SERVER['APP_BASE_PATH'] = $base;

// Atualiza o classMap para apontar para o worktree atual em vez do checkout original da junction
$oldMap = $loader->getClassMap();
$newMap = [];
$oldBasePrefix1 = 'C:\\xampp\\htdocs\\torre360\\vendor\\composer/../../';
$oldBasePrefix2 = 'C:/xampp/htdocs/torre360/vendor/composer/../../';
$oldBase = 'C:\\xampp\\htdocs\\torre360';

foreach ($oldMap as $class => $path) {
    if (str_starts_with($path, $oldBasePrefix1)) {
        $rel = substr($path, strlen($oldBasePrefix1));
        $newMap[$class] = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
    } elseif (str_starts_with($path, $oldBasePrefix2)) {
        $rel = substr($path, strlen($oldBasePrefix2));
        $newMap[$class] = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
    } elseif (str_starts_with($path, $oldBase) && ! str_starts_with($path, $oldBase.'\\vendor')) {
        $newMap[$class] = str_replace($oldBase, $base, $path);
    } else {
        $newMap[$class] = $path;
    }
}
$loader->addClassMap($newMap);

$loader->setPsr4('App\\', $base.'/app/');
$loader->setPsr4('Database\\Factories\\', $base.'/database/factories/');
$loader->setPsr4('Database\\Seeders\\', $base.'/database/seeders/');
$loader->setPsr4('Tests\\', $base.'/tests/');
