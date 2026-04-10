<?php

$files = file('tables_list.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($files as $file) {
    if (! file_exists($file)) {
        continue;
    }

    $content = file_get_contents($file);

    // Procura o último ']);' que provavelmente fecha o return $table->...
    // ou se terminar com actions, como ']);' no final do método configure.

    // Uma forma mais segura é procurar o último ';' antes do fechamento do método e inserir antes dele.
    // Mas no Filament, quase sempre o configure termina com return $table->...;

    if (strpos($content, '->stackedOnMobile()') !== false) {
        continue; // Já tem
    }

    // Tenta encontrar o último ']);'
    $lastSemicolonPos = strrpos($content, ';');
    if ($lastSemicolonPos !== false) {
        // Verifica se o que vem antes é um parêntese de fechamento
        $beforeSemicolon = substr($content, $lastSemicolonPos - 1, 1);
        if ($beforeSemicolon === ')') {
            $newContent = substr_replace($content, "\n            ->stackedOnMobile();", $lastSemicolonPos, 1);
            file_put_contents($file, $newContent);
            echo "Updated: $file\n";
        } else {
            echo "Skipped (semicolon not after parenthesis): $file\n";
        }
    }
}
