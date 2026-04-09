<?php

namespace App\Http\Controllers\Locacao;

use App\Http\Controllers\Controller;
use App\Domain\Locacao\Models\LocacaoModeloContrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Traits\VerificaLimite;

class ModeloContratoController extends Controller
{
    use VerificaLimite;
    /**
     * Listagem de modelos de contrato
     */
    public function index()
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $modelos = LocacaoModeloContrato::where('id_empresa', $idEmpresa)
            ->orderBy('padrao', 'desc')
            ->orderBy('nome')
            ->paginate(20);
        
        return view('locacoes.modelos-contrato.index', compact('modelos'));
    }

    /**
     * Formulário de criação
     */
    public function create()
    {
        $tipoModelo = $this->normalizarTipoModeloDocumento(request()->query('tipo'));
        $variaveisDisponiveis = $this->getVariaveisDisponiveis();
        $conteudoPadrao = $this->getConteudoPadraoEstiloContrato();
        $colunasTabelaDisponiveis = $this->getColunasTabelaDisponiveis();
        return view('locacoes.modelos-contrato.create', compact('variaveisDisponiveis', 'conteudoPadrao', 'colunasTabelaDisponiveis', 'tipoModelo'));
    }

    /**
     * Salvar novo modelo
     */
    public function store(Request $request)
    {
        // Verificar limite de modelos de contrato
        $limiteCheck = $this->verificarLimiteModeloContrato();
        if ($limiteCheck) {
            return $limiteCheck;
        }

        Log::info('ModeloContrato.store iniciado', [
            'id_empresa' => session('id_empresa') ?? Auth::user()->id_empresa ?? null,
            'payload_keys' => array_keys($request->all()),
            'has_assinatura_base64' => $request->filled('assinatura_locadora_base64'),
            'has_assinatura_arquivo' => $request->hasFile('assinatura_locadora_arquivo'),
        ]);

        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:100',
            'descricao' => 'nullable|string',
            'clausulas_html' => 'nullable|string',
            'cabecalho_html' => 'nullable|string',
            'rodape_html' => 'nullable|string',
            'css_personalizado' => 'nullable|string',
            'titulo_documento' => 'nullable|string|max:120',
            'subtitulo_documento' => 'nullable|string|max:200',
            'cor_borda' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'colunas_tabela_produtos' => 'nullable|array',
            'colunas_tabela_produtos.*' => 'string|in:produto,quantidade,dias,valor_unitario,subtotal',
            'exibir_assinatura_locadora' => 'nullable|boolean',
            'exibir_assinatura_cliente' => 'nullable|boolean',
            'assinatura_locadora_base64' => 'nullable|string',
            'assinatura_locadora_arquivo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'tipo_modelo' => 'nullable|string|in:contrato,orcamento,medicao',
            'usa_medicao' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('ModeloContrato.store validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $tipoModelo = $this->normalizarTipoModeloDocumento($request->input('tipo_modelo'));
            
            // Se marcou como padrão, desmarcar os outros
            if ($request->padrao) {
                $queryPadrao = LocacaoModeloContrato::where('id_empresa', $idEmpresa);

                if ($this->hasColunaModeloContrato('tipo_modelo')) {
                    $queryPadrao->where('tipo_modelo', $tipoModelo);
                } elseif ($this->hasColunaModeloContrato('usa_medicao')) {
                    if ($tipoModelo === 'medicao') {
                        $queryPadrao->where('usa_medicao', true);
                    } else {
                        $queryPadrao->where(function ($sub) {
                            $sub->whereNull('usa_medicao')->orWhere('usa_medicao', false);
                        });
                    }
                }

                $queryPadrao->update(['padrao' => false]);
            }

            $assinaturaLocadoraUrl = null;
            $assinaturaBase64 = $request->input('assinatura_locadora_base64');
            if (!empty($assinaturaBase64)) {
                $assinaturaLocadoraUrl = $this->salvarAssinaturaBase64($assinaturaBase64, $idEmpresa);
                if (empty($assinaturaLocadoraUrl)) {
                    throw new \Exception('Não foi possível salvar a assinatura da locadora com segurança.');
                }
            } elseif ($request->hasFile('assinatura_locadora_arquivo')) {
                $assinaturaLocadoraUrl = $this->salvarAssinaturaArquivo($request->file('assinatura_locadora_arquivo'), $idEmpresa);
                if (empty($assinaturaLocadoraUrl)) {
                    throw new \Exception('Não foi possível salvar a assinatura da locadora com segurança.');
                }
            }

            $tituloDocumento = trim((string) $request->input('titulo_documento', ''));
            $subtituloDocumento = trim((string) $request->input('subtitulo_documento', ''));

            if ($tipoModelo === 'orcamento') {
                $tituloDocumento = 'Orçamento';
                $subtituloDocumento = 'Orçamento de Locação';
            }

            $dadosModelo = [
                'id_empresa' => $idEmpresa,
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'conteudo_html' => (string) $request->input('clausulas_html', ''),
                'titulo_documento' => $tituloDocumento !== '' ? $tituloDocumento : null,
                'subtitulo_documento' => $subtituloDocumento,
                'cor_borda' => $tipoModelo === 'orcamento' ? '#2f4858' : $request->cor_borda,
                'exibir_cabecalho' => $tipoModelo === 'orcamento' ? true : (bool) $request->input('exibir_cabecalho', 0),
                'exibir_logo' => $tipoModelo === 'orcamento' ? true : (bool) $request->input('exibir_logo', 0),
                'exibir_assinatura_locadora' => $tipoModelo === 'orcamento' ? true : (bool) $request->input('exibir_assinatura_locadora', 0),
                'exibir_assinatura_cliente' => $tipoModelo === 'orcamento' ? true : (bool) $request->input('exibir_assinatura_cliente', 0),
                'assinatura_locadora_url' => $assinaturaLocadoraUrl,
                'colunas_tabela_produtos' => $tipoModelo === 'orcamento'
                    ? ['produto', 'quantidade', 'dias', 'valor_unitario', 'subtotal']
                    : $request->input('colunas_tabela_produtos', ['produto', 'quantidade', 'dias', 'valor_unitario', 'subtotal']),
                'ativo' => $request->ativo ?? true,
                'padrao' => $request->padrao ?? false,
            ];

            if ($this->hasColunaModeloContrato('tipo_modelo')) {
                $dadosModelo['tipo_modelo'] = $tipoModelo;
            }

            if ($this->hasColunaModeloContrato('usa_medicao')) {
                $dadosModelo['usa_medicao'] = $tipoModelo === 'medicao';
            }

            $modelo = LocacaoModeloContrato::create($dadosModelo);
            
            DB::commit();
            
            return redirect()->route('documentos.index')
                ->with('success', 'Modelo de contrato criado com sucesso!');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar modelo de contrato: ' . $e->getMessage());
            return back()->with('error', 'Erro ao criar modelo: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Visualizar modelo
     */
    public function show($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $modelo = LocacaoModeloContrato::where('id_modelo', $id)
            ->where('id_empresa', $idEmpresa)
            ->firstOrFail();
        
        return view('locacoes.modelos-contrato.show', compact('modelo'));
    }

    /**
     * Formulário de edição
     */
    public function edit($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $modelo = LocacaoModeloContrato::where('id_modelo', $id)
            ->where('id_empresa', $idEmpresa)
            ->firstOrFail();

        $this->normalizarAssinaturaLocadoraPublica($modelo);
        $this->normalizarAssinaturaLocadoraPreview($modelo);
        
        $variaveisDisponiveis = $this->getVariaveisDisponiveis();
        $conteudoPadrao = $this->getConteudoPadraoEstiloContrato();
        $colunasTabelaDisponiveis = $this->getColunasTabelaDisponiveis();
        $tipoModelo = $this->resolverTipoModeloDocumento($modelo);

        return view('locacoes.modelos-contrato.edit', compact('modelo', 'variaveisDisponiveis', 'conteudoPadrao', 'colunasTabelaDisponiveis', 'tipoModelo'));
    }

    /**
     * Atualizar modelo
     */
    public function update(Request $request, $id)
    {
        Log::info('ModeloContrato.update iniciado', [
            'id_modelo' => $id,
            'id_empresa' => session('id_empresa') ?? Auth::user()->id_empresa ?? null,
            'payload_keys' => array_keys($request->all()),
            'has_assinatura_base64' => $request->filled('assinatura_locadora_base64'),
            'has_assinatura_arquivo' => $request->hasFile('assinatura_locadora_arquivo'),
        ]);

        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:100',
            'descricao' => 'nullable|string',
            'clausulas_html' => 'nullable|string',
            'cabecalho_html' => 'nullable|string',
            'rodape_html' => 'nullable|string',
            'css_personalizado' => 'nullable|string',
            'titulo_documento' => 'nullable|string|max:120',
            'subtitulo_documento' => 'nullable|string|max:200',
            'cor_borda' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'colunas_tabela_produtos' => 'nullable|array',
            'colunas_tabela_produtos.*' => 'string|in:produto,quantidade,dias,valor_unitario,subtotal',
            'exibir_assinatura_locadora' => 'nullable|boolean',
            'exibir_assinatura_cliente' => 'nullable|boolean',
            'assinatura_locadora_base64' => 'nullable|string',
            'assinatura_locadora_arquivo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'tipo_modelo' => 'nullable|string|in:contrato,orcamento,medicao',
            'usa_medicao' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('ModeloContrato.update validation failed', [
                'id_modelo' => $id,
                'errors' => $validator->errors()->toArray(),
            ]);
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            
            $modelo = LocacaoModeloContrato::where('id_modelo', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();
            
            // Se marcou como padrão, desmarcar os outros
            if ($request->padrao && !$modelo->padrao) {
                $tipoModeloAtual = $this->normalizarTipoModeloDocumento($request->input('tipo_modelo', $this->resolverTipoModeloDocumento($modelo)));

                $queryPadrao = LocacaoModeloContrato::where('id_empresa', $idEmpresa)
                    ->where('id_modelo', '!=', $id);

                if ($this->hasColunaModeloContrato('tipo_modelo')) {
                    $queryPadrao->where('tipo_modelo', $tipoModeloAtual);
                } elseif ($this->hasColunaModeloContrato('usa_medicao')) {
                    if ($tipoModeloAtual === 'medicao') {
                        $queryPadrao->where('usa_medicao', true);
                    } else {
                        $queryPadrao->where(function ($sub) {
                            $sub->whereNull('usa_medicao')->orWhere('usa_medicao', false);
                        });
                    }
                }

                $queryPadrao->update(['padrao' => false]);
            }

            $assinaturaBase64 = $request->input('assinatura_locadora_base64');
            if (!empty($assinaturaBase64)) {
                $this->removerAssinaturaAntiga($modelo->assinatura_locadora_url ?? null);
                $modelo->assinatura_locadora_url = $this->salvarAssinaturaBase64($assinaturaBase64, $idEmpresa);
                if (empty($modelo->assinatura_locadora_url)) {
                    throw new \Exception('Não foi possível salvar a assinatura da locadora com segurança.');
                }
            } elseif ($request->hasFile('assinatura_locadora_arquivo')) {
                $this->removerAssinaturaAntiga($modelo->assinatura_locadora_url ?? null);
                $modelo->assinatura_locadora_url = $this->salvarAssinaturaArquivo($request->file('assinatura_locadora_arquivo'), $idEmpresa);
                if (empty($modelo->assinatura_locadora_url)) {
                    throw new \Exception('Não foi possível salvar a assinatura da locadora com segurança.');
                }
            }

            $tituloDocumento = trim((string) $request->input('titulo_documento', ''));
            $subtituloDocumento = trim((string) $request->input('subtitulo_documento', ''));
            $tipoModelo = $this->normalizarTipoModeloDocumento($request->input('tipo_modelo'));

            if ($tipoModelo === 'orcamento') {
                $tituloDocumento = 'Orçamento';
                $subtituloDocumento = 'Orçamento de Locação';
            }

            $dadosModeloUpdate = [
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'conteudo_html' => (string) $request->input('clausulas_html', ''),
                'titulo_documento' => $tituloDocumento !== '' ? $tituloDocumento : null,
                'subtitulo_documento' => $subtituloDocumento,
                'cor_borda' => $tipoModelo === 'orcamento' ? '#2f4858' : $request->cor_borda,
                'exibir_cabecalho' => $tipoModelo === 'orcamento' ? true : (bool) $request->input('exibir_cabecalho', 0),
                'exibir_logo' => $tipoModelo === 'orcamento' ? true : (bool) $request->input('exibir_logo', 0),
                'exibir_assinatura_locadora' => $tipoModelo === 'orcamento' ? true : (bool) $request->input('exibir_assinatura_locadora', 0),
                'exibir_assinatura_cliente' => $tipoModelo === 'orcamento' ? true : (bool) $request->input('exibir_assinatura_cliente', 0),
                'assinatura_locadora_url' => $modelo->assinatura_locadora_url,
                'colunas_tabela_produtos' => $tipoModelo === 'orcamento'
                    ? ['produto', 'quantidade', 'dias', 'valor_unitario', 'subtotal']
                    : $request->input('colunas_tabela_produtos', ['produto', 'quantidade', 'dias', 'valor_unitario', 'subtotal']),
                'ativo' => $request->ativo ?? true,
                'padrao' => $request->padrao ?? false,
            ];

            if ($this->hasColunaModeloContrato('tipo_modelo')) {
                $dadosModeloUpdate['tipo_modelo'] = $tipoModelo;
            }

            if ($this->hasColunaModeloContrato('usa_medicao')) {
                $dadosModeloUpdate['usa_medicao'] = $tipoModelo === 'medicao';
            }

            $modelo->update($dadosModeloUpdate);
            
            DB::commit();
            
            return redirect()->route('documentos.index')
                ->with('success', 'Modelo de contrato atualizado com sucesso!');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar modelo de contrato: ' . $e->getMessage());
            return back()->with('error', 'Erro ao atualizar modelo: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Excluir modelo
     */
    public function destroy($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            
            $modelo = LocacaoModeloContrato::where('id_modelo', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();
            
            // Não permite excluir se for o único modelo
            $total = LocacaoModeloContrato::where('id_empresa', $idEmpresa)->count();
            if ($total <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível excluir o único modelo de contrato.'
                ], 400);
            }
            
            $modelo->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Modelo excluído com sucesso.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir modelo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Definir modelo como padrão
     */
    public function definirPadrao($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $modeloAtual = LocacaoModeloContrato::where('id_modelo', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();

            $tipoModelo = $this->resolverTipoModeloDocumento($modeloAtual);
            
            // Desmarcar padrão apenas do mesmo tipo de documento
            $queryPadrao = LocacaoModeloContrato::where('id_empresa', $idEmpresa);

            if ($this->hasColunaModeloContrato('tipo_modelo')) {
                $queryPadrao->where('tipo_modelo', $tipoModelo);
            } elseif ($this->hasColunaModeloContrato('usa_medicao')) {
                if ($tipoModelo === 'medicao') {
                    $queryPadrao->where('usa_medicao', true);
                } else {
                    $queryPadrao->where(function ($sub) {
                        $sub->whereNull('usa_medicao')->orWhere('usa_medicao', false);
                    });
                }
            }

            $queryPadrao->update(['padrao' => false]);
            
            // Marcar o selecionado
            LocacaoModeloContrato::where('id_modelo', $id)
                ->where('id_empresa', $idEmpresa)
                ->update(['padrao' => true]);
            
            return response()->json([
                'success' => true,
                'message' => 'Modelo definido como padrão.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview do modelo com dados fictícios
     */
    public function preview($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $modelo = LocacaoModeloContrato::where('id_modelo', $id)
            ->where('id_empresa', $idEmpresa)
            ->firstOrFail();
        
        // Criar dados fictícios para preview
        // Processar template
        $html = $modelo->processarTemplatePreview();
        
        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Duplicar modelo
     */
    public function duplicar($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            
            $modelo = LocacaoModeloContrato::where('id_modelo', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();
            
            $novo = $modelo->replicate();
            $novo->nome = $modelo->nome . ' (Cópia)';
            $novo->padrao = false;
            $novo->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Modelo duplicado com sucesso.',
                'id' => $novo->id_modelo
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao duplicar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retornar lista de variáveis disponíveis para o template
     */
    private function getVariaveisDisponiveis()
    {
        return [
            'Empresa' => [
                '{{empresa_nome}}' => 'Nome/Razão Social',
                '{{empresa_cnpj}}' => 'CNPJ',
                '{{empresa_endereco}}' => 'Endereço completo',
                '{{empresa_telefone}}' => 'Telefone',
                '{{empresa_email}}' => 'E-mail',
                '{{titulo_documento}}' => 'Título principal do documento',
                '{{subtitulo_documento}}' => 'Subtítulo do documento',
            ],
            'Cliente' => [
                '{{cliente_nome}}' => 'Nome/Razão Social',
                '{{cliente_documento}}' => 'CPF/CNPJ',
                '{{cliente_endereco}}' => 'Endereço completo',
                '{{cliente_telefone}}' => 'Telefone',
                '{{cliente_email}}' => 'E-mail',
            ],
            'Locação' => [
                '{{numero_contrato}}' => 'Número do Contrato',
                '{{data_inicio}}' => 'Data de Início',
                '{{hora_saida}}' => 'Hora de Saída',
                '{{data_fim}}' => 'Data de Término',
                '{{hora_retorno}}' => 'Hora de Retorno',
                '{{total_dias}}' => 'Total de Dias',
                '{{local_entrega}}' => 'Local de Entrega',
                '{{observacoes}}' => 'Observações',
            ],
            'Valores' => [
                '{{subtotal_produtos}}' => 'Subtotal de Produtos',
                '{{subtotal_servicos}}' => 'Subtotal de Serviços',
                '{{desconto}}' => 'Valor do Desconto',
                '{{taxa_entrega}}' => 'Taxa de Entrega',
                '{{valor_total}}' => 'Valor Total',
            ],
            'Listas' => [
                '{{produtos_lista}}' => 'Tabela de Produtos',
            ],
            'Outros' => [
                '{{data_atual}}' => 'Data Atual',
                '{{data_extenso}}' => 'Data por Extenso',
                '{{cidade}}' => 'Cidade da Empresa',
                '{{cor_borda}}' => 'Cor de borda do tema',
            ],
        ];
    }

    private function getColunasTabelaDisponiveis(): array
    {
        return [
            'produto' => 'Produto',
            'quantidade' => 'Quantidade',
            'dias' => 'Qtd. de dias',
            'valor_unitario' => 'Valor unitário',
            'subtotal' => 'Subtotal',
        ];
    }

    private function getConteudoPadraoEstiloContrato(): string
    {
        return '<div class="contrato-wrapper">\n'
            . '  <div class="faixa-topo"></div>\n'
            . '  <div class="cabecalho-principal">\n'
            . '      <table class="cabecalho-table">\n'
            . '          <tr>\n'
            . '              <td style="width:72%;">\n'
            . '                  <div class="documento-titulo">{{titulo_documento}} <span class="titulo-dots">● ● ●</span></div>\n'
            . '                  <div class="documento-subtitulo">{{subtitulo_documento}}</div>\n'
            . '              </td>\n'
            . '              <td style="width:28%;" class="logo-area">{{logo_bloco}}</td>\n'
            . '          </tr>\n'
            . '      </table>\n'
            . '  </div>\n'
            . '</div>\n\n'
            . '<table class="partes-table">\n'
            . '  <tr>\n'
            . '      <td style="width:50%;">\n'
            . '          <div class="bloco-parte">\n'
            . '              <div class="bloco-titulo">Contratante</div>\n'
            . '              <div class="item-linha">Nome: {{cliente_nome}}</div>\n'
            . '              <div class="item-linha">CPF/CNPJ: {{cliente_documento}}</div>\n'
            . '              <div class="item-linha">Endereço: {{cliente_endereco}}</div>\n'
            . '              <div class="item-linha">E mail: {{cliente_email}}</div>\n'
            . '          </div>\n'
            . '      </td>\n'
            . '      <td style="width:50%;">\n'
            . '          <div class="bloco-parte">\n'
            . '              <div class="bloco-titulo">Contratado</div>\n'
            . '              <div class="item-linha">Nome: {{empresa_nome}}</div>\n'
            . '              <div class="item-linha">CPF/CNPJ: {{empresa_cnpj}}</div>\n'
            . '              <div class="item-linha">Endereço: {{empresa_endereco}}</div>\n'
            . '              <div class="item-linha">E mail: {{empresa_email}}</div>\n'
            . '          </div>\n'
            . '      </td>\n'
            . '  </tr>\n'
            . '</table>\n\n'
            . '<div class="bloco">\n'
            . '  <div class="bloco-titulo">Tabela de Produtos</div>\n'
            . '  {{produtos_lista}}\n'
            . '</div>\n\n'
            . '<div class="clausulas-titulo">Cláusulas</div>\n'
            . '<div class="clausulas-texto">\n'
            . '  <p>Cláusula 1ª: O objeto deste contrato refere-se à locação dos itens relacionados, pelo período de {{total_dias}} dia(s).</p>\n'
            . '  <p>Cláusula 2ª: O início da locação ocorrerá em {{data_inicio}} às {{hora_saida}}, com término em {{data_fim}} às {{hora_retorno}}.</p>\n'
            . '  <p>Cláusula 3ª: Valor total pactuado: R$ {{valor_total}}.</p>\n'
            . '</div>';
    }

    private function salvarAssinaturaBase64(string $base64, int $idEmpresa): ?string
    {
        if (!preg_match('/^data:image\/(png|jpe?g);base64,/', $base64)) {
            return null;
        }

        preg_match('/^data:image\/(png|jpe?g);base64,/', $base64, $matches);
        $mimeExt = strtolower((string) ($matches[1] ?? 'png'));
        $mimeType = in_array($mimeExt, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
        $extensao = $mimeType === 'image/jpeg' ? 'jpg' : 'png';

        $base64 = preg_replace('/^data:image\/(png|jpe?g);base64,/', '', $base64);
        $conteudo = base64_decode($base64, true);
        if ($conteudo === false) {
            return null;
        }

        $nomeArquivo = 'assinatura_locadora_' . $idEmpresa . '_' . Str::random(8) . '.' . $extensao;

        $urlApi = $this->enviarAssinaturaParaApi($conteudo, $nomeArquivo, $idEmpresa, $mimeType);
        if (!empty($urlApi)) {
            return $urlApi;
        }

        Log::warning('Upload da assinatura bloqueado no fallback local para evitar exposição pública insegura.', [
            'id_empresa' => $idEmpresa,
            'arquivo' => $nomeArquivo,
        ]);

        return null; // Segurança: evita salvar assinatura privada em diretório público.
    }

    private function salvarAssinaturaArquivo($arquivo, int $idEmpresa): ?string
    {
        if (!$arquivo) {
            return null;
        }

        $ext = $arquivo->getClientOriginalExtension();
        $nomeArquivo = 'assinatura_locadora_' . $idEmpresa . '_' . Str::random(8) . '.' . $ext;

        try {
            $conteudo = file_get_contents($arquivo->getRealPath());
            $mimeType = (string) ($arquivo->getMimeType() ?: 'application/octet-stream');

            if ($conteudo !== false) {
                $urlApi = $this->enviarAssinaturaParaApi($conteudo, $nomeArquivo, $idEmpresa, $mimeType);
                if (!empty($urlApi)) {
                    return $urlApi;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao preparar assinatura para upload na API (fallback local desativado por segurança).', [
                'id_empresa' => $idEmpresa,
                'erro' => $e->getMessage(),
            ]);
        }

        Log::warning('Upload da assinatura bloqueado no fallback local para evitar exposição pública insegura.', [
            'id_empresa' => $idEmpresa,
            'arquivo' => $nomeArquivo,
        ]);

        return null; // Segurança: evita salvar assinatura privada em diretório público.
    }

    private function removerAssinaturaAntiga(?string $assinaturaUrl): void
    {
        if (!$assinaturaUrl) {
            return;
        }

        try {
            $pathApi = parse_url($assinaturaUrl, PHP_URL_PATH);
            if (is_string($pathApi) && str_contains($pathApi, '/uploads/assinaturas/')) {
                $baseUrl = $this->getApiBaseUrl();
                if ($baseUrl !== '') {
                    $endpointDelete = rtrim($baseUrl, '/') . '/api/assinaturas/delete-by-url';
                    Http::timeout(15)->asJson()->post($endpointDelete, [
                        'url' => $assinaturaUrl,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao tentar remover assinatura na API.', [
                'url' => $assinaturaUrl,
                'erro' => $e->getMessage(),
            ]);
        }

        $path = parse_url($assinaturaUrl, PHP_URL_PATH);
        $path = $path ? ltrim($path, '/') : null;

        if ($path) {
            $publicPath = public_path($path);
            if (File::exists($publicPath)) {
                File::delete($publicPath);
            }

            if (str_starts_with($path, 'storage/')) {
                $oldPath = str_replace('storage/', 'public/', $path);
                Storage::delete($oldPath);
            }
        }
    }

    private function montarUrlAssinaturaApi(int $idEmpresa, string $nomeArquivo): string
    {
        $baseUrl = $this->getApiBaseUrl();

        return $baseUrl . '/uploads/assinaturas/' . $idEmpresa . '/' . $nomeArquivo;
    }

    private function enviarAssinaturaParaApi(string $conteudo, string $nomeArquivo, int $idEmpresa, string $mimeType): ?string
    {
        $baseUrl = $this->getApiBaseUrl();
        if ($baseUrl === '') {
            return null;
        }

        $endpoint = rtrim($baseUrl, '/') . '/api/assinaturas';

        try {
            $response = Http::timeout(25)
                ->attach('file', $conteudo, $nomeArquivo, ['Content-Type' => $mimeType])
                ->post($endpoint, [
                    'idEmpresa' => $idEmpresa,
                    'nomeImagemAssinatura' => pathinfo($nomeArquivo, PATHINFO_FILENAME),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $url = data_get($data, 'data.file.url')
                    ?? data_get($data, 'data.url')
                    ?? data_get($data, 'url');

                if (is_string($url) && $url !== '') {
                    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                        return $url;
                    }

                    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
                }

                return $this->montarUrlAssinaturaApi($idEmpresa, $nomeArquivo);
            }

            Log::warning('Upload de assinatura na API retornou falha.', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Erro no upload da assinatura para API.', [
                'endpoint' => $endpoint,
                'erro' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getApiBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com')), '/');
        return str_replace(['api.gestornow.comn', 'api.gestornow.comN'], 'api.gestornow.com', $baseUrl);
    }

    private function normalizarAssinaturaLocadoraPublica(LocacaoModeloContrato $modelo): void
    {
        $url = $modelo->assinatura_locadora_url ?? null;
        if (!$url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || !str_starts_with($path, '/storage/assinaturas-contrato/')) {
            return;
        }

        $nomeArquivo = basename($path);
        if (!$nomeArquivo) {
            return;
        }

        $origem = storage_path('app/public/assinaturas-contrato/' . $nomeArquivo);
        if (!File::exists($origem)) {
            return;
        }

        $diretorioPublico = public_path('assets/assinaturas-contrato');
        if (!File::exists($diretorioPublico)) {
            File::makeDirectory($diretorioPublico, 0755, true);
        }

        $destino = $diretorioPublico . DIRECTORY_SEPARATOR . $nomeArquivo;
        if (!File::exists($destino)) {
            File::copy($origem, $destino);
        }

        $modelo->assinatura_locadora_url = asset('assets/assinaturas-contrato/' . $nomeArquivo);
        $modelo->save();
    }

    private function normalizarAssinaturaLocadoraPreview(LocacaoModeloContrato $modelo): void
    {
        $url = trim((string) ($modelo->assinatura_locadora_url ?? ''));
        if ($url === '') {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return;
        }

        $path = '/' . ltrim($path, '/');

        // Se o arquivo existir localmente, preferir URL local para o preview do formulário.
        // Isso evita imagem quebrada quando a URL da API não está acessível no ambiente local.
        if (str_starts_with($path, '/uploads/assinaturas/')) {
            $arquivoLocal = public_path(ltrim($path, '/'));
            if (File::exists($arquivoLocal)) {
                $modelo->assinatura_locadora_url = asset(ltrim($path, '/'));
            }
        }
    }

    private function normalizarTipoModeloDocumento(?string $tipo): string
    {
        $tipoNormalizado = strtolower(trim((string) $tipo));

        return in_array($tipoNormalizado, ['contrato', 'orcamento', 'medicao'], true)
            ? $tipoNormalizado
            : 'contrato';
    }

    private function resolverTipoModeloDocumento(LocacaoModeloContrato $modelo): string
    {
        if ($this->hasColunaModeloContrato('tipo_modelo')) {
            return $this->normalizarTipoModeloDocumento((string) ($modelo->tipo_modelo ?? 'contrato'));
        }

        return (bool) ($modelo->usa_medicao ?? false) ? 'medicao' : 'contrato';
    }

    /**
     * Dados fictícios para preview
     */
    private function hasColunaModeloContrato(string $coluna): bool
    {
        static $colunas = null;

        if ($colunas === null) {
            $colunas = Schema::hasTable('locacao_modelos_contrato')
                ? Schema::getColumnListing('locacao_modelos_contrato')
                : [];
        }

        return in_array($coluna, $colunas, true);
    }

    private function getDadosFicticios()
    {
        return [
            'empresa_nome' => 'Empresa Exemplo LTDA',
            'empresa_cnpj' => '00.000.000/0001-00',
            'empresa_endereco' => 'Rua Exemplo, 123 - Centro, Cidade - UF',
            'empresa_telefone' => '(00) 0000-0000',
            'empresa_email' => 'contato@empresa.com',
            'logo_url' => asset('assets/img/logo-placeholder.png'),
            'cliente_nome' => 'Cliente de Exemplo',
            'cliente_documento' => '000.000.000-00',
            'cliente_endereco' => 'Av. Cliente, 456 - Bairro, Cidade - UF',
            'cliente_telefone' => '(00) 99999-9999',
            'cliente_email' => 'cliente@email.com',
            'numero_contrato' => 'LOC-000001-2026',
            'data_inicio' => date('d/m/Y'),
            'hora_saida' => '08:00',
            'data_fim' => date('d/m/Y', strtotime('+3 days')),
            'hora_retorno' => '18:00',
            'total_dias' => '4',
            'local_entrega' => 'Rua de Entrega, 789 - Bairro, Cidade',
            'tipo_locacao' => 'Locação',
            'observacoes' => 'Observações de exemplo para o contrato.',
            'subtotal_produtos' => 'R$ 1.500,00',
            'subtotal_servicos' => 'R$ 300,00',
            'desconto' => 'R$ 100,00',
            'taxa_entrega' => 'R$ 50,00',
            'valor_total' => 'R$ 1.750,00',
            'produtos_lista' => '<tr><td>Produto Exemplo 1</td><td>2</td><td>R$ 250,00</td><td>R$ 500,00</td></tr>
                                  <tr><td>Produto Exemplo 2</td><td>1</td><td>R$ 1.000,00</td><td>R$ 1.000,00</td></tr>',
            'servicos_lista' => '<tr><td>Serviço de Montagem</td><td>1</td><td>R$ 300,00</td><td>R$ 300,00</td></tr>',
            'data_atual' => date('d/m/Y'),
            'data_extenso' => date('d') . ' de ' . strftime('%B') . ' de ' . date('Y'),
            'cidade' => 'São Paulo',
        ];
    }
}
