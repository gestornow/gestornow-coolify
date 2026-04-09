<?php

namespace App\Services\Boleto;

use App\Models\Banco;
use App\Models\BancoBoleto;
use App\Models\BancoBoletoConfig;
use App\Models\Boleto;
use App\Models\ContasAReceber;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Cliente\Models\Cliente;
use App\Services\LimiteService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BoletoService
{
    /**
     * Retorna o service de integração correto para o banco.
     */
    public function getIntegracaoService(BancoBoletoConfig $config): object
    {
        $bancoBoleto = $config->bancoBoleto;
        
        if (!$bancoBoleto) {
            throw new Exception('Banco de boleto não encontrado.');
        }

        $nomeBanco = strtolower(trim((string) $bancoBoleto->nome));
        $codigoBanco = strtolower(trim((string) ($bancoBoleto->codigo_banco ?? '')));
        $identificador = trim($nomeBanco . ' ' . $codigoBanco);

        // Mapear bancos para seus services
        return match (true) {
            str_contains($identificador, 'inter') => new BancoInterService($config),
            str_contains($identificador, 'asaas') => new BancoAsaasService($config),
            str_contains($identificador, 'mercado pago')
                || str_contains($identificador, 'mercadopago')
                || str_contains($identificador, 'mercado_pago') => new BancoMercadoPagoService($config),
            str_contains($identificador, 'paghiper')
                || str_contains($identificador, 'pag hiper')
                || str_contains($identificador, 'pag_hiper') => new BancoPagHiperService($config),
            str_contains($identificador, 'cora') => new BancoCoraService($config),
            // Adicionar outros bancos aqui no futuro:
            // str_contains($identificador, 'sicredi') => new SicrediService($config),
            // str_contains($identificador, 'bradesco') => new BradescoService($config),
            default => throw new Exception("Integração não implementada para o banco: {$bancoBoleto->nome}"),
        };
    }

    /**
     * Gera um boleto para uma conta a receber.
     */
    public function gerarBoleto(ContasAReceber $conta, Banco $banco): Boleto
    {
        if (!LimiteService::possuiModuloBoletos((int) $conta->id_empresa)) {
            throw new Exception('Seu plano não possui a aba Boletos. Faça upgrade para habilitar emissão de boleto.');
        }

        // Validar se o banco pode gerar boletos
        if (!$banco->gera_boleto) {
            throw new Exception('Este banco não está configurado para gerar boletos.');
        }

        $config = $banco->boletoConfig;
        if (!$config || !$config->ativo) {
            throw new Exception('Configuração de boleto não encontrada ou inativa para este banco.');
        }

        if (!$config->isConfiguracaoCompleta()) {
            throw new Exception('Configuração de boleto incompleta. Verifique os dados do banco.');
        }

        // Buscar empresa
        $empresa = Empresa::find($conta->id_empresa);
        if (!$empresa) {
            throw new Exception('Empresa não encontrada.');
        }

        // Buscar cliente
        $cliente = Cliente::find($conta->id_clientes);
        if (!$cliente) {
            throw new Exception('Cliente não encontrado. É necessário ter um cliente vinculado à conta.');
        }

        // Obter service de integração
        $service = $this->getIntegracaoService($config);

        // Gerar boleto
        return $service->gerarBoleto($conta, $empresa, $cliente);
    }

    /**
     * Obtém o PDF de um boleto.
     */
    public function obterPdfBoleto(Boleto $boleto): string
    {
        $config = BancoBoletoConfig::where('id_bancos', $boleto->id_bancos)
            ->where('id_empresa', $boleto->id_empresa)
            ->first();

        if (!$config) {
            throw new Exception('Configuração de boleto não encontrada.');
        }

        $service = $this->getIntegracaoService($config);
        return $service->obterPdf($boleto);
    }

    /**
     * Consulta a situação de um boleto.
     */
    public function consultarBoleto(Boleto $boleto): array
    {
        $config = BancoBoletoConfig::where('id_bancos', $boleto->id_bancos)
            ->where('id_empresa', $boleto->id_empresa)
            ->first();

        if (!$config) {
            throw new Exception('Configuração de boleto não encontrada.');
        }

        $service = $this->getIntegracaoService($config);
        return $service->consultarBoleto($boleto);
    }

    /**
     * Retorna os bancos disponíveis para gerar boleto em uma empresa.
     */
    public function getBancosDisponiveis(int $idEmpresa): array
    {
        if (!LimiteService::possuiModuloBoletos($idEmpresa)) {
            return [];
        }

        $bancos = Banco::where('id_empresa', $idEmpresa)
            ->where('gera_boleto', true)
            ->whereHas('boletoConfig', function ($query) {
                $query->where('ativo', true);
            })
            ->with(['boletoConfig.bancoBoleto'])
            ->get();

        $resultado = [];

        foreach ($bancos as $banco) {
            try {
                $config = $banco->boletoConfig;

                if (!$config || !$config->ativo) {
                    continue;
                }

                $config = $this->vincularArquivosExistentesConfig($config, $idEmpresa, (int) $banco->id_bancos);

                if (!$config->bancoBoleto) {
                    continue;
                }

                if (!$config->isConfiguracaoCompleta()) {
                    continue;
                }

                $resultado[] = [
                    'id_bancos' => $banco->id_bancos,
                    'nome_banco' => $banco->nome_banco,
                    'boleto_config' => [
                        'id_config' => $config->id_config,
                        'id_banco_boleto' => $config->id_banco_boleto,
                        'banco_boleto' => [
                            'id_banco_boleto' => $config->bancoBoleto->id_banco_boleto,
                            'nome' => $config->bancoBoleto->nome,
                        ],
                    ],
                ];
            } catch (\Throwable $e) {
                Log::warning('Banco ignorado em bancosDisponiveis por erro de configuração.', [
                    'id_empresa' => $idEmpresa,
                    'id_bancos' => $banco->id_bancos ?? null,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $resultado;
    }

    /**
     * Vincula arquivos já existentes no storage quando a configuração ainda não aponta para eles.
     */
    private function vincularArquivosExistentesConfig(BancoBoletoConfig $config, int $idEmpresa, int $idBancos): BancoBoletoConfig
    {
        $atualizacoes = [];

        if (empty($config->arquivo_certificado)) {
            $arquivoCertificado = $this->buscarArquivoMaisRecente($idEmpresa, $idBancos, 'crt');
            if ($arquivoCertificado) {
                $atualizacoes['arquivo_certificado'] = $arquivoCertificado;
            }
        }

        if (empty($config->arquivo_chave)) {
            $arquivoChave = $this->buscarArquivoMaisRecente($idEmpresa, $idBancos, 'key');
            if ($arquivoChave) {
                $atualizacoes['arquivo_chave'] = $arquivoChave;
            }
        }

        if (!empty($atualizacoes)) {
            $config->update($atualizacoes);
            $config->refresh();
            $config->load('bancoBoleto');
        }

        return $config;
    }

    /**
     * Busca o arquivo mais recente por empresa/banco/extensão no diretório de certificados.
     */
    private function buscarArquivoMaisRecente(int $idEmpresa, int $idBancos, string $extensao): ?string
    {
        $basePath = 'boletos/certificados';
        if (!Storage::disk('local')->exists($basePath)) {
            return null;
        }

        $prefixo = sprintf('boleto_%d_%d_', $idEmpresa, $idBancos);
        $arquivos = Storage::disk('local')->files($basePath);

        $candidatos = collect($arquivos)
            ->filter(function (string $arquivo) use ($prefixo, $extensao) {
                $nomeArquivo = basename($arquivo);
                return Str::startsWith($nomeArquivo, $prefixo)
                    && Str::endsWith(Str::lower($nomeArquivo), '.' . Str::lower($extensao));
            })
            ->map(function (string $arquivo) {
                return [
                    'nome' => basename($arquivo),
                    'modificado_em' => Storage::disk('local')->lastModified($arquivo),
                ];
            })
            ->sortByDesc('modificado_em')
            ->values();

        return $candidatos->isNotEmpty() ? $candidatos->first()['nome'] : null;
    }

    /**
     * Retorna os boletos de uma conta a receber.
     */
    public function getBoletosContaReceber(int $idContaReceber): array
    {
        return Boleto::where('id_conta_receber', $idContaReceber)
            ->with(['banco', 'bancoBoleto'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Processa o webhook de um banco.
     */
    public function processarWebhook(string $banco, array $dados): void
    {
        match (strtolower($banco)) {
            'inter' => (new BancoInterService())->processarWebhook($dados),
            'asaas' => (new BancoAsaasService())->processarWebhook($dados),
            'mercado_pago', 'mercado-pago', 'mercadopago' => (new BancoMercadoPagoService())->processarWebhook($dados),
            'paghiper', 'pag_hiper', 'pag-hiper' => (new BancoPagHiperService())->processarWebhook($dados),
            'cora' => (new BancoCoraService())->processarWebhook($dados),
            default => throw new Exception("Webhook não implementado para o banco: {$banco}"),
        };
    }

    /**
     * Altera o vencimento de um boleto.
     * Cancela o boleto antigo e gera um novo com a nova data.
     */
    public function alterarVencimentoBoleto(Boleto $boleto, string $novaDataVencimento, float $novoValor): Boleto
    {
        $config = BancoBoletoConfig::where('id_bancos', $boleto->id_bancos)
            ->where('id_empresa', $boleto->id_empresa)
            ->first();

        if (!$config) {
            throw new Exception('Configuração de boleto não encontrada.');
        }

        $service = $this->getIntegracaoService($config);

        // Verificar se o service implementa alteração de vencimento
        if (!method_exists($service, 'alterarVencimento')) {
            throw new Exception('Este banco não suporta alteração de vencimento de boleto.');
        }

        return $service->alterarVencimento($boleto, $novaDataVencimento, $novoValor);
    }
}
