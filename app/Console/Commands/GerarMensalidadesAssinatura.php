<?php

namespace App\Console\Commands;

use App\Services\Billing\AssinaturaPlanoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GerarMensalidadesAssinatura extends Command
{
    protected $signature = 'billing:assinaturas-gerar-mensalidades';

    protected $description = 'Gera cobranças mensais pendentes das assinaturas de plano';

    public function __construct(
        private readonly AssinaturaPlanoService $assinaturaPlanoService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Iniciando geração de mensalidades de assinatura...');

        try {
            $resultado = $this->assinaturaPlanoService->gerarMensalidadesPendentes();

            $this->info('Mensalidades geradas com sucesso.');
            $this->line('Assinaturas analisadas: ' . (int) ($resultado['assinaturas_analisadas'] ?? 0));
            $this->line('Mensalidades geradas: ' . (int) ($resultado['mensalidades_geradas'] ?? 0));

            Log::info('Comando billing:assinaturas-gerar-mensalidades concluído', $resultado);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro ao gerar mensalidades: ' . $e->getMessage());

            Log::error('Erro no comando billing:assinaturas-gerar-mensalidades', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
