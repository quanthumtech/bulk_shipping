<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployBuildCommand extends Command
{
    protected $signature = 'deploy:build';
    protected $description = 'Executa toda a pipeline de build e otimizações';

    public function handle()
    {
        $this->info('✅ Iniciando pipeline de deploy...');
        $this->info('Executanto migrates...');
        $this->call('migrate', ['--force' => true]);

        $this->info('Executando seeders para atualizar permissões e roles...');
        $this->call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

        $this->info('Executando limpeza de cache e publicando vite...');
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');
        $this->call('vendor:publish', [
            '--force' => true,
            '--tag' => 'livewire:assets'
        ]);
        $this->call('route:clear');

        $this->info('Rodando npm build...');
        exec('npm run build');

        $this->info('✅ Pipeline finalizada com sucesso!');
        return Command::SUCCESS;
    }
}
