<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Iniciando limpeza profunda do banco de dados remoto...\n";

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    
    $tables = DB::select('SHOW TABLES');
    $dbName = config('database.connections.mysql.database');
    $property = "Tables_in_" . $dbName;

    foreach ($tables as $table) {
        $tableName = $table->$property;
        echo "Limpando tabela: $tableName\n";
        Schema::dropIfExists($tableName);
    }
    
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "Banco de dados limpo com sucesso!\n";
} catch (\Exception $e) {
    echo "Erro ao limpar banco: " . $e->getMessage() . "\n";
}
