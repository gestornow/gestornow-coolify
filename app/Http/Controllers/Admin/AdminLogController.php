<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Models\Empresa;
use App\Domain\Auth\Models\Usuario;
use App\Facades\Perm;
use App\Http\Controllers\Controller;
use App\Models\RegistroAtividade;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminLogController extends Controller
{
    private static bool $relationsBooted = false;
    private array $referenceCache = [];

    public function __construct()
    {
        $this->bootDynamicRelations();
    }

    public function index(Request $request)
    {
        $this->ensureAdminOrSupport($request);

        $usuarioLogado = $request->user();
        $isSuporte = (int) ($usuarioLogado->is_suporte ?? $usuarioLogado->isSuporte ?? 0) === 1;
        $idEmpresaSessao = (int) (session('id_empresa') ?? ($usuarioLogado->id_empresa ?? 0));

        $filtros = [
            'id_empresa' => $request->query('id_empresa'),
            'id_usuario' => $request->query('id_usuario'),
            'acao' => $request->query('acao'),
            'entidade_tipo' => $request->query('entidade_tipo'),
            'origem' => $request->query('origem'),
            'data_inicio' => $request->query('data_inicio'),
            'data_fim' => $request->query('data_fim'),
            'busca' => $request->query('busca'),
            'valor_min' => $request->query('valor_min'),
            'valor_max' => $request->query('valor_max'),
        ];

        $baseQuery = RegistroAtividade::query();

        if (!$isSuporte && $idEmpresaSessao > 0) {
            $baseQuery->where('id_empresa', $idEmpresaSessao);
        }

        $query = clone $baseQuery;
        $this->applyFilters($query, $filtros);

        $logs = $query
            ->with(['usuario', 'empresa'])
            ->orderByDesc('ocorrido_em')
            ->paginate(50)
            ->withQueryString();

        $logs->getCollection()->transform(function (RegistroAtividade $log) {
            $log->acao_label = $this->formatActionLabel($log->acao);
            $log->entidade_referencia = $this->resolveEntityReference($log);
            $log->entidade_tipo_label = $this->formatModuleLabel($log->entidade_tipo);

            return $log;
        });

        $queryTotais = clone $baseQuery;
        $this->applyFilters($queryTotais, $filtros);
        $totais = $this->buildTotals($queryTotais);

        $acoes = (clone $baseQuery)
            ->whereNotNull('acao')
            ->where('acao', '!=', '')
            ->distinct()
            ->orderBy('acao')
            ->pluck('acao')
            ->values()
            ->all();

        $acoesMapeadas = [];
        $acoesAgrupadas = [];
        foreach ($acoes as $acao) {
            $label = $this->formatActionLabel((string) $acao);
            $acoesMapeadas[$acao] = $label;

            if (!isset($acoesAgrupadas[$label])) {
                $acoesAgrupadas[$label] = [];
            }
            $acoesAgrupadas[$label][] = $acao;
        }

        $entidades = (clone $baseQuery)
            ->whereNotNull('entidade_tipo')
            ->where('entidade_tipo', '!=', '')
            ->distinct()
            ->orderBy('entidade_tipo')
            ->pluck('entidade_tipo')
            ->values();

        $entidadesMapeadas = [];
        foreach ($entidades as $entidade) {
            $entidadesMapeadas[$entidade] = $this->formatModuleLabel((string) $entidade);
        }

        $empresas = Empresa::query()
            ->when(!$isSuporte && $idEmpresaSessao > 0, function (Builder $q) use ($idEmpresaSessao) {
                $q->where('id_empresa', $idEmpresaSessao);
            })
            ->orderBy('nome_empresa')
            ->get(['id_empresa', 'nome_empresa']);

        $usuarios = Usuario::query()
            ->when(!$isSuporte && $idEmpresaSessao > 0, function (Builder $q) use ($idEmpresaSessao) {
                $q->where('id_empresa', $idEmpresaSessao);
            })
            ->orderBy('nome')
            ->get(['id_usuario', 'nome', 'login']);

        return view('admin.logs.index', [
            'logs' => $logs,
            'empresas' => $empresas,
            'usuarios' => $usuarios,
            'acoes' => $acoes,
            'acoesMapeadas' => $acoesMapeadas,
            'acoesAgrupadas' => $acoesAgrupadas,
            'entidades' => $entidades,
            'entidadesMapeadas' => $entidadesMapeadas,
            'filtros' => $filtros,
            'totais' => $totais,
        ]);
    }

    public function show(Request $request, $id)
    {
        $this->ensureAdminOrSupport($request);

        $usuarioLogado = $request->user();
        $isSuporte = (int) ($usuarioLogado->is_suporte ?? $usuarioLogado->isSuporte ?? 0) === 1;
        $idEmpresaSessao = (int) (session('id_empresa') ?? ($usuarioLogado->id_empresa ?? 0));

        $log = RegistroAtividade::query()
            ->with(['usuario', 'empresa'])
            ->where('id_registro', $id)
            ->when(!$isSuporte && $idEmpresaSessao > 0, function (Builder $q) use ($idEmpresaSessao) {
                $q->where('id_empresa', $idEmpresaSessao);
            })
            ->firstOrFail();

        $payload = [
            'id_registro' => $log->id_registro,
            'acao' => $log->acao,
            'acao_label' => $this->formatActionLabel($log->acao),
            'descricao' => $log->descricao,
            'entidade_tipo' => $log->entidade_tipo,
            'entidade_tipo_label' => $this->formatModuleLabel($log->entidade_tipo),
            'entidade_id' => $log->entidade_id,
            'entidade_label' => $log->entidade_label,
            'entidade_referencia' => $this->resolveEntityReference($log),
            'valor' => $log->valor,
            'origem' => $log->origem,
            'icone' => $log->icone,
            'cor' => $log->cor,
            'ip' => $log->ip,
            'nome_responsavel' => $log->nome_responsavel,
            'email_responsavel' => $log->email_responsavel,
            'ocorrido_em' => optional($log->ocorrido_em)->format('Y-m-d H:i:s'),
            'empresa' => $log->empresa ? [
                'id_empresa' => $log->empresa->id_empresa,
                'nome_empresa' => $log->empresa->nome_empresa,
            ] : null,
            'usuario' => $log->usuario ? [
                'id_usuario' => $log->usuario->id_usuario,
                'nome' => $log->usuario->nome,
                'login' => $log->usuario->login,
            ] : null,
            'contexto' => $this->formatPayload($log->contexto),
            'antes' => $this->formatPayload($log->antes),
            'depois' => $this->formatPayload($log->depois),
            'tags' => $this->formatTags($log->tags),
        ];

        return response()->json([
            'success' => true,
            'log' => $payload,
        ]);
    }

    private function applyFilters(Builder $query, array $filtros): void
    {
        $query->when(!empty($filtros['id_empresa']), function (Builder $q) use ($filtros) {
            $q->where('id_empresa', (int) $filtros['id_empresa']);
        });

        $query->when(!empty($filtros['id_usuario']), function (Builder $q) use ($filtros) {
            $q->where('id_usuario', (int) $filtros['id_usuario']);
        });

        $query->when(!empty($filtros['acao']), function (Builder $q) use ($filtros) {
            $acaoFiltro = (string) $filtros['acao'];

            if (str_starts_with($acaoFiltro, '__label__:')) {
                $label = substr($acaoFiltro, 10);
                $acoesCompativeis = (clone $q)
                    ->whereNotNull('acao')
                    ->where('acao', '!=', '')
                    ->distinct()
                    ->pluck('acao')
                    ->filter(function ($acao) use ($label) {
                        return $this->formatActionLabel((string) $acao) === $label;
                    })
                    ->values()
                    ->all();

                if (empty($acoesCompativeis)) {
                    $q->whereRaw('1 = 0');
                    return;
                }

                $q->whereIn('acao', $acoesCompativeis);
                return;
            }

            $q->where('acao', $acaoFiltro);
        });

        $query->when(!empty($filtros['entidade_tipo']), function (Builder $q) use ($filtros) {
            $q->where('entidade_tipo', $filtros['entidade_tipo']);
        });

        $query->when(!empty($filtros['origem']), function (Builder $q) use ($filtros) {
            $q->where('origem', $filtros['origem']);
        });

        $query->when(!empty($filtros['data_inicio']), function (Builder $q) use ($filtros) {
            $q->whereDate('ocorrido_em', '>=', $filtros['data_inicio']);
        });

        $query->when(!empty($filtros['data_fim']), function (Builder $q) use ($filtros) {
            $q->whereDate('ocorrido_em', '<=', $filtros['data_fim']);
        });

        $query->when(!empty($filtros['busca']), function (Builder $q) use ($filtros) {
            $q->where('descricao', 'like', '%' . trim((string) $filtros['busca']) . '%');
        });

        $query->when($filtros['valor_min'] !== null && $filtros['valor_min'] !== '', function (Builder $q) use ($filtros) {
            $q->where('valor', '>=', (float) $filtros['valor_min']);
        });

        $query->when($filtros['valor_max'] !== null && $filtros['valor_max'] !== '', function (Builder $q) use ($filtros) {
            $q->where('valor', '<=', (float) $filtros['valor_max']);
        });
    }

    private function buildTotals(Builder $query): array
    {
        $base = clone $query;
        $inicioHoje = Carbon::now()->startOfDay();
        $inicioSemana = Carbon::now()->startOfWeek();
        $inicioMes = Carbon::now()->startOfMonth();

        return [
            'total' => (clone $base)->count(),
            'hoje' => (clone $base)->where('ocorrido_em', '>=', $inicioHoje)->count(),
            'semana' => (clone $base)->where('ocorrido_em', '>=', $inicioSemana)->count(),
            'mes' => (clone $base)->where('ocorrido_em', '>=', $inicioMes)->count(),
        ];
    }

    private function formatPayload($payload): array
    {
        if (empty($payload) || !is_array($payload)) {
            return [];
        }

        $saida = [];
        foreach ($payload as $chave => $valor) {
            $chaveTexto = (string) $chave;
            $saida[$this->formatFieldLabel($chaveTexto)] = $this->formatValueByKey($chaveTexto, $valor);
        }

        return $saida;
    }

    private function formatValueByKey(string $chave, $valor)
    {
        if (is_array($valor)) {
            $resultado = [];
            foreach ($valor as $subChave => $subValor) {
                $subChaveTexto = (string) $subChave;
                $resultado[$this->formatFieldLabel($subChaveTexto)] = $this->formatValueByKey($subChaveTexto, $subValor);
            }
            return $resultado;
        }

        if (is_bool($valor)) {
            return $valor ? 'Sim' : 'Nao';
        }

        if ($valor === null || $valor === '') {
            return '-';
        }

        $chaveNormalizada = mb_strtolower($chave);

        if ($this->isIdKey($chaveNormalizada) && is_scalar($valor)) {
            return $this->resolveReadableIdValue($chaveNormalizada, (string) $valor);
        }

        if (str_contains($chaveNormalizada, 'valor')
            || str_contains($chaveNormalizada, 'preco')
            || str_contains($chaveNormalizada, 'total')
            || str_contains($chaveNormalizada, 'saldo')) {
            if (is_numeric($valor)) {
                return 'R$ ' . number_format((float) $valor, 2, ',', '.');
            }
        }

        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            try {
                return Carbon::parse($valor)->format('d/m/Y H:i');
            } catch (\Throwable $e) {
                return $valor;
            }
        }

        return $valor;
    }

    private function formatFieldLabel(string $chave): string
    {
        $normalizada = mb_strtolower(trim($chave));

        $mapa = [
            'id_fornecedores' => 'Fornecedor',
            'id_fornecedor' => 'Fornecedor',
            'id_categoria_contas' => 'Categoria financeira',
            'id_bancos' => 'Banco',
            'id_banco' => 'Banco',
            'id_forma_pagamento' => 'Forma de pagamento',
            'id_usuario' => 'Usuario',
            'id_empresa' => 'Empresa',
            'id_cliente' => 'Cliente',
            'id_produto' => 'Produto',
            'id_patrimonio' => 'Patrimonio',
            'id_locacao' => 'Locacao',
            'id_conta_pagar' => 'Conta a pagar',
            'id_conta_receber' => 'Conta a receber',
        ];

        if (isset($mapa[$normalizada])) {
            return $mapa[$normalizada];
        }

        $texto = str_replace(['.', '-', '_'], ' ', $normalizada);
        if (str_starts_with($texto, 'id ')) {
            $texto = substr($texto, 3);
        }

        return ucfirst(trim($texto));
    }

    private function isIdKey(string $chave): bool
    {
        return $chave === 'id' || str_starts_with($chave, 'id_') || str_contains($chave, '_id');
    }

    private function resolveReadableIdValue(string $chave, string $valor): string
    {
        $id = trim($valor);
        if ($id === '' || !ctype_digit($id)) {
            return $valor;
        }

        $cacheKey = $chave . ':' . $id;
        if (array_key_exists($cacheKey, $this->referenceCache)) {
            return $this->referenceCache[$cacheKey];
        }

        $referencia = $this->findReferenceByIdKey($chave, (int) $id);
        if ($referencia !== null && $referencia !== '') {
            $saida = $referencia . ' (#' . $id . ')';
            $this->referenceCache[$cacheKey] = $saida;
            return $saida;
        }

        $fallback = 'Registro #' . $id;
        $this->referenceCache[$cacheKey] = $fallback;

        return $fallback;
    }

    private function findReferenceByIdKey(string $chave, int $id): ?string
    {
        $mapas = [
            'id_fornecedores' => [
                ['fornecedores', 'id_fornecedor', ['nome', 'nome_fornecedor', 'razao_social']]
            ],
            'id_fornecedor' => [
                ['fornecedores', 'id_fornecedor', ['nome', 'nome_fornecedor', 'razao_social']]
            ],
            'id_categoria_contas' => [
                ['categorias_contas', 'id_categoria_contas', ['nome', 'descricao', 'titulo']],
                ['categorias', 'id_categoria', ['nome', 'descricao', 'titulo']],
            ],
            'id_bancos' => [
                ['bancos', 'id_bancos', ['nome', 'nome_banco', 'descricao']],
            ],
            'id_banco' => [
                ['bancos', 'id_bancos', ['nome', 'nome_banco', 'descricao']],
            ],
            'id_forma_pagamento' => [
                ['formas_pagamento', 'id_forma_pagamento', ['nome', 'descricao', 'tipo']],
            ],
            'id_usuario' => [
                ['usuarios', 'id_usuario', ['nome', 'login', 'email']],
            ],
            'id_empresa' => [
                ['empresas', 'id_empresa', ['nome_empresa', 'nome', 'razao_social']],
            ],
            'id_cliente' => [
                ['clientes', 'id_cliente', ['nome', 'nome_cliente', 'razao_social']],
            ],
            'id_produto' => [
                ['produtos', 'id_produto', ['nome', 'nome_produto', 'descricao']],
            ],
            'id_locacao' => [
                ['locacoes', 'id_locacao', ['numero_contrato', 'titulo', 'descricao']],
            ],
            'id_patrimonio' => [
                ['patrimonios', 'id_patrimonio', ['descricao', 'numero_serie', 'identificacao']],
            ],
            'id_conta_pagar' => [
                ['contas_pagar', 'id_conta_pagar', ['descricao', 'titulo', 'numero_documento']],
            ],
            'id_conta_receber' => [
                ['contas_receber', 'id_conta_receber', ['descricao', 'titulo', 'numero_documento']],
            ],
        ];

        $chaveBase = $chave;
        if (!isset($mapas[$chaveBase])) {
            return null;
        }

        foreach ($mapas[$chaveBase] as [$tabela, $colunaId, $colunasNome]) {
            $nome = $this->fetchReferenceFromTable($tabela, $colunaId, $id, $colunasNome);
            if ($nome !== null && $nome !== '') {
                return $nome;
            }
        }

        return null;
    }

    private function fetchReferenceFromTable(string $tabela, string $colunaId, int $id, array $colunasNome): ?string
    {
        if (!Schema::hasTable($tabela) || !Schema::hasColumn($tabela, $colunaId)) {
            return null;
        }

        $colunasValidas = [];
        foreach ($colunasNome as $coluna) {
            if (Schema::hasColumn($tabela, $coluna)) {
                $colunasValidas[] = $coluna;
            }
        }

        if (empty($colunasValidas)) {
            return null;
        }

        $registro = DB::table($tabela)
            ->where($colunaId, $id)
            ->first(array_merge([$colunaId], $colunasValidas));

        if (!$registro) {
            return null;
        }

        foreach ($colunasValidas as $coluna) {
            $valor = $registro->{$coluna} ?? null;
            if (is_scalar($valor)) {
                $texto = trim((string) $valor);
                if ($texto !== '') {
                    return $texto;
                }
            }
        }

        return null;
    }

    private function formatTags($tags): array
    {
        if (empty($tags)) {
            return [];
        }

        if (is_string($tags)) {
            return array_values(array_filter(array_map('trim', explode(',', $tags))));
        }

        if (is_array($tags)) {
            return array_values(array_filter(array_map(function ($item) {
                return trim((string) $item);
            }, $tags)));
        }

        return [];
    }

    private function formatActionLabel(?string $acao): string
    {
        $acao = trim((string) $acao);
        if ($acao === '') {
            return '-';
        }

        $normalizada = mb_strtolower($acao);

        $mapaExato = [
            'created' => 'Criou registro',
            'create' => 'Criou registro',
            'stored' => 'Criou registro',
            'updated' => 'Atualizou registro',
            'update' => 'Atualizou registro',
            'edited' => 'Atualizou registro',
            'deleted' => 'Excluiu registro',
            'delete' => 'Excluiu registro',
            'destroyed' => 'Excluiu registro',
            'restored' => 'Restaurou registro',
            'login' => 'Realizou login',
            'logout' => 'Realizou logout',
        ];

        if (isset($mapaExato[$normalizada])) {
            return $mapaExato[$normalizada];
        }

        if (str_contains($normalizada, 'exclu') || str_contains($normalizada, 'delet') || str_contains($normalizada, 'remov')) {
            return 'Excluiu registro';
        }

        if (str_contains($normalizada, 'atualiz') || str_contains($normalizada, 'updat') || str_contains($normalizada, 'edit')) {
            return 'Atualizou registro';
        }

        if (str_contains($normalizada, 'criou') || str_contains($normalizada, 'creat') || str_contains($normalizada, 'store')) {
            return 'Criou registro';
        }

        if (str_contains($normalizada, 'cancel')) {
            return 'Cancelou registro';
        }

        return ucfirst(str_replace(['.', '_', '-'], ' ', $normalizada));
    }

    private function formatModuleLabel(?string $modulo): string
    {
        $modulo = trim((string) $modulo);
        if ($modulo === '') {
            return '-';
        }

        $mapa = [
            'conta_pagar' => 'Conta Pagar',
            'conta_receber' => 'Conta Receber',
            'boleto' => 'Boleto',
            'fluxo_caixa' => 'Fluxo Caixa',
            'cliente' => 'Cliente',
            'produto' => 'Produto',
            'empresa' => 'Empresa',
            'patrimonio' => 'Patrimonio',
            'locacao' => 'Locacao',
            'locacao_servico' => 'Locacao Servico',
            'locacao_produto' => 'Locacao Produto',
            'locacao_despesa' => 'Locacao Despesa',
            'locacao_produto_terceiro' => 'Locacao Produto Terceiro',
            'locacao_troca_produto' => 'Locacao Troca Produto',
            'locacao_retorno_patrimonio' => 'Locacao Retorno Patrimonio',
            'faturamento_locacao' => 'Faturamento Locacao',
            'pdv_venda' => 'PDV Venda',
        ];

        $normalizado = mb_strtolower(
            preg_replace('/\s+/', '_',
                preg_replace('/([a-z])([A-Z])/', '$1_$2',
                    str_replace(['-', '.'], '_', $modulo)
                )
            )
        );

        if (isset($mapa[$normalizado])) {
            return $mapa[$normalizado];
        }

        return ucwords(str_replace('_', ' ', $normalizado));
    }

    private function resolveEntityReference(RegistroAtividade $log): string
    {
        $label = trim((string) ($log->entidade_label ?? ''));
        if ($label !== '') {
            return $label;
        }

        $contexto = $log->contexto;
        if (is_array($contexto)) {
            $chavesPreferenciais = [
                'nome',
                'name',
                'titulo',
                'title',
                'descricao',
                'description',
                'numero',
                'codigo',
                'login',
                'email',
                'nome_empresa',
                'nome_cliente',
                'nome_produto',
            ];

            foreach ($chavesPreferenciais as $chave) {
                $valor = $contexto[$chave] ?? null;
                if (is_scalar($valor)) {
                    $texto = trim((string) $valor);
                    if ($texto !== '') {
                        return $texto;
                    }
                }
            }
        }

        if ($log->entidade_id !== null && $log->entidade_id !== '') {
            return 'Registro #' . $log->entidade_id;
        }

        return '-';
    }

    private function bootDynamicRelations(): void
    {
        if (self::$relationsBooted) {
            return;
        }

        RegistroAtividade::resolveRelationUsing('usuario', function (RegistroAtividade $model) {
            return $model->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
        });

        RegistroAtividade::resolveRelationUsing('empresa', function (RegistroAtividade $model) {
            return $model->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
        });

        self::$relationsBooted = true;
    }

    private function ensureAdminOrSupport(Request $request): void
    {
        $usuario = $request->user();
        if (!$usuario) {
            abort(403, 'Acesso negado.');
        }

        $isSuporte = (int) ($usuario->is_suporte ?? $usuario->isSuporte ?? 0) === 1;
        if ($isSuporte) {
            return;
        }

        $podeLogs = Perm::pode($usuario, 'admin.visualizar') && Perm::pode($usuario, 'admin.logs.visualizar');

        if (!$podeLogs) {
            abort(403, 'Acesso negado.');
        }
    }
}
