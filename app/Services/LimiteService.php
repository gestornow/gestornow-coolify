<?php

namespace App\Services;

use App\Domain\Auth\Models\Empresa;
use App\Models\PlanoContratado;
use App\Models\PlanoContratadoModulo;
use App\Models\PlanoModulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LimiteService
{
    /**
     * Mapeamento de entidades para id_modulo no banco
     * Ajuste conforme os IDs reais dos módulos no seu banco
     */
    protected const MODULO_CLIENTES = 'Clientes';
    protected const MODULO_PRODUTOS = 'Produtos';
    protected const MODULO_CONTRATOS = 'Modelos de Contrato';
    protected const MODULO_USUARIOS = 'Usuarios';
    protected const MODULO_BANCOS = 'Bancos';
    protected const MODULO_BOLETOS = 'Boletos';

    /**
     * Verifica se a empresa pode cadastrar mais clientes
     */
    public static function podecadastrarCliente(int $idEmpresa): array
    {
        return self::verificarLimite($idEmpresa, self::MODULO_CLIENTES, 'clientes', 'id_cliente');
    }

    /**
     * Verifica se a empresa pode cadastrar mais produtos
     */
    public static function podecadastrarProduto(int $idEmpresa): array
    {
        return self::verificarLimite($idEmpresa, self::MODULO_PRODUTOS, 'produtos', 'id_produto');
    }

    /**
     * Verifica se a empresa pode cadastrar mais modelos de contrato
     */
    public static function podecadastrarModeloContrato(int $idEmpresa): array
    {
        $tabelaModelos = self::resolverTabelaModelosContrato();

        if (!$tabelaModelos) {
            return self::resultadoSemLimite();
        }

        return self::verificarLimite($idEmpresa, self::MODULO_CONTRATOS, $tabelaModelos, 'id_modelo');
    }

    /**
     * Verifica se a empresa pode cadastrar mais usuários
     */
    public static function podecadastrarUsuario(int $idEmpresa): array
    {
        return self::verificarLimite($idEmpresa, self::MODULO_USUARIOS, 'usuarios', 'id_usuario');
    }

    /**
     * Verifica se a empresa pode marcar mais bancos como "gera boleto"
     */
    public static function podeMarcarBancoGeraBoleto(int $idEmpresa): array
    {
        $atual = DB::table('bancos')
            ->where('id_empresa', $idEmpresa)
            ->where('gera_boleto', 1)
            ->count();

        $modulosBoletos = self::getModulosBoletosEmpresa($idEmpresa);

        if ($modulosBoletos->isEmpty()) {
            return [
                'pode' => false,
                'limite' => 0,
                'atual' => $atual,
                'restante' => 0,
                'mensagem' => 'Seu plano não possui a aba Boletos. Faça upgrade para habilitar emissão de boleto.',
            ];
        }

        // Limite vazio/null/0 na aba Boletos = ilimitado.
        $limitesPositivos = $modulosBoletos
            ->pluck('limite')
            ->filter(function ($limiteModulo) {
                return !is_null($limiteModulo) && (int) $limiteModulo > 0;
            });

        if ($limitesPositivos->isEmpty()) {
            return [
                'pode' => true,
                'limite' => null,
                'atual' => $atual,
                'restante' => null,
                'mensagem' => null,
            ];
        }

        $limite = (int) $limitesPositivos->min();

        $pode = $atual < $limite;

        return [
            'pode' => $pode,
            'limite' => $limite,
            'atual' => $atual,
            'restante' => max(0, $limite - $atual),
            'mensagem' => $pode ? null : "Limite de {$limite} bancos com emissão de boleto atingido. Faça upgrade do seu plano para adicionar mais.",
        ];
    }

    /**
     * Verifica se a empresa possui a aba/módulo de Boletos no plano atual.
     */
    public static function possuiModuloBoletos(int $idEmpresa): bool
    {
        return self::getModulosBoletosEmpresa($idEmpresa)->isNotEmpty();
    }

    /**
     * Verifica limite genérico para uma entidade
     */
    protected static function verificarLimite(int $idEmpresa, string $nomeModulo, string $tabela, string $primaryKey): array
    {
        $limite = self::getLimiteModulo($idEmpresa, $nomeModulo);

        if (!Schema::hasTable($tabela)) {
            return self::resultadoSemLimite();
        }
        
        if ($limite === null) {
            // Sem limite definido = ilimitado
            return self::resultadoSemLimite();
        }

        // Contar registros atuais
        $atual = DB::table($tabela)
            ->where('id_empresa', $idEmpresa)
            ->count();

        $pode = $atual < $limite;

        $nomeAmigavel = self::getNomeAmigavel($nomeModulo);

        return [
            'pode' => $pode,
            'limite' => $limite,
            'atual' => $atual,
            'restante' => max(0, $limite - $atual),
            'mensagem' => $pode ? null : "Limite de {$limite} {$nomeAmigavel} atingido. Faça upgrade do seu plano para cadastrar mais.",
        ];
    }

    /**
     * Obtém o limite de um módulo específico para a empresa
     */
    protected static function getLimiteModulo(int $idEmpresa, string $nomeModulo): ?int
    {
        $termosBusca = self::construirTermosBuscaModulo($nomeModulo);

        // Buscar plano contratado ativo
        $planoContratado = PlanoContratado::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->first();

        if (!$planoContratado) {
            // Fallback para empresas em teste baseadas no id_plano_teste.
            $empresa = Empresa::find($idEmpresa);

            if (!$empresa || empty($empresa->id_plano_teste)) {
                // Sem plano = em teste sem limites ou bloqueado
                return null;
            }

            $modulosPlano = PlanoModulo::where('id_plano', (int) $empresa->id_plano_teste)
                ->where('ativo', 1)
                ->with(['modulo.moduloPai'])
                ->get();

            $modulosPlano = $modulosPlano->filter(function ($moduloPlano) use ($termosBusca) {
                return self::moduloCorrespondeTermos($moduloPlano, $termosBusca);
            });

            return self::extrairMenorLimitePositivo($modulosPlano);
        }

        // Buscar módulo específico
        $modulosContratados = PlanoContratadoModulo::where('id_plano_contratado', $planoContratado->id)
            ->where('ativo', 1)
            ->with(['modulo.moduloPai'])
            ->get();

        $modulosContratados = $modulosContratados->filter(function ($moduloContratado) use ($termosBusca) {
            return self::moduloCorrespondeTermos($moduloContratado, $termosBusca);
        });

        return self::extrairMenorLimitePositivo($modulosContratados);
    }

    protected static function construirTermosBuscaModulo(string $nomeModulo): array
    {
        $nomeNormalizado = trim(mb_strtolower($nomeModulo, 'UTF-8'));
        $nomeSemAcento = self::removerAcentos($nomeNormalizado);
        $termos = [$nomeNormalizado, $nomeSemAcento];

        $aliases = self::getAliasesModulo($nomeModulo);
        foreach ($aliases as $alias) {
            $aliasNormalizado = self::removerAcentos(trim(mb_strtolower($alias, 'UTF-8')));
            if ($aliasNormalizado !== '') {
                $termos[] = $aliasNormalizado;
            }
        }

        if (str_ends_with($nomeSemAcento, 's')) {
            $termos[] = substr($nomeSemAcento, 0, -1);
        }

        foreach (preg_split('/\s+/', $nomeSemAcento) as $token) {
            if (!empty($token) && strlen($token) >= 3) {
                $termos[] = $token;
            }
        }

        return array_values(array_unique(array_filter($termos)));
    }

    protected static function moduloCorrespondeTermos($planoModulo, array $termosBusca): bool
    {
        $modulo = $planoModulo->modulo ?? null;
        if (!$modulo) {
            return false;
        }

        $textos = [
            self::normalizarTextoModulo((string) ($modulo->nome ?? '')),
            self::normalizarTextoModulo((string) ($modulo->rota ?? '')),
            self::normalizarTextoModulo((string) ($modulo->moduloPai->nome ?? '')),
            self::normalizarTextoModulo((string) ($modulo->moduloPai->rota ?? '')),
        ];

        foreach ($termosBusca as $termo) {
            $termoNormalizado = self::normalizarTextoModulo((string) $termo);
            if ($termoNormalizado === '') {
                continue;
            }

            foreach ($textos as $texto) {
                if ($texto !== '' && str_contains($texto, $termoNormalizado)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected static function extrairMenorLimitePositivo($modulos): ?int
    {
        $limites = collect($modulos)
            ->pluck('limite')
            ->filter(function ($limite) {
                return !is_null($limite) && (int) $limite > 0;
            })
            ->map(function ($limite) {
                return (int) $limite;
            });

        if ($limites->isEmpty()) {
            return null;
        }

        return (int) $limites->min();
    }

    protected static function removerAcentos(string $valor): string
    {
        $mapa = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ];

        return strtr($valor, $mapa);
    }

    protected static function normalizarTextoModulo(string $valor): string
    {
        $valor = trim(mb_strtolower($valor, 'UTF-8'));
        return self::removerAcentos($valor);
    }

    protected static function getAliasesModulo(string $nomeModulo): array
    {
        return match ($nomeModulo) {
            self::MODULO_CLIENTES => ['cliente', 'clientes', 'clientes/create', 'clientes-index', 'clientes-create'],
            self::MODULO_PRODUTOS => ['produto', 'produtos', 'produtos/create', 'produtos-index', 'produtos-create'],
            self::MODULO_CONTRATOS => ['contrato', 'contratos', 'modelo', 'modelos', 'documentos', 'modelos-contrato'],
            self::MODULO_USUARIOS => ['usuario', 'usuarios', 'usuário', 'usuários'],
            self::MODULO_BANCOS => ['banco', 'bancos', 'financeiro/bancos'],
            self::MODULO_BOLETOS => ['boleto', 'boletos', 'financeiro/boletos'],
            default => [],
        };
    }

    /**
     * Busca os módulos de boletos da empresa no plano contratado ativo,
     * com fallback para id_plano_teste quando aplicável.
     */
    protected static function getModulosBoletosEmpresa(int $idEmpresa)
    {
        $planoContratado = PlanoContratado::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->first();

        if ($planoContratado) {
            return PlanoContratadoModulo::where('id_plano_contratado', $planoContratado->id)
                ->where('ativo', 1)
                ->whereHas('modulo', function ($query) {
                    $query->where(function ($moduloQuery) {
                        $moduloQuery->where('nome', 'LIKE', '%' . self::MODULO_BOLETOS . '%')
                            ->orWhereHas('moduloPai', function ($paiQuery) {
                                $paiQuery->where('nome', 'LIKE', '%' . self::MODULO_BOLETOS . '%');
                            });
                    });
                })
                ->get();
        }

        $empresa = Empresa::find($idEmpresa);

        if (!$empresa || empty($empresa->id_plano_teste)) {
            return collect();
        }

        return PlanoModulo::where('id_plano', (int) $empresa->id_plano_teste)
            ->where('ativo', 1)
            ->whereHas('modulo', function ($query) {
                $query->where(function ($moduloQuery) {
                    $moduloQuery->where('nome', 'LIKE', '%' . self::MODULO_BOLETOS . '%')
                        ->orWhereHas('moduloPai', function ($paiQuery) {
                            $paiQuery->where('nome', 'LIKE', '%' . self::MODULO_BOLETOS . '%');
                        });
                });
            })
            ->get();
    }

    protected static function resolverTabelaModelosContrato(): ?string
    {
        $candidatas = [
            'locacao_modelos_contrato',
            'modelos_contrato',
        ];

        foreach ($candidatas as $tabela) {
            if (Schema::hasTable($tabela)) {
                return $tabela;
            }
        }

        return null;
    }

    protected static function resultadoSemLimite(): array
    {
        return [
            'pode' => true,
            'limite' => null,
            'atual' => 0,
            'mensagem' => null,
        ];
    }

    /**
     * Retorna nome amigável para mensagens
     */
    protected static function getNomeAmigavel(string $nomeModulo): string
    {
        return match ($nomeModulo) {
            self::MODULO_CLIENTES => 'clientes',
            self::MODULO_PRODUTOS => 'produtos',
            self::MODULO_CONTRATOS => 'modelos de contrato',
            self::MODULO_USUARIOS => 'usuários',
            self::MODULO_BANCOS => 'bancos com boleto',
            default => 'registros',
        };
    }

    /**
     * Lança exceção se o limite foi atingido
     */
    public static function validarLimiteOuFalha(int $idEmpresa, string $tipo): void
    {
        $resultado = match ($tipo) {
            'cliente' => self::podecadastrarCliente($idEmpresa),
            'produto' => self::podecadastrarProduto($idEmpresa),
            'modelo_contrato' => self::podecadastrarModeloContrato($idEmpresa),
            'usuario' => self::podecadastrarUsuario($idEmpresa),
            'banco_boleto' => self::podeMarcarBancoGeraBoleto($idEmpresa),
            default => ['pode' => true, 'mensagem' => null],
        };

        if (!$resultado['pode']) {
            throw ValidationException::withMessages([
                'limite' => [$resultado['mensagem']],
            ]);
        }
    }

    /**
     * Retorna resumo de todos os limites da empresa
     */
    public static function resumoLimites(int $idEmpresa): array
    {
        return [
            'clientes' => self::podecadastrarCliente($idEmpresa),
            'produtos' => self::podecadastrarProduto($idEmpresa),
            'modelos_contrato' => self::podecadastrarModeloContrato($idEmpresa),
            'usuarios' => self::podecadastrarUsuario($idEmpresa),
            'bancos_boleto' => self::podeMarcarBancoGeraBoleto($idEmpresa),
        ];
    }

    /**
     * Verifica se a empresa tem plano ativo
     */
    public static function temPlanoAtivo(int $idEmpresa): bool
    {
        return PlanoContratado::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->exists();
    }

    /**
     * Retorna informações do plano ativo
     */
    public static function getPlanoAtivo(int $idEmpresa): ?PlanoContratado
    {
        return PlanoContratado::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->with('modulos.modulo')
            ->first();
    }
}
