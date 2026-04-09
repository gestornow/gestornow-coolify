<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Auth\Services\PasswordResetService;

class ClearExpiredResetCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:clear-expired-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove códigos de redefinição de senha expirados';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Limpando códigos de redefinição expirados...');
        
        PasswordResetService::limparCodigosExpirados();
        
        $this->info('Códigos expirados removidos com sucesso!');
        
        return Command::SUCCESS;
    }
}