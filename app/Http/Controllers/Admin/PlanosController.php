<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Empresa;
use App\Models\Plano;
use App\Models\PlanoPromocao;
use App\Models\PlanoModulo;
use App\Models\PlanoContratado;
use App\Models\PlanoContratadoModulo;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PlanosController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        $query = Plano::with(['modulos', 'promocoes'])->orderBy('nome');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('descricao', 'like', "%{$search}%");
            });
        }

        $planos = $query->paginate($perPage)->withQueryString();
        
        return view('admin.planos.index', compact('planos'));
    }

    public function create()
    {
        $modulos = Modulo::ordenados()->get();
        
        return view('admin.planos.create', compact('modulos'));
    }

    public function store(Request $request)
    {
        // Log para debug
        \Log::info('Tentando criar plano', [
            'request_data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:100',
            'descricao' => 'nullable|string',
            'valor' => 'required|numeric|min:0',
            'adesao' => 'required|numeric|min:0',
            'relatorios' => 'required|in:S,N,1,0',
            'bancos' => 'required|in:S,N,1,0',
            'assinatura_digital' => 'required|in:S,N,1,0',
            'contratos' => 'required|in:S,N,1,0',
            'faturas' => 'required|in:S,N,1,0',
            'modulos' => 'nullable|array',
            'modulos.*.id_modulo' => 'required|exists:modulos,id_modulo',
            'modulos.*.limite' => 'nullable|integer|min:0',
            'modulos.*.ativo' => 'nullable|boolean',
        ], [
            'nome.required' => 'O nome do plano é obrigatório.',
            'valor.required' => 'O valor do plano é obrigatório.',
            'valor.numeric' => 'O valor deve ser um número válido.',
            'adesao.required' => 'O valor da adesão é obrigatório.',
            'adesao.numeric' => 'O valor da adesão deve ser um número válido.',
            'relatorios.required' => 'Selecione se o plano inclui relatórios.',
            'bancos.required' => 'Selecione se o plano inclui bancos.',
            'assinatura_digital.required' => 'Selecione se o plano inclui assinatura digital.',
            'contratos.required' => 'Selecione se o plano inclui contratos.',
            'faturas.required' => 'Selecione se o plano inclui faturas.',
        ]);

        if ($validator->fails()) {
            \Log::warning('Validação falhou ao criar plano', [
                'errors' => $validator->errors()->toArray()
            ]);
            
            return redirect()->back()
                           ->withErrors($validator)
                           ->withInput();
        }

        DB::beginTransaction();

        try {
            $recursos = $this->mapearRecursosParaPersistencia($request);

            // Criar o plano
            $plano = Plano::create([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'valor' => $request->valor,
                'adesao' => $request->adesao,
                'relatorios' => $recursos['relatorios'],
                'bancos' => $recursos['bancos'],
                'assinatura_digital' => $recursos['assinatura_digital'],
                'contratos' => $recursos['contratos'],
                'faturas' => $recursos['faturas'],
                'ativo' => 1, // Plano criado como ativo por padrão
            ]);

            // Criar os módulos do plano
            if ($request->has('modulos') && is_array($request->modulos)) {
                foreach ($request->modulos as $moduloData) {
                    // Se o campo 'ativo' for 1 ou "1" (checkbox marcado)
                    if (isset($moduloData['ativo']) && ($moduloData['ativo'] == 1 || $moduloData['ativo'] === '1' || $moduloData['ativo'] === true)) {
                        PlanoModulo::create([
                            'id_plano' => $plano->id_plano,
                            'id_modulo' => $moduloData['id_modulo'],
                            'limite' => $moduloData['limite'] ?? null,
                            'ativo' => 1,
                        ]);
                    }
                }
            }

            DB::commit();

            \Log::info('Plano criado com sucesso', [
                'plano_id' => $plano->id_plano,
                'nome' => $plano->nome
            ]);

            return redirect()->route('admin.planos.index')
                           ->with('success', 'Plano criado com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Erro ao criar plano', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                           ->with('error', 'Erro ao criar plano: ' . $e->getMessage())
                           ->withInput();
        }
    }

    public function show(Plano $plano)
    {
        $plano->load(['modulos.modulo']);
        
        return view('admin.planos.show', compact('plano'));
    }

    public function edit(Plano $plano)
    {
        $plano->load(['todosModulos.modulo']);

        $promocoes = collect();
        if (Schema::hasTable('planos_promocoes')) {
            $queryPromocoes = PlanoPromocao::query()
                ->where('id_plano', $plano->id_plano);

            if (Schema::hasColumn('planos_promocoes', 'prioridade')) {
                $queryPromocoes->orderBy('prioridade', 'asc');
            }

            $promocoes = $queryPromocoes
                ->orderBy('id', 'desc')
                ->get();
        }

        // Carrega apenas módulos principais com seus submódulos
        $modulos = Modulo::with('submodulos')->principais()->ordenados()->get();
        
        return view('admin.planos.edit', compact('plano', 'modulos', 'promocoes'));
    }

    public function storePromocao(Request $request, Plano $plano)
    {
        if (!Schema::hasTable('planos_promocoes')) {
            return redirect()->back()->with('error', 'Tabela de promoções não instalada. Execute as migrations.');
        }

        $dados = $request->validate([
            'nome' => 'required|string|max:120',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'desconto_mensal' => 'nullable|numeric|min:0',
            'desconto_adesao' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string|max:1000',
            'ativo' => 'nullable|boolean',
        ]);

        PlanoPromocao::create([
            'id_plano' => $plano->id_plano,
            'nome' => $dados['nome'],
            'data_inicio' => $dados['data_inicio'] ?? null,
            'data_fim' => $dados['data_fim'] ?? null,
            'desconto_mensal' => $dados['desconto_mensal'] ?? 0,
            'desconto_adesao' => $dados['desconto_adesao'] ?? 0,
            'ativo' => (bool) ($dados['ativo'] ?? true),
            'observacoes' => $dados['observacoes'] ?? null,
        ]);

        return redirect()
            ->route('admin.planos.edit', $plano)
            ->with('success', 'Promocao cadastrada com sucesso.');
    }

    public function destroyPromocao(Plano $plano, int $idPromocao)
    {
        if (!Schema::hasTable('planos_promocoes')) {
            return redirect()->back()->with('error', 'Tabela de promocoes nao instalada. Execute as migrations/SQL de billing.');
        }

        $promocao = PlanoPromocao::query()
            ->where('id', $idPromocao)
            ->where('id_plano', $plano->id_plano)
            ->firstOrFail();

        $promocao->delete();

        return redirect()
            ->route('admin.planos.edit', $plano)
            ->with('success', 'Promocao removida com sucesso.');
    }

    public function update(Request $request, Plano $plano)
    {
        // Log para debug
        \Log::info('Tentando atualizar plano', [
            'plano_id' => $plano->id_plano,
            'request_data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:100',
            'descricao' => 'nullable|string',
            'valor' => 'required|numeric|min:0',
            'adesao' => 'required|numeric|min:0',
            'relatorios' => 'required|in:S,N,1,0',
            'bancos' => 'required|in:S,N,1,0',
            'assinatura_digital' => 'required|in:S,N,1,0',
            'contratos' => 'required|in:S,N,1,0',
            'faturas' => 'required|in:S,N,1,0',
            'modulos' => 'nullable|array',
            'modulos.*.id_modulo' => 'required|exists:modulos,id_modulo',
            'modulos.*.limite' => 'nullable|integer|min:0',
            'modulos.*.ativo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            \Log::warning('Validação falhou ao atualizar plano', [
                'plano_id' => $plano->id_plano,
                'errors' => $validator->errors()->toArray()
            ]);
            
            return redirect()->back()
                           ->withErrors($validator)
                           ->withInput();
        }

        // Verificar se houve mudanças nos módulos
        $modulosAtuais = PlanoModulo::where('id_plano', $plano->id_plano)
            ->where('ativo', 1)
            ->pluck('id_modulo')
            ->sort()
            ->values()
            ->toArray();

        $limitesAtuais = PlanoModulo::where('id_plano', $plano->id_plano)
            ->where('ativo', 1)
            ->get(['id_modulo', 'limite'])
            ->mapWithKeys(function ($item) {
                $limite = is_null($item->limite) ? null : (int) $item->limite;
                return [(int) $item->id_modulo => $limite];
            })
            ->toArray();
        
        $modulosNovos = [];
        $limitesNovos = [];
        if ($request->has('modulos') && is_array($request->modulos)) {
            foreach ($request->modulos as $moduloData) {
                // Se o campo 'ativo' for 1 ou "1" (checkbox marcado)
                if (isset($moduloData['ativo']) && ($moduloData['ativo'] == 1 || $moduloData['ativo'] === '1' || $moduloData['ativo'] === true)) {
                    $idModulo = (int) $moduloData['id_modulo'];
                    $modulosNovos[] = $idModulo;
                    $limitesNovos[$idModulo] = isset($moduloData['limite']) && $moduloData['limite'] !== ''
                        ? (int) $moduloData['limite']
                        : null;
                }
            }
        }
        sort($modulosNovos);
        ksort($limitesAtuais);
        ksort($limitesNovos);
        
        $houveAlteracaoModulos = $modulosAtuais !== $modulosNovos;
        $houveAlteracaoLimites = $limitesAtuais !== $limitesNovos;
        $houveAlteracao = $houveAlteracaoModulos || $houveAlteracaoLimites;
        
        // Contar alvos reais da propagação: contratos ativos (nome e nome + "(Teste)")
        // e empresas em teste vinculadas por id_plano_teste.
        $nomesPlanoRelacionados = array_values(array_filter(array_unique([
            trim((string) $plano->nome),
            trim((string) $request->input('nome')),
        ])));

        $empresasComContratos = PlanoContratado::where('status', 'ativo')
            ->where(function ($query) use ($nomesPlanoRelacionados) {
                foreach ($nomesPlanoRelacionados as $nomePlano) {
                    $query->orWhere('nome', $nomePlano)
                        ->orWhere('nome', 'like', $nomePlano . ' (Teste)%');
                }
            })
            ->pluck('id_empresa')
            ->map(function ($idEmpresa) {
                return (int) $idEmpresa;
            })
            ->toArray();

        $empresasTesteIds = Empresa::where('status', 'teste')
            ->where('id_plano_teste', $plano->id_plano)
            ->pluck('id_empresa')
            ->map(function ($idEmpresa) {
                return (int) $idEmpresa;
            })
            ->toArray();

        $totalAlvosPropagacao = count(array_unique(array_merge($empresasComContratos, $empresasTesteIds)));
        
        // Se houve alteração de módulos E existem planos contratados, perguntar ao usuário
        if ($houveAlteracao && $totalAlvosPropagacao > 0 && !$request->has('aplicar_planos_contratados')) {
            // Armazenar dados em cache para evitar perda de payload grande na sessão.
            $confirmToken = (string) Str::uuid();
            
            $dataToStore = [
                'plano_update_data' => $request->all(),
                'plano_id' => $plano->id_plano,
            ];
            
            // Salvar no cache
            Cache::put('plano_update_data:' . $confirmToken, $dataToStore, now()->addMinutes(20));
            
            // Backup na sessão (para ambientes com múltiplos servidores sem cache compartilhado)
            session([
                'plano_update_data_' . $confirmToken => $request->all(),
                'plano_id_' . $confirmToken => $plano->id_plano,
            ]);
            
            \Log::info('[update] Dados armazenados para confirmação', [
                'confirm_token' => $confirmToken,
                'plano_id' => $plano->id_plano,
            ]);
            
            return view('admin.planos.confirm-update', [
                'plano' => $plano,
                'planosContratadosCount' => $totalAlvosPropagacao,
                'modulosAdicionados' => array_diff($modulosNovos, $modulosAtuais),
                'modulosRemovidos' => array_diff($modulosAtuais, $modulosNovos),
                'confirmToken' => $confirmToken,
            ]);
        }

        // Processar a atualização
        return $this->processUpdate($request, $plano);
    }
    
    private function processUpdate(Request $request, Plano $plano)
    {
        DB::beginTransaction();

        try {
            $nomePlanoAntes = (string) $plano->nome;
            $recursos = $this->mapearRecursosParaPersistencia($request);

            // Atualizar o plano
            $plano->update([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'valor' => $request->valor,
                'adesao' => $request->adesao,
                'relatorios' => $recursos['relatorios'],
                'bancos' => $recursos['bancos'],
                'assinatura_digital' => $recursos['assinatura_digital'],
                'contratos' => $recursos['contratos'],
                'faturas' => $recursos['faturas'],
                'ativo' => $request->has('ativo') ? 1 : 0,
            ]);

            // Remover módulos antigos
            PlanoModulo::where('id_plano', $plano->id_plano)->delete();

            // Criar os novos módulos do plano
            $modulosNovos = [];
            $limites = [];
            if ($request->has('modulos') && is_array($request->modulos)) {
                foreach ($request->modulos as $moduloData) {
                    // Se o campo 'ativo' for 1 ou "1" (checkbox marcado)
                    if (isset($moduloData['ativo']) && ($moduloData['ativo'] == 1 || $moduloData['ativo'] === '1' || $moduloData['ativo'] === true)) {
                        $idModulo = (int) $moduloData['id_modulo'];
                        $limiteModulo = isset($moduloData['limite']) && $moduloData['limite'] !== ''
                            ? (int) $moduloData['limite']
                            : null;

                        $modulosNovos[] = $idModulo;
                        $limites[$idModulo] = $limiteModulo;
                        
                        PlanoModulo::create([
                            'id_plano' => $plano->id_plano,
                            'id_modulo' => $idModulo,
                            'limite' => $limites[$idModulo],
                            'ativo' => 1,
                        ]);
                    }
                }
            }

            // Se deve aplicar aos planos contratados
            if ($request->input('aplicar_planos_contratados') === 'sim') {
                $nomesPlanoRelacionados = array_values(array_filter(array_unique([
                    trim($nomePlanoAntes),
                    trim((string) $plano->nome),
                ])));

                $planosContratadosPorNome = PlanoContratado::where('status', 'ativo')
                    ->where(function ($query) use ($nomesPlanoRelacionados) {
                        foreach ($nomesPlanoRelacionados as $nomePlano) {
                            $query->orWhere('nome', $nomePlano)
                                ->orWhere('nome', 'like', $nomePlano . ' (Teste)%');
                        }
                    })
                    ->get();

                $empresasTesteIds = Empresa::where('status', 'teste')
                    ->where('id_plano_teste', $plano->id_plano)
                    ->pluck('id_empresa')
                    ->map(function ($idEmpresa) {
                        return (int) $idEmpresa;
                    })
                    ->toArray();

                $planosContratadosTeste = collect();

                if (!empty($empresasTesteIds)) {
                    $planosContratadosTeste = PlanoContratado::whereIn('id_empresa', $empresasTesteIds)
                        ->where('status', 'ativo')
                        ->orderByDesc('id')
                        ->get()
                        ->groupBy('id_empresa')
                        ->map(function ($itensPorEmpresa) {
                            return $itensPorEmpresa->first();
                        })
                        ->values();

                    $empresasComContratoAtivo = $planosContratadosTeste
                        ->pluck('id_empresa')
                        ->map(function ($idEmpresa) {
                            return (int) $idEmpresa;
                        })
                        ->toArray();

                    $empresasSemContratoAtivo = array_diff($empresasTesteIds, $empresasComContratoAtivo);

                    foreach ($empresasSemContratoAtivo as $idEmpresaTeste) {
                        $novoContratoTeste = PlanoContratado::create([
                            'id_empresa' => (int) $idEmpresaTeste,
                            'nome' => (string) $plano->nome . ' (Teste)',
                            'valor' => 0,
                            'adesao' => 0,
                            'data_contratacao' => now(),
                            'status' => 'ativo',
                            'observacoes' => 'Plano de teste sincronizado em ' . now()->format('d/m/Y H:i'),
                        ]);

                        $planosContratadosTeste->push($novoContratoTeste);
                    }
                }

                $planosContratados = $planosContratadosPorNome
                    ->concat($planosContratadosTeste)
                    ->unique('id')
                    ->values();
                
                foreach ($planosContratados as $planoContratado) {
                    $ehContratoTeste = in_array((int) $planoContratado->id_empresa, $empresasTesteIds, true)
                        || str_contains(strtolower((string) $planoContratado->nome), '(teste)');

                    $nomeEsperado = $ehContratoTeste
                        ? (string) $plano->nome . ' (Teste)'
                        : (string) $plano->nome;

                    // Mantém o nome do contratado alinhado com o plano-base.
                    if ((string) $planoContratado->nome !== $nomeEsperado) {
                        $planoContratado->update(['nome' => $nomeEsperado]);
                    }

                    // Sincroniza todos os módulos/limites do contrato com o plano-base.
                    foreach ($modulosNovos as $idModulo) {
                        PlanoContratadoModulo::updateOrCreate(
                            [
                                'id_plano_contratado' => $planoContratado->id,
                                'id_modulo' => $idModulo,
                            ],
                            [
                                'limite' => $limites[$idModulo] ?? null,
                                'ativo' => 1,
                            ]
                        );
                    }

                    if (!empty($modulosNovos)) {
                        PlanoContratadoModulo::where('id_plano_contratado', $planoContratado->id)
                            ->whereNotIn('id_modulo', $modulosNovos)
                            ->update(['ativo' => 0]);
                    } else {
                        PlanoContratadoModulo::where('id_plano_contratado', $planoContratado->id)
                            ->update(['ativo' => 0]);
                    }
                }

                \Log::info('Módulos do plano aplicados em contratos ativos', [
                    'plano_id' => $plano->id_plano,
                    'planos_contratados_afetados' => $planosContratados->count(),
                    'empresas_teste_alvo' => count($empresasTesteIds),
                    'modulos_ativos' => count($modulosNovos),
                ]);
            }

            DB::commit();

            \Log::info('Plano atualizado com sucesso', [
                'plano_id' => $plano->id_plano,
                'nome' => $plano->nome
            ]);

            // Limpar sessão
            session()->forget(['plano_update_data', 'plano_id']);

            return redirect()->route('admin.planos.index')
                           ->with('success', 'Plano atualizado com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Erro ao atualizar plano', [
                'plano_id' => $plano->id_plano,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                           ->with('error', 'Erro ao atualizar plano: ' . $e->getMessage())
                           ->withInput();
        }
    }
    
    public function confirmUpdate(Request $request)
    {
        // Recuperar dados do cache de confirmação
        $confirmToken = (string) $request->input('confirm_token');
        
        \Log::info('[confirmUpdate] Iniciando', [
            'confirm_token' => $confirmToken,
            'all_input' => $request->all(),
        ]);

        // Tentar recuperar do cache primeiro
        $cacheData = null;
        if ($confirmToken) {
            $cacheData = Cache::pull('plano_update_data:' . $confirmToken);
            \Log::info('[confirmUpdate] Cache pull resultado', [
                'found' => !is_null($cacheData),
            ]);
        }

        // Fallback para sessão (armazenado com prefixo do token)
        $planoUpdateData = $cacheData['plano_update_data'] 
            ?? session('plano_update_data_' . $confirmToken) 
            ?? session('plano_update_data');
        $planoId = $cacheData['plano_id'] 
            ?? session('plano_id_' . $confirmToken) 
            ?? session('plano_id');
        
        \Log::info('[confirmUpdate] Dados recuperados', [
            'plano_id' => $planoId,
            'has_update_data' => !is_null($planoUpdateData),
        ]);
        
        if (!$planoUpdateData || !$planoId) {
            \Log::warning('[confirmUpdate] Dados não encontrados - redirecionando');
            return redirect()->route('admin.planos.index')
                           ->with('error', 'Sessão expirada. Por favor, tente novamente.');
        }

        // Limpar dados da sessão após uso
        session()->forget(['plano_update_data_' . $confirmToken, 'plano_id_' . $confirmToken, 'plano_update_data', 'plano_id']);
        
        $plano = Plano::findOrFail($planoId);
        
        // Criar novo request com os dados salvos + a escolha do usuário
        $newRequest = new Request($planoUpdateData);
        $newRequest->merge([
            'aplicar_planos_contratados' => $request->input('aplicar_planos_contratados')
        ]);
        
        \Log::info('[confirmUpdate] Chamando processUpdate', [
            'plano_nome' => $plano->nome,
            'aplicar_planos_contratados' => $request->input('aplicar_planos_contratados'),
        ]);
        
        return $this->processUpdate($newRequest, $plano);
    }

    private function mapearRecursosParaPersistencia(Request $request): array
    {
        $recursosSN = [
            'relatorios' => $this->normalizarRecursoSN($request->input('relatorios')),
            'bancos' => $this->normalizarRecursoSN($request->input('bancos')),
            'assinatura_digital' => $this->normalizarRecursoSN($request->input('assinatura_digital')),
            'contratos' => $this->normalizarRecursoSN($request->input('contratos')),
            'faturas' => $this->normalizarRecursoSN($request->input('faturas')),
        ];

        if (!$this->recursosPlanosPersistemComoInteiro()) {
            return $recursosSN;
        }

        return [
            'relatorios' => $recursosSN['relatorios'] === 'S' ? 1 : 0,
            'bancos' => $recursosSN['bancos'] === 'S' ? 1 : 0,
            'assinatura_digital' => $recursosSN['assinatura_digital'] === 'S' ? 1 : 0,
            'contratos' => $recursosSN['contratos'] === 'S' ? 1 : 0,
            'faturas' => $recursosSN['faturas'] === 'S' ? 1 : 0,
        ];
    }

    private function normalizarRecursoSN($valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'S' : 'N';
        }

        $normalizado = strtoupper(trim((string) $valor));

        return in_array($normalizado, ['S', '1', 'TRUE', 'SIM', 'Y', 'YES'], true)
            ? 'S'
            : 'N';
    }

    private function recursosPlanosPersistemComoInteiro(): bool
    {
        try {
            $coluna = DB::selectOne("SHOW COLUMNS FROM planos LIKE 'relatorios'");
            $tipo = strtolower((string) ($coluna->Type ?? ''));

            return str_contains($tipo, 'int')
                || str_contains($tipo, 'bool')
                || str_contains($tipo, 'bit');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function destroy(Plano $plano)
    {
        DB::beginTransaction();

        try {
            // Verificar se existem planos contratados
            $planosContratados = PlanoContratado::where('nome', $plano->nome)->get();
            
            // Remover os planos contratados e seus módulos
            if ($planosContratados->count() > 0) {
                foreach ($planosContratados as $planoContratado) {
                    // Remover módulos do plano contratado
                    PlanoContratadoModulo::where('id_plano_contratado', $planoContratado->id)->delete();
                    
                    // Remover o plano contratado
                    $planoContratado->delete();
                }
            }

            // Remover módulos do plano
            PlanoModulo::where('id_plano', $plano->id_plano)->delete();
            
            // Remover o plano
            $plano->delete();

            DB::commit();

            $mensagem = 'Plano excluído com sucesso!';
            if ($planosContratados->count() > 0) {
                $mensagem .= ' ' . $planosContratados->count() . ' contrato(s) ativo(s) também foi(ram) removido(s).';
            }

            return redirect()->route('admin.planos.index')
                           ->with('success', $mensagem);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                           ->with('error', 'Erro ao excluir plano: ' . $e->getMessage());
        }
    }

    /**
     * Ativar/Inativar plano
     */
    public function toggleStatus(Plano $plano)
    {
        try {
            $novoStatus = !$plano->ativo;
            $plano->update(['ativo' => $novoStatus]);

            $mensagem = $novoStatus 
                ? "Plano '{$plano->nome}' ativado com sucesso!" 
                : "Plano '{$plano->nome}' inativado com sucesso!";

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $mensagem,
                    'ativo' => $novoStatus
                ]);
            }

            return redirect()->back()->with('success', $mensagem);

        } catch (\Exception $e) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao alterar status do plano: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Erro ao alterar status do plano: ' . $e->getMessage());
        }
    }

    // Métodos para planos contratados
    public function planosContratados()
    {
        $planosContratados = PlanoContratado::with(['empresa', 'modulos'])
                                           ->orderBy('created_at', 'desc')
                                           ->get();
        
        return view('admin.planos.contratados.index', compact('planosContratados'));
    }

    public function showPlanoContratado(PlanoContratado $planoContratado)
    {
        $planoContratado->load(['empresa', 'todosModulos']);
        
        return view('admin.planos.contratados.show', compact('planoContratado'));
    }

    // Método para contratar plano para uma empresa
    public function contratarPlano(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|exists:empresa,id_empresa',
            'id_plano' => 'required|exists:planos,id_plano',
            'observacoes' => 'nullable|string',
            // permitir valores customizados
            'valor' => 'nullable|numeric|min:0',
            'adesao' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $plano = Plano::with(['modulos.modulo'])->findOrFail($request->id_plano);

            // Inativar todos os planos contratados anteriores desta empresa
            PlanoContratado::where('id_empresa', $request->id_empresa)
                          ->where('status', 'ativo')
                          ->update(['status' => 'inativo']);

            // Criar plano contratado (usar valores customizados quando presentes)
            $valorContratado = $request->filled('valor') ? $request->valor : $plano->valor;
            $adesaoContratada = $request->filled('adesao') ? $request->adesao : $plano->adesao;

            $planoContratado = PlanoContratado::create([
                'id_empresa' => $request->id_empresa,
                'nome' => $plano->nome,
                'valor' => $valorContratado,
                'adesao' => $adesaoContratada,
                'data_contratacao' => now(),
                'status' => 'ativo',
                'observacoes' => $request->observacoes,
            ]);

            // Criar módulos contratados baseados no plano atual
            foreach ($plano->modulos as $planoModulo) {
                // Usar id_modulo diretamente da tabela planos_modulos
                PlanoContratadoModulo::create([
                    'id_plano_contratado' => $planoContratado->id,
                    'id_modulo' => $planoModulo->id_modulo,
                    'limite' => $planoModulo->limite,
                    'ativo' => 1,
                ]);
            }

            DB::commit();

            // Recarregar o plano contratado com dados formatados
            $planoContratado->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Plano contratado com sucesso!',
                'plano_contratado' => [
                    'id' => $planoContratado->id,
                    'nome' => $planoContratado->nome,
                    'valor_formatado' => $planoContratado->valor_formatado,
                    'adesao_formatada' => $planoContratado->adesao_formatada,
                    'data_contratacao_formatada' => $planoContratado->data_contratacao_formatada,
                    'observacoes' => $planoContratado->observacoes
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao contratar plano: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getModulos($idPlanoContratado)
    {
        try {
            // Buscar módulos do plano contratado com join na tabela modulos
            $modulos = DB::table('planos_contratados_modulos as pcm')
                ->join('modulos as m', 'pcm.id_modulo', '=', 'm.id_modulo')
                ->leftJoin('modulos as mp', 'm.id_modulo_pai', '=', 'mp.id_modulo')
                ->where('pcm.id_plano_contratado', $idPlanoContratado)
                ->where('pcm.ativo', 1)
                ->select(
                    'pcm.id',
                    'm.id_modulo',
                    'm.nome as nome', 
                    'm.icone',
                    'm.id_modulo_pai',
                    'mp.nome as nome_pai',
                    'pcm.limite',
                    'm.ordem'
                )
                ->orderBy('m.ordem')
                ->orderBy('m.nome')
                ->get();

            // Organizar módulos hierarquicamente
            $principais = [];
            $submodulos = [];
            
            foreach ($modulos as $modulo) {
                if (!$modulo->id_modulo_pai) {
                    // É um módulo principal
                    $principais[$modulo->id_modulo] = [
                        'id' => $modulo->id,
                        'id_modulo' => $modulo->id_modulo,
                        'nome' => $modulo->nome,
                        'icone' => $modulo->icone,
                        'limite' => $modulo->limite,
                        'ordem' => $modulo->ordem,
                        'submodulos' => []
                    ];
                } else {
                    // É um submódulo
                    if (!isset($submodulos[$modulo->id_modulo_pai])) {
                        $submodulos[$modulo->id_modulo_pai] = [];
                    }
                    $submodulos[$modulo->id_modulo_pai][] = [
                        'id' => $modulo->id,
                        'id_modulo' => $modulo->id_modulo,
                        'nome' => $modulo->nome,
                        'icone' => $modulo->icone,
                        'limite' => $modulo->limite,
                        'ordem' => $modulo->ordem
                    ];
                }
            }
            
            // Adicionar submódulos aos módulos principais
            foreach ($principais as $idModulo => $modulo) {
                if (isset($submodulos[$idModulo])) {
                    $principais[$idModulo]['submodulos'] = $submodulos[$idModulo];
                }
            }
            
            // Converter para array indexado
            $modulosOrganizados = array_values($principais);

            return response()->json([
                'success' => true,
                'modulos' => $modulosOrganizados
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar módulos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function editPlanoContratado(PlanoContratado $planoContratado)
    {
        // Carregar empresa e módulos disponíveis (principais com submódulos)
        $planoContratado->load('empresa');
        $modulos = Modulo::with('submodulos')->principais()->ordenados()->get();
        
        // Buscar IDs dos módulos contratados
        $modulosContratados = DB::table('planos_contratados_modulos')
            ->where('id_plano_contratado', $planoContratado->id)
            ->where('ativo', 1)
            ->pluck('id_modulo')
            ->toArray();
        
        // Buscar limites dos módulos contratados (usando id_modulo como chave)
        $limites = DB::table('planos_contratados_modulos')
            ->where('id_plano_contratado', $planoContratado->id)
            ->where('ativo', 1)
            ->pluck('limite', 'id_modulo')
            ->toArray();
        
        return view('admin.planos.contratados.edit', compact('planoContratado', 'modulos', 'modulosContratados', 'limites'));
    }

    public function updatePlanoContratado(Request $request, PlanoContratado $planoContratado)
    {
        $validatedData = $request->validate([
            'modulos' => 'nullable|array',
            'modulos.*' => 'integer',
            'limites' => 'nullable|array',
        ]);

        DB::beginTransaction();

        try {
            // Desativar todos os módulos atuais
            DB::table('planos_contratados_modulos')
                ->where('id_plano_contratado', $planoContratado->id)
                ->update(['ativo' => 0]);

            // Adicionar/reativar módulos selecionados
            if (!empty($validatedData['modulos'])) {
                foreach ($validatedData['modulos'] as $idModulo) {
                    $limite = $validatedData['limites'][$idModulo] ?? null;

                    // Verificar se já existe
                    $existente = DB::table('planos_contratados_modulos')
                        ->where('id_plano_contratado', $planoContratado->id)
                        ->where('id_modulo', $idModulo)
                        ->first();

                    if ($existente) {
                        // Reativar e atualizar limite
                        DB::table('planos_contratados_modulos')
                            ->where('id', $existente->id)
                            ->update([
                                'ativo' => 1,
                                'limite' => $limite,
                                'updated_at' => now()
                            ]);
                    } else {
                        // Criar novo
                        DB::table('planos_contratados_modulos')->insert([
                            'id_plano_contratado' => $planoContratado->id,
                            'id_modulo' => $idModulo,
                            'limite' => $limite,
                            'ativo' => 1,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('admin.filiais.show', $planoContratado->id_empresa)
                ->with('success', 'Módulos do plano contratado atualizados com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()
                ->back()
                ->with('error', 'Erro ao atualizar módulos: ' . $e->getMessage())
                ->withInput();
        }
    }
}