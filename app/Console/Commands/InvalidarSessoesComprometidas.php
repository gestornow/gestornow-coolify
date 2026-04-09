<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InvalidarSessoesComprometidas extends Command
{
    protected $signature = 'seguranca:invalidar-sessoes';
    protected $description = 'Invalida todas as sessões, tokens e códigos de reset (usar após incidente de segurança)';

    public function handle(): int
    {
        $this->warn('=== INVALIDANDO SESSÕES COMPROMETIDAS ===');

        // 1) Invalidar sessões de usuários
        $usuarios = DB::table('usuarios')->count();
        DB::table('usuarios')->update([
            'session_token' => null,
            'remember_token' => null,
            'codigo_reset' => null,
        ]);
        $this->info("✅ {$usuarios} usuários tiveram sessões invalidadas.");

        // 2) Limpar tokens API (Sanctum)
        if (\Schema::hasTable('personal_access_tokens')) {
            $tokens = DB::table('personal_access_tokens')->count();
            DB::table('personal_access_tokens')->truncate();
            $this->info("✅ {$tokens} tokens API revogados.");
        } else {
            $this->line("ℹ️  Tabela personal_access_tokens não existe.");
        }

        // 3) Limpar sessões em arquivo (se usar session driver file)
        $sessionPath = storage_path('framework/sessions');
        if (is_dir($sessionPath)) {
            $files = glob($sessionPath . '/*');
            $count = 0;
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.gitignore') {
                    unlink($file);
                    $count++;
                }
            }
            $this->info("✅ {$count} arquivos de sessão removidos.");
        }

        $this->newLine();
        $this->info('🔒 Todas as sessões foram invalidadas. Usuários precisarão fazer login novamente.');

        return Command::SUCCESS;
    }
}
