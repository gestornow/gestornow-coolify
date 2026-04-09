<?php

namespace App\Console\Commands;

use App\Services\Billing\AssinaturaPlanoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessarInadimplenciaAssinatura extends Command
{
    protected $signature = 'billing:assinaturas-processar-inadimplencia {--dias=5 : Dias de atraso para bloquear empresa}';

    protected $description = 'Bloqueia e desbloqueia empresas conforme inadimplência das mensalidades de assinatura';

    public function __construct(
        private readonly AssinaturaPlanoService $assinaturaPlanoService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $diasAtraso = (int) $this->option('dias');
        if ($diasAtraso <= 0) {
            $diasAtraso = 5;
        }

        $this->info('Processando inadimplência de assinaturas...');
        $this->line('Dias de atraso considerados: ' . $diasAtraso);

        try {
            $resultado = $this->assinaturaPlanoService->processarInadimplencia($diasAtraso);

            $this->info('Processamento finalizado com sucesso.');
            $this->line('Cancelamentos finalizados: ' . (int) ($resultado['cancelamentos_finalizados'] ?? 0));
            $this->line('Empresas bloqueadas: ' . (int) ($resultado['empresas_bloqueadas'] ?? 0));
            $this->line('Empresas desbloqueadas: ' . (int) ($resultado['empresas_desbloqueadas'] ?? 0));

            Log::info('Comando billing:assinaturas-processar-inadimplencia concluído', $resultado);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro ao processar inadimplência: ' . $e->getMessage());

            Log::error('Erro no comando billing:assinaturas-processar-inadimplencia', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
