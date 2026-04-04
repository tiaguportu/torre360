<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateToMysql extends Command
{
    protected $signature = 'db:migrate-to-mysql';
    protected $description = 'Migra todos os dados do SQLite local para o MySQL remoto.';

    public function handle()
    {
        $this->info('Iniciando migração assistida de SQLite para MySQL (Modo Resistente)...');

        // Forçar configuração da conexão de origem SQLite
        Config::set('database.connections.sqlite_source', [
            'driver' => 'sqlite',
            'database' => database_path('database.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $sqlite = DB::connection('sqlite_source');
        $mysql = DB::connection('mysql');
        
        $this->info('Limpando constraints no MySQL...');
        $mysql->statement('SET FOREIGN_KEY_CHECKS=0;');
        
        try {
            $this->info('Rodando migrations pendentes no MySQL...');
            $this->call('migrate', [
                '--database' => 'mysql',
                '--force' => true
            ]);
        } catch (\Exception $e) {
            $this->warn('Erro nas migrations, tentando prosseguir com a cópia de dados: ' . $e->getMessage());
        }

        // Listar tabelas do SQLite (exceto as de sistema e migrations já tratadas)
        $tables = $sqlite->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'");

        foreach ($tables as $table) {
            $tableName = $table->name;
            
            if (!Schema::connection('mysql')->hasTable($tableName)) {
                $this->warn("Tabela $tableName não encontrada no MySQL. Pulando dados...");
                continue;
            }

            $this->info("Migrando dados da tabela: $tableName");
            
            try {
                $count = $sqlite->table($tableName)->count();
                if ($count === 0) {
                    $this->line("Tabela $tableName está vazia no SQLite.");
                    continue;
                }

                $bar = $this->output->createProgressBar($count);
                $bar->start();

                // Pegamos a primeira coluna para o orderBy ou usamos 'id'
                $firstRow = (array)$sqlite->table($tableName)->first();
                $orderColumn = isset($firstRow['id']) ? 'id' : (count($firstRow) > 0 ? array_keys($firstRow)[0] : null);

                $query = $sqlite->table($tableName);
                if ($orderColumn) {
                    $query->orderBy($orderColumn);
                }

                $query->chunk(100, function($chunk) use ($mysql, $tableName, $bar) {
                    $data = $chunk->map(fn($row) => (array)$row)->toArray();
                    try {
                        // Usar insertOrIgnore para evitar duplicatas se o script for rodado novamente
                        $mysql->table($tableName)->insertOrIgnore($data);
                    } catch (\Exception $e) {
                        $this->error("Falha ao inserir bloco na tabela $tableName: " . $e->getMessage());
                    }
                    $bar->advance(count($data));
                });

                $bar->finish();
                $this->line("");
            } catch (\Exception $e) {
                $this->error("Erro ao processar tabela $tableName: " . $e->getMessage());
            }
        }

        // Sincronizar o estado das migrations
        $this->info('Sincronizando logs de migrations...');
        try {
            $sqlite->table('migrations')->get()->each(function($row) use ($mysql) {
                $mysql->table('migrations')->insertOrIgnore((array)$row);
            });
        } catch (\Exception $e) {}

        $mysql->statement('SET FOREIGN_KEY_CHECKS=1;');
        $this->info('Processo finalizado (algumas tabelas/blocos podem ter sido ignorados se houver erro de schema).');
    }
}
