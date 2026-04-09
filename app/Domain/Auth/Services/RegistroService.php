<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Empresa;
use App\Domain\Auth\Models\Usuario;
use App\Domain\Auth\Repositories\EmpresaRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Models\Plano;
use App\Models\PlanoModulo;
use App\Models\PlanoContratado;
use App\Models\PlanoContratadoModulo;
use App\Services\PermissaoService as PermissaoGrupoService;
use App\Services\TesteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class RegistroService
{
    protected $empresaRepository;
    protected $usuarioRepository;

    public function __construct(
        EmpresaRepository $empresaRepository,
        UsuarioRepository $usuarioRepository
    ) {
        Log::info('=== REGISTROSERVICE CONSTRUTOR EXECUTADO ===');
        $this->empresaRepository = $empresaRepository;
        $this->usuarioRepository = $usuarioRepository;
    }

    public function registrarEmpresaEUsuario(array $dadosEmpresa, array $dadosUsuario): array
    {
        Log::info('=== INICIANDO REGISTRO EMPRESA E USUARIO ===', [
            'dados_empresa' => $dadosEmpresa,
            'dados_usuario' => $dadosUsuario
        ]);

        // Validar dados únicos
        $this->validarDadosUnicos($dadosEmpresa, $dadosUsuario);
        
        Log::info('=== VALIDAÇÃO DE DADOS ÚNICOS PASSOU ===');

        DB::beginTransaction();

        try {
            // Criar empresa
            Log::info('=== CRIANDO EMPRESA ===');
            $empresa = $this->criarEmpresa($dadosEmpresa);
            Log::info('=== EMPRESA CRIADA ===', ['id_empresa' => $empresa->id_empresa]);

            // Criar usuário admin da empresa
            Log::info('=== CRIANDO USUÁRIO ADMIN ===');
            $usuario = $this->criarUsuarioAdmin($empresa, $dadosUsuario);
            Log::info('=== USUÁRIO CRIADO ===', ['id_usuario' => $usuario->id_usuario]);

            // Vincular perfil global administrador automaticamente ao usuário criador.
            $this->atribuirPerfilGlobalAdmin($empresa, $usuario);

            DB::commit();
            
            Log::info('=== CADASTRO REALIZADO COM SUCESSO ===', [
                'id_empresa' => $empresa->id_empresa,
                'id_usuario' => $usuario->id_usuario,
                'timestamp' => now()
            ]);

            return [
                'empresa' => $empresa,
                'usuario' => $usuario,
                'success' => true,
                'message' => 'Empresa e usuário criados com sucesso!'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== ERRO AO REGISTRAR EMPRESA E USUARIO ===', [
                'erro' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function validarDadosUnicos(array $dadosEmpresa, array $dadosUsuario): void
    {
        $errors = [];

        // Validar CNPJ único
        if (isset($dadosEmpresa['cnpj']) && $this->empresaRepository->cnpjExiste($dadosEmpresa['cnpj'])) {
            $errors['cnpj'] = ['CNPJ já está sendo utilizado por outra empresa.'];
        }

        // Validar CPF único (se fornecido)
        if (isset($dadosEmpresa['cpf']) && $dadosEmpresa['cpf'] && $this->empresaRepository->cpfExiste($dadosEmpresa['cpf'])) {
            $errors['cpf'] = ['CPF já está sendo utilizado por outra empresa.'];
        }

        // Validar email único
        if (isset($dadosEmpresa['email']) && $this->empresaRepository->emailExiste($dadosEmpresa['email'])) {
            $errors['email'] = ['E-mail já está sendo utilizado por outra empresa.'];
        }

        // Validar login único
        if (isset($dadosUsuario['login']) && $this->usuarioRepository->loginExiste($dadosUsuario['login'])) {
            $errors['login'] = ['Login já está sendo utilizado por outro usuário.'];
        }

        // Validar CPF do usuário único (se fornecido)
        if (isset($dadosUsuario['cpf']) && $dadosUsuario['cpf'] && $this->usuarioRepository->cpfExiste($dadosUsuario['cpf'])) {
            $errors['usuario_cpf'] = ['CPF do usuário já está sendo utilizado.'];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function criarEmpresa(array $dados): Empresa
    {
        // Gerar próximo código sequencial
        $proximoCodigo = $this->empresaRepository->getProximoCodigo();
        
        // Dados padrão para nova empresa
        $dadosEmpresa = array_merge([
            'status' => 'validacao',
            'dados_cadastrais' => 'pendente',
            'filial' => 'Unica',
            'codigo' => $proximoCodigo,
            'c_produtos' => 'S',
            'c_clientes' => 'S',
            'c_fornecedores' => 'S',
        ], $dados);

        return $this->empresaRepository->create($dadosEmpresa);
    }

    protected function criarUsuarioAdmin(Empresa $empresa, array $dados): Usuario
    {
        // Dados padrão para usuário admin
        $dadosUsuario = array_merge([
            'id_empresa' => $empresa->id_empresa,
            'status' => 'ativo',
            'is_suporte' => false,
            'comissao' => 0.00,
            'tema' => 'light',
            'finalidade' => 'administrador',
            // Não definir senha aqui - será definida quando validar email
        ], $dados);

        return $this->usuarioRepository->create($dadosUsuario);
    }

    protected function atribuirPerfilGlobalAdmin(Empresa $empresa, Usuario $usuario): void
    {
        $idPerfilGlobalAdmin = DB::table('perfil_global')
            ->where('ativo', 1)
            ->whereIn('codigo', ['administrador', 'admin'])
            ->value('id_perfil_global');

        if (!$idPerfilGlobalAdmin) {
            Log::warning('Perfil global administrador nao encontrado no registro da empresa.', [
                'id_empresa' => $empresa->id_empresa,
                'id_usuario' => $usuario->id_usuario,
            ]);
            return;
        }

        app(PermissaoGrupoService::class)->atribuirPerfilGlobal(
            (int) $usuario->id_usuario,
            (int) $empresa->id_empresa,
            (int) $idPerfilGlobalAdmin
        );
    }

    public function ativarEmpresa(int $idEmpresa): bool
    {
        $empresa = $this->empresaRepository->findById($idEmpresa);
        
        if (!$empresa) {
            throw new \Exception('Empresa não encontrada.');
        }

        return $this->empresaRepository->update($empresa, [
            'status' => 'teste',
            'dados_cadastrais' => 'completo'
        ]);
    }

    public function bloquearEmpresa(int $idEmpresa, string $motivo = null): bool
    {
        $empresa = $this->empresaRepository->findById($idEmpresa);
        
        if (!$empresa) {
            throw new \Exception('Empresa não encontrada.');
        }

        $dados = [
            'status' => 'bloqueado',
            'data_bloqueio' => now()
        ];

        if ($motivo) {
            $configuracoes = $empresa->configuracoes ?: [];
            $configuracoes['motivo_bloqueio'] = $motivo;
            $dados['configuracoes'] = $configuracoes;
        }

        // Atualizar empresa
        $resultado = $this->empresaRepository->update($empresa, $dados);

        if ($resultado) {
            // Deslogar todos os usuários da empresa
            $this->deslogarUsuariosEmpresa($empresa);
        }

        return $resultado;
    }

    public function cancelarEmpresa(int $idEmpresa, string $motivo = null): bool
    {
        $empresa = $this->empresaRepository->findById($idEmpresa);
        
        if (!$empresa) {
            throw new \Exception('Empresa não encontrada.');
        }

        $dados = [
            'status' => 'cancelado',
            'data_cancelamento' => now()
        ];

        if ($motivo) {
            $configuracoes = $empresa->configuracoes ?: [];
            $configuracoes['motivo_cancelamento'] = $motivo;
            $dados['configuracoes'] = $configuracoes;
        }

        return $this->empresaRepository->update($empresa, $dados);
    }

    public function emailUsuarioExiste(string $email): bool
    {
        return $this->usuarioRepository->emailExiste($email);
    }

    public function finalizarRegistro(int $idEmpresa, int $idUsuario, string $senha, ?int $idPlanoTeste = null): array
    {
        // Fluxo objetivo: apenas definir a senha (hash) e ativar empresa
        DB::beginTransaction();

        try {
            // Buscar empresa e usuário
            $empresa = $this->empresaRepository->findById($idEmpresa);
            $usuario = $this->usuarioRepository->findById($idUsuario);

            if (!$empresa || !$usuario) {
                throw new \Exception('Empresa ou usuário não encontrado.');
            }

            $planoTeste = $this->resolverPlanoTeste($idPlanoTeste);
            $dataFimTeste = now()->addDays(TesteService::DIAS_TESTE);

            // Atualizar status da empresa para teste com prazo e plano.
            $this->empresaRepository->update($empresa, [
                'status' => 'teste',
                'dados_cadastrais' => 'completo',
                'id_plano_teste' => $planoTeste->id_plano,
                'data_fim_teste' => $dataFimTeste,
            ]);

            $empresa = $empresa->fresh();

            // Garantir plano contratado com módulos e limites para aplicar regras de teste.
            $planoContratado = $this->garantirPlanoContratadoTeste($empresa, $planoTeste);
            $this->sincronizarModulosPlanoContratado($planoContratado, $planoTeste);

            // Hash explícito (replicando lógica do reset de senha)
            // Usamos Hash::make diretamente para eliminar qualquer risco de duplo hash
            $hash = Hash::make($senha);
            $usuario->senha = $hash;
            $usuario->save();

            DB::commit();

            return [
                'empresa' => $empresa->fresh(),
                'usuario' => $usuario->fresh(),
                'success' => true,
                'message' => 'Registro finalizado com sucesso!'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function resolverPlanoTeste(?int $idPlanoTeste): Plano
    {
        if ($idPlanoTeste) {
            $planoSelecionado = Plano::ativos()
                ->where('id_plano', $idPlanoTeste)
                ->whereNotIn('nome', ['Plano Gestor', 'Gestor'])
                ->first();

            if ($planoSelecionado) {
                return $planoSelecionado;
            }
        }

        $planoMaisCaro = Plano::ativos()
            ->whereNotIn('nome', ['Plano Gestor', 'Gestor'])
            ->orderBy('valor', 'desc')
            ->first();

        if (!$planoMaisCaro) {
            throw new \Exception('Nenhum plano ativo disponível para teste.');
        }

        return $planoMaisCaro;
    }

    private function garantirPlanoContratadoTeste(Empresa $empresa, Plano $plano): PlanoContratado
    {
        $planoContratado = PlanoContratado::where('id_empresa', $empresa->id_empresa)
            ->where('status', 'ativo')
            ->orderByDesc('id')
            ->first();

        if ($planoContratado) {
            return $planoContratado;
        }

        return PlanoContratado::create([
            'id_empresa' => $empresa->id_empresa,
            'nome' => $plano->nome . ' (Teste)',
            'valor' => 0,
            'adesao' => 0,
            'data_contratacao' => now(),
            'status' => 'ativo',
            'observacoes' => 'Plano de teste criado em ' . now()->format('d/m/Y H:i'),
        ]);
    }

    private function sincronizarModulosPlanoContratado(PlanoContratado $planoContratado, Plano $plano): void
    {
        $modulosPlano = PlanoModulo::where('id_plano', $plano->id_plano)
            ->where('ativo', 1)
            ->get(['id_modulo', 'limite']);

        foreach ($modulosPlano as $moduloPlano) {
            PlanoContratadoModulo::updateOrCreate(
                [
                    'id_plano_contratado' => $planoContratado->id,
                    'id_modulo' => $moduloPlano->id_modulo,
                ],
                [
                    'limite' => $moduloPlano->limite,
                    'ativo' => 1,
                ]
            );
        }
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
                Log::info("Usuários deslogados por bloqueio da empresa", [
                    'id_empresa' => $empresa->id_empresa,
                    'usuarios_deslogados' => $usuariosDeslogados
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Erro ao deslogar usuários da empresa bloqueada", [
                'id_empresa' => $empresa->id_empresa,
                'erro' => $e->getMessage()
            ]);
        }
    }
}