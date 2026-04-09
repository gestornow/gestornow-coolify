<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Auth\Repositories\EmpresaRepository;
use App\Models\PlanoContratado;
use App\Services\TesteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BloquearEmpresasTeste extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'empresas:bloquear-teste-expirado';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bloqueia empresas que estão em status "teste" com período expirado';

    protected $empresaRepository;

    public function __construct(EmpresaRepository $empresaRepository)
    {
        parent::__construct();
        $this->empresaRepository = $empresaRepository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando verificação de empresas em teste expirado...');

        // Buscar empresas com status 'teste' e data_fim_teste expirada
        // OU empresas sem data_fim_teste criadas há mais de DIAS_TESTE dias (fallback)
        $dataLimite = Carbon::now()->subDays(TesteService::DIAS_TESTE);

        $empresasExpiradas = Empresa::where('status', 'teste')
            ->where(function ($query) use ($dataLimite) {
                $query->where(function ($q) {
                    // Tem data_fim_teste e já passou
                    $q->whereNotNull('data_fim_teste')
                      ->where('data_fim_teste', '<', now());
                })
                ->orWhere(function ($q) use ($dataLimite) {
                                        // Não tem data_fim_teste e foi criada há mais de DIAS_TESTE dias
                    $q->whereNull('data_fim_teste')
                      ->where('created_at', '<=', $dataLimite);
                });
            })
            ->get();

        if ($empresasExpiradas->isEmpty()) {
            $this->info('Nenhuma empresa em teste expirado encontrada.');
            Log::info('Comando BloquearEmpresasTeste: Nenhuma empresa encontrada para bloqueio');
            return;
        }

        $contador = 0;
        foreach ($empresasExpiradas as $empresa) {
            DB::beginTransaction();
            try {
                // Atualizar status para 'teste bloqueado'
                $empresa->update([
                    'status' => 'teste bloqueado',
                    'data_bloqueio' => Carbon::now(),
                    'configuracoes' => array_merge(
                        $empresa->configuracoes ?? [],
                        [
                            'motivo_bloqueio' => 'Período de teste expirado',
                            'bloqueado_automaticamente' => true,
                            'data_bloqueio_automatico' => Carbon::now()->toDateTimeString()
                        ]
                    )
                ]);

                // Inativar todos os planos contratados ativos da empresa
                PlanoContratado::where('id_empresa', $empresa->id_empresa)
                    ->where('status', 'ativo')
                    ->update(['status' => 'inativo']);

                // Deslogar todos os usuários da empresa
                $this->deslogarUsuariosEmpresa($empresa);

                DB::commit();

                $contador++;
                $this->line("✓ Empresa bloqueada: {$empresa->nome_empresa} (ID: {$empresa->id_empresa})");

                // Log da ação
                Log::info("Empresa bloqueada automaticamente por teste expirado", [
                    'id_empresa' => $empresa->id_empresa,
                    'nome_empresa' => $empresa->nome_empresa,
                    'data_fim_teste' => $empresa->data_fim_teste,
                    'data_criacao' => $empresa->created_at,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("✗ Erro ao bloquear empresa {$empresa->nome_empresa} (ID: {$empresa->id_empresa}): {$e->getMessage()}");
                Log::error("Erro ao bloquear empresa automaticamente", [
                    'id_empresa' => $empresa->id_empresa,
                    'nome_empresa' => $empresa->nome_empresa,
                    'erro' => $e->getMessage()
                ]);
            }
        }

        $this->info("Processo concluído. {$contador} empresas bloqueadas de {$empresasExpiradas->count()} encontradas.");
        Log::info("Comando BloquearEmpresasTeste concluído", [
            'total_encontradas' => $empresasExpiradas->count(),
            'total_bloqueadas' => $contador
        ]);
    }

    /**
     * Deslogar todos os usuários ativos de uma empresa
     */
    private function deslogarUsuariosEmpresa(Empresa $empresa): void
    {
        try {
            // Buscar todos os usuários ativos da empresa com session_token
            $usuarios = $empresa->usuarios()
                ->where('status', 'ativo')
                ->whereNotNull('session_token')
                ->get();

            $usuariosDeslogados = 0;
            foreach ($usuarios as $usuario) {
                // Limpar session_token do usuário
                $usuario->update(['session_token' => null]);
                $usuariosDeslogados++;
            }

            if ($usuariosDeslogados > 0) {
                $this->line("  → {$usuariosDeslogados} usuários deslogados da empresa");
                Log::info("Usuários deslogados por bloqueio da empresa", [
                    'id_empresa' => $empresa->id_empresa,
                    'usuarios_deslogados' => $usuariosDeslogados
                ]);
            }

        } catch (\Exception $e) {
            $this->error("  ✗ Erro ao deslogar usuários da empresa {$empresa->id_empresa}: {$e->getMessage()}");
            Log::error("Erro ao deslogar usuários da empresa bloqueada", [
                'id_empresa' => $empresa->id_empresa,
                'erro' => $e->getMessage()
            ]);
        }
    }
}