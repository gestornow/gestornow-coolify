<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\FormaPagamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FormaPagamentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $query = FormaPagamento::where('id_empresa', $idEmpresa);

        // Filtro por busca
        if ($request->filled('busca')) {
            $query->where('nome', 'like', '%' . $request->busca . '%');
        }

        $formasPagamento = $query->orderBy('nome')->paginate(50);

        return view('formas-pagamento.index', compact('formasPagamento'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('formas-pagamento.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => ['required', 'string', 'max:100'],
                'descricao' => ['nullable', 'string'],
            ], [
                'nome.required' => 'O nome da forma de pagamento é obrigatório.',
                'nome.max' => 'O nome deve ter no máximo 100 caracteres.',
            ]);

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            if (!$idEmpresa) {
                throw new \Exception('Empresa não identificada.');
            }

            FormaPagamento::create([
                'id_empresa' => $idEmpresa,
                'nome' => $request->nome,
                'descricao' => $request->descricao,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Forma de pagamento cadastrada com sucesso.'
                ]);
            }

            return redirect()->route('formas-pagamento.index')->with('success', 'Forma de pagamento cadastrada com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('Erro ao criar forma de pagamento: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $formaPagamento = FormaPagamento::where('id_forma_pagamento', $id)
            ->where('id_empresa', $idEmpresa)
            ->firstOrFail();

        return view('formas-pagamento.edit', compact('formaPagamento'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $formaPagamento = FormaPagamento::where('id_forma_pagamento', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();

            $validated = $request->validate([
                'nome' => ['required', 'string', 'max:100'],
                'descricao' => ['nullable', 'string'],
            ], [
                'nome.required' => 'O nome da forma de pagamento é obrigatório.',
                'nome.max' => 'O nome deve ter no máximo 100 caracteres.',
            ]);

            $formaPagamento->update([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Forma de pagamento atualizada com sucesso.'
                ]);
            }

            return redirect()->route('formas-pagamento.index')->with('success', 'Forma de pagamento atualizada com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar forma de pagamento: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $formaPagamento = FormaPagamento::where('id_forma_pagamento', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();

            $formaPagamento->delete();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Forma de pagamento excluída com sucesso.'
                ]);
            }

            return redirect()->route('formas-pagamento.index')->with('success', 'Forma de pagamento excluída com sucesso.');

        } catch (\Exception $e) {
            Log::error('Erro ao excluir forma de pagamento: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
