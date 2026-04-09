<?php

namespace App\Services;

use App\Domain\Auth\Models\Empresa;
use App\Domain\Auth\Models\Usuario;
use App\Domain\Auth\Repositories\EmpresaRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Models\Plano;
use App\Models\PlanoContratado;
use App\Models\PlanoContratadoModulo;
use App\Models\PlanoModulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TesteService
{
    protected EmpresaRepository $empresaRepository;
    protected UsuarioRepository $usuarioRepository;

    /**
     * Dias de duração do período de teste
     */
    public const DIAS_TESTE = 5;

    public function __construct(
        EmpresaRepository $empresaRepository,
        UsuarioRepository $usuarioRepository
    ) {
        $this->empresaRepository = $empresaRepository;
        $this->usuarioRepository = $usuarioRepository;
    }

    /**
     * Criar empresa em modo teste com plano selecionado
     */
    public function criarTeste(array $dadosEmpresa, array $dadosUsuario, int $idPlano): array
    {
        Log::info('=== CRIANDO CONTA DE TESTE ===', [
            'dados_empresa' => $dadosEmpresa,
            'id_plano' => $idPlano
        ]);

        // Buscar plano
        $plano = Plano::where('id_plano', $idPlano)->ativos()->first();
        
        if (!$plano) {
            throw ValidationException::withMessages([
                'plano' => ['Plano não encontrado ou inativo.']
            ]);
        }

        // Validar dados únicos
        $this->validarDadosUnicos($dadosEmpresa, $dadosUsuario);

        DB::beginTransaction();

        try {
            // 1. Criar empresa em modo teste
            $empresa = $this->criarEmpresaTeste($dadosEmpresa, $plano);
            
            // 2. Criar usuário admin
            $usuario = $this->criarUsuarioAdmin($empresa, $dadosUsuario);
            
            // 3. Criar plano contratado com módulos e limites do plano
            $planoContratado = $this->criarPlanoContratado($empresa, $plano);

            DB::commit();

            Log::info('=== TESTE CRIADO COM SUCESSO ===', [
                'id_empresa' => $empresa->id_empresa,
                'id_usuario' => $usuario->id_usuario,
                'id_plano_contratado' => $planoContratado->id,
                'data_fim_teste' => $empresa->data_fim_teste
            ]);

            return [
                'empresa' => $empresa,
                'usuario' => $usuario,
                'plano_contratado' => $planoContratado,
                'success' => true,
                'message' => 'Conta de teste criada com sucesso!'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== ERRO AO CRIAR TESTE ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Validar dados únicos
     */
    protected function validarDadosUnicos(array $dadosEmpresa, array $dadosUsuario): void
    {
        $errors = [];

        // CNPJ único
        if (!empty($dadosEmpresa['cnpj']) && $this->empresaRepository->cnpjExiste($dadosEmpresa['cnpj'])) {
            $errors['cnpj'] = ['CNPJ já está sendo utilizado.'];
        }

        // CPF único (se fornecido)
        if (!empty($dadosEmpresa['cpf']) && $this->empresaRepository->cpfExiste($dadosEmpresa['cpf'])) {
            $errors['cpf'] = ['CPF já está sendo utilizado.'];
        }

        // Email único
        if (!empty($dadosEmpresa['email']) && $this->empresaRepository->emailExiste($dadosEmpresa['email'])) {
            $errors['email'] = ['E-mail já está sendo utilizado.'];
        }

        // Login único
        if (!empty($dadosUsuario['login']) && $this->usuarioRepository->loginExiste($dadosUsuario['login'])) {
            $errors['login'] = ['Email de login já está sendo utilizado.'];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Criar empresa em modo teste
     */
    protected function criarEmpresaTeste(array $dados, Plano $plano): Empresa
    {
        $proximoCodigo = $this->empresaRepository->getProximoCodigo();

        $dadosEmpresa = array_merge([
            'status' => 'teste',
            'dados_cadastrais' => 'pendente',
            'filial' => 'Unica',
            'codigo' => $proximoCodigo,
            'c_produtos' => 'S',
            'c_clientes' => 'S',
            'c_fornecedores' => 'S',
        ], $dados);

        $empresa = $this->empresaRepository->create($dadosEmpresa);

        // Garantir os metadados do teste com base na entrada efetiva em teste.
        $baseTeste = $empresa->created_at ?: now();
        $empresa->update([
            'status' => 'teste',
            'data_fim_teste' => $baseTeste->copy()->addDays(self::DIAS_TESTE),
            'id_plano_teste' => $plano->id_plano,
        ]);

        return $empresa->fresh();
    }

    /**
     * Criar usuário admin da empresa
     */
    protected function criarUsuarioAdmin(Empresa $empresa, array $dados): Usuario
    {
        $dadosUsuario = array_merge([
            'id_empresa' => $empresa->id_empresa,
            'status' => 'ativo',
            'is_suporte' => false,
            'comissao' => 0.00,
            'tema' => 'light',
            'finalidade' => 'administrador',
            'senha' => Hash::make($dados['senha']),
        ], $dados);

        unset($dadosUsuario['senha_confirmation']);

        return $this->usuarioRepository->create($dadosUsuario);
    }

    /**
     * Criar plano contratado com módulos e limites
     */
    protected function criarPlanoContratado(Empresa $empresa, Plano $plano): PlanoContratado
    {
        // Criar plano contratado
        $planoContratado = PlanoContratado::create([
            'id_empresa' => $empresa->id_empresa,
            'nome' => $plano->nome . ' (Teste)',
            'valor' => 0, // Teste é gratuito
            'adesao' => 0,
            'data_contratacao' => now(),
            'status' => 'ativo',
            'observacoes' => 'Plano de teste criado em ' . now()->format('d/m/Y H:i'),
        ]);

        // Copiar módulos do plano para o plano contratado
        $modulosPlano = PlanoModulo::where('id_plano', $plano->id_plano)
            ->where('ativo', 1)
            ->with('modulo')
            ->get();

        foreach ($modulosPlano as $moduloPlano) {
            PlanoContratadoModulo::create([
                'id_plano_contratado' => $planoContratado->id,
                'id_modulo' => $moduloPlano->id_modulo,
                'limite' => $moduloPlano->limite,
                'ativo' => 1,
            ]);
        }

        return $planoContratado;
    }

    /**
     * Verificar se empresa está em período de teste válido
     */
    public static function empresaEmTeste(Empresa $empresa): bool
    {
        return $empresa->status === 'teste' 
            && $empresa->data_fim_teste 
            && $empresa->data_fim_teste->isFuture();
    }

    /**
     * Verificar se teste expirou
     */
    public static function testeExpirado(Empresa $empresa): bool
    {
        return $empresa->status === 'teste' 
            && $empresa->data_fim_teste 
            && $empresa->data_fim_teste->isPast();
    }

    /**
     * Dias restantes do teste
     */
    public static function diasRestantesTeste(Empresa $empresa): int
    {
        if (!$empresa->data_fim_teste) {
            return 0;
        }

        $segundos = now()->diffInSeconds($empresa->data_fim_teste, false);
        return max(0, (int) ceil($segundos / 86400));
    }

    /**
     * Bloquear teste expirado (para usar no cron)
     */
    public function bloquearTestesExpirados(): int
    {
        $empresasExpiradas = Empresa::where('status', 'teste')
            ->whereNotNull('data_fim_teste')
            ->where('data_fim_teste', '<', now())
            ->get();

        $count = 0;

        foreach ($empresasExpiradas as $empresa) {
            DB::beginTransaction();
            try {
                // Mudar status para bloqueado
                $empresa->update([
                    'status' => 'teste bloqueado',
                    'data_bloqueio' => now(),
                ]);

                // Inativar plano contratado
                PlanoContratado::where('id_empresa', $empresa->id_empresa)
                    ->where('status', 'ativo')
                    ->update(['status' => 'inativo']);

                DB::commit();
                $count++;

                Log::info('Teste bloqueado', ['id_empresa' => $empresa->id_empresa]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Erro ao bloquear teste', [
                    'id_empresa' => $empresa->id_empresa,
                    'erro' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }
}
