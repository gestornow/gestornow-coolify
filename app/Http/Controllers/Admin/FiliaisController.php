<?php

namespace App\Http\Controllers\Admin;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Empresa;
use App\Models\PlanoContratado;
use App\Models\Plano;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FiliaisController extends Controller
{
    public function index(Request $request)
    {
        $query = Empresa::with(['planosContratados' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->get('search');
            // Remover formatação do CNPJ para busca (manter apenas números)
            $searchNumeric = preg_replace('/[^0-9]/', '', $search);
            
            $query->where(function ($q) use ($search, $searchNumeric) {
                $q->where('nome_empresa', 'like', "%{$search}%")
                  ->orWhere('razao_social', 'like', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$searchNumeric}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('filial')) {
            $query->where('filial', $request->get('filial'));
        }

        $empresas = $query->orderBy('nome_empresa', 'asc')->paginate(50);

        // Calcular estatísticas (sem filtros de busca, status ou tipo)
        $stats = [
            'total' => Empresa::count(),
            'ativas' => Empresa::where('status', 'ativo')->count(),
            'inativas' => Empresa::where('status', 'inativo')->count(),
            'bloqueadas' => Empresa::where('status', 'bloqueado')->count(),
        ];

        // Preparar dados das empresas com plano mais recente
        $empresasComPlanos = $empresas->getCollection()->map(function ($empresa) {
            $planoMaisRecente = $empresa->planosContratados->first();
            
            return [
                'empresa' => $empresa,
                'plano_atual' => $planoMaisRecente,
                'total_planos' => $empresa->planosContratados->count()
            ];
        });

        $empresas->setCollection($empresasComPlanos);

        return view('admin.filiais.index', compact('empresas', 'stats'));
    }

    public function show(Empresa $empresa)
    {
        $empresa->load(['planosContratados' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        return view('admin.filiais.show', compact('empresa'));
    }

    public function edit(Empresa $empresa)
    {
        // Carregar apenas planos ativos disponíveis para contratação
        $planos = Plano::where('ativo', 1)->orderBy('nome')->get();
        
        // Carregar plano atual da empresa (mais recente ativo)
        $planoAtual = PlanoContratado::planoAtivoDaEmpresa($empresa->id_empresa);
        
        return view('admin.filiais.edit', compact('empresa', 'planos', 'planoAtual'));
    }

    public function update(Request $request, Empresa $empresa)
    {
        $validatedData = $request->validate([
            'nome_empresa' => 'required|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'required|string|max:18',
            'cpf' => 'nullable|string|max:14',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:10',
            'bairro' => 'nullable|string|max:100',
            'complemento' => 'nullable|string|max:100',
            'uf' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:9',
            'ie' => 'nullable|string|max:20',
            'im' => 'nullable|string|max:20',
            'codigo' => 'nullable|string|max:20',
            'filial' => 'required|in:Unica,Matriz,Filial',
            'status' => 'required|in:ativo,inativo,bloqueado,validacao,teste,cancelado,teste bloqueado',
            'dados_cadastrais' => 'required|in:incompleto,completo',
        ]);

        $empresa->update($validatedData);

        return redirect()
            ->route('admin.filiais.show', $empresa)
            ->with('success', 'Dados da filial atualizados com sucesso!');
    }

    public function updateStatus(Request $request, Empresa $empresa)
    {
        $statusAnterior = (string) $empresa->status;

        $validatedData = $request->validate([
            'status' => 'required|in:ativo,inativo,bloqueado,validacao,teste,cancelado,teste bloqueado',
        ]);

        $updateData = ['status' => $validatedData['status']];

        // Registrar data de bloqueio/inativação
        if (in_array($validatedData['status'], ['bloqueado', 'teste bloqueado', 'inativo'])) {
            $updateData['data_bloqueio'] = now();
        } elseif (in_array($empresa->status, ['bloqueado', 'teste bloqueado', 'inativo']) && !in_array($validatedData['status'], ['bloqueado', 'teste bloqueado', 'inativo'])) {
            // Limpar data de bloqueio ao ativar
            $updateData['data_bloqueio'] = null;
        }

        // Registrar data de cancelamento
        if ($validatedData['status'] === 'cancelado') {
            $updateData['data_cancelamento'] = now();
        } elseif ($empresa->status === 'cancelado' && $validatedData['status'] !== 'cancelado') {
            // Limpar data de cancelamento ao reativar
            $updateData['data_cancelamento'] = null;
        }

        $empresa->update($updateData);

        $empresaAtualizada = $empresa->fresh();
        ActionLogger::log($empresaAtualizada, 'status_alterado');

        $statusNovo = (string) ($validatedData['status'] ?? '');
        if (in_array($statusNovo, ['bloqueado', 'teste bloqueado', 'inativo'], true)) {
            ActionLogger::log($empresaAtualizada, 'bloqueio');
        }

        if ($statusNovo === 'cancelado') {
            ActionLogger::log($empresaAtualizada, 'cancelamento');
        }

        if (
            in_array($statusAnterior, ['bloqueado', 'teste bloqueado', 'inativo'], true)
            && !in_array($statusNovo, ['bloqueado', 'teste bloqueado', 'inativo'], true)
        ) {
            ActionLogger::log($empresaAtualizada, 'desbloqueio');
        }

        return redirect()
            ->route('admin.filiais.edit', $empresa)
            ->with('success', 'Status da filial alterado para ' . ucfirst($validatedData['status']) . '!');
    }

    public function create()
    {
        // Carregar planos disponíveis
        $planos = Plano::orderBy('nome')->get();
        
        return view('admin.filiais.create', compact('planos'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nome_empresa' => 'required|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'required|string|max:18|unique:empresa,cnpj',
            'cpf' => 'nullable|string|max:14',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:10',
            'bairro' => 'nullable|string|max:100',
            'complemento' => 'nullable|string|max:100',
            'uf' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:9',
            'ie' => 'nullable|string|max:20',
            'im' => 'nullable|string|max:20',
            'codigo' => 'nullable|string|max:20',
            'filial' => 'required|in:Unica,Matriz,Filial',
            'status' => 'required|in:ativo,inativo,bloqueado,validacao,teste,cancelado,teste bloqueado',
            'dados_cadastrais' => 'required|in:incompleto,completo',
        ]);

        $empresa = Empresa::create($validatedData);

        return redirect()
            ->route('admin.filiais.show', $empresa)
            ->with('success', 'Filial cadastrada com sucesso!');
    }

    public function deleteMultiple(Request $request)
    {
        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:empresa,id_empresa',
        ]);

        $deletedCount = Empresa::whereIn('id_empresa', $validatedData['ids'])->delete();

        return redirect()
            ->route('admin.filiais.index')
            ->with('success', "{$deletedCount} filial(is) excluída(s) com sucesso!");
    }
}