<?php

namespace App\Console\Commands;

use App\Domain\Auth\Models\Empresa;
use App\Services\Integracoes\Cobli\CobliGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CobliTestarConexao extends Command
{
    protected $signature = 'cobli:testar-conexao
        {--empresa= : ID de empresa especifica (opcional)}';

    protected $description = 'Testa a conexao com a API da Cobli usando a configuracao por empresa ou o fallback do .env';

    public function __construct(
        private readonly CobliGatewayService $cobliGateway
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $idEmpresa = $this->option('empresa');
        $empresaId = $idEmpresa !== null ? (int) $idEmpresa : null;

        if ($empresaId !== null) {
            if (!Schema::hasTable('empresa_integracoes_cobli')) {
                $this->error('Tabela empresa_integracoes_cobli nao encontrada. Execute as migrations primeiro.');
                return self::FAILURE;
            }

            if (!Empresa::query()->where('id_empresa', $empresaId)->exists()) {
                $this->error('Empresa nao encontrada: #' . $empresaId . '.');
                return self::FAILURE;
            }
        }

        if (!$this->cobliGateway->isConfigured($empresaId)) {
            $this->error($empresaId !== null
                ? 'Integracao Cobli nao configurada ou inativa para a empresa #' . $empresaId . '.'
                : 'Integracao Cobli nao configurada no .env.');

            return self::FAILURE;
        }

        try {
            $resultado = $this->cobliGateway->testConnection($empresaId);

            $this->info('Conexao com a Cobli validada com sucesso.');
            $this->line('Base URL: ' . ($resultado['base_url'] ?? '-'));
            $this->line('HTTP Status: ' . ($resultado['status'] ?? '-'));

            if ($empresaId !== null) {
                $this->line('Empresa: #' . $empresaId);
            }

            $chaves = $resultado['response_keys'] ?? [];
            if (!empty($chaves)) {
                $this->line('Chaves retornadas: ' . implode(', ', $chaves));
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Falha ao testar conexao com a Cobli: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}