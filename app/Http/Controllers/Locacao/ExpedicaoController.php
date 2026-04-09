<?php

namespace App\Http\Controllers\Locacao;

use App\ActivityLog\ActionLogger;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Locacao\Models\Locacao;
use App\Domain\Locacao\Models\LocacaoChecklist;
use App\Domain\Locacao\Models\LocacaoChecklistFoto;
use App\Facades\Perm;
use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpedicaoController extends Controller
{
    private ?array $ultimoErroUploadChecklist = null;

    public function index()
    {
        abort_unless(Perm::pode(auth()->user(), 'expedicao.logistica.visualizar'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()?->id_empresa;

        $locacoes = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->whereNotIn('status', ['orcamento', 'cancelado', 'cancelada'])
            ->with([
                'cliente:id_clientes,nome',
                'produtos:id_produto_locacao,id_locacao,id_produto,quantidade',
                'produtos.produto:id_produto,nome',
            ])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $cards = $locacoes->map(function (Locacao $locacao) {
            $statusLogistica = $this->resolverStatusLogistica($locacao);
            $itensResumo = $locacao->produtos
                ->filter(fn ($item) => $item->produto)
                ->take(3)
                ->map(function ($item) {
                    $qtd = (int) ($item->quantidade ?? 1);
                    return $qtd . 'x ' . ($item->produto->nome ?? 'Item');
                })
                ->implode(', ');

            $totalItens = $locacao->produtos->count();
            if ($totalItens > 3) {
                $itensResumo .= ' +' . ($totalItens - 3) . ' item(ns)';
            }

            $urgencia = $this->resolverUrgencia($locacao);
            $endereco = trim((string) ($locacao->endereco_entrega ?: $locacao->local_entrega ?: $locacao->local_evento ?: ''));
            $cidadeEstado = trim((string) (($locacao->cidade ?? '') . ' ' . ($locacao->estado ?? '')));
            $enderecoCompleto = trim($endereco . ' ' . $cidadeEstado);

            return [
                'id_locacao' => $locacao->id_locacao,
                'numero_contrato' => $locacao->numero_contrato,
                'cliente' => $locacao->cliente->nome ?? 'Cliente não informado',
                'status_logistica' => $statusLogistica,
                'itens_resumo' => $itensResumo ?: 'Sem itens vinculados',
                'endereco' => $enderecoCompleto ?: 'Endereço não informado',
                'map_url' => $enderecoCompleto
                    ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($enderecoCompleto)
                    : null,
                'urgencia' => $urgencia,
            ];
        })->values();

        return view('locacoes.expedicao', [
            'cards' => $cards,
            'colunasKanban' => Locacao::statusLogisticaList(),
        ]);
    }

    public function moverCard(Request $request, int $idLocacao)
    {
        abort_unless(Perm::pode(auth()->user(), 'expedicao.logistica.mover-card'), 403);

        $dados = $request->validate([
            'status_logistica' => ['required', 'string', 'in:para_separar,pronto_patio,em_rota,entregue,aguardando_coleta'],
        ]);

        if (!$this->temColunaStatusLogistica()) {
            return response()->json([
                'ok' => false,
                'message' => 'A coluna status_logistica não existe. Execute as migrations antes de usar o Kanban.',
            ], 422);
        }

        $idEmpresa = session('id_empresa') ?? Auth::user()?->id_empresa;

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->firstOrFail();

        $locacao->update([
            'status_logistica' => $dados['status_logistica'],
        ]);
        $locacao->refresh();
        ActionLogger::log($locacao, 'status_logistica');

        return response()->json([
            'ok' => true,
            'message' => 'Status logístico atualizado com sucesso.',
        ]);
    }

    public function checklistDados(int $idLocacao)
    {
        abort_unless(Perm::pode(auth()->user(), 'expedicao.logistica.checklist'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()?->id_empresa;
        $temCodigoPatrimonio = Schema::hasColumn('patrimonios', 'codigo_patrimonio');

        $colunasPatrimonio = ['id_patrimonio', 'numero_serie'];
        if ($temCodigoPatrimonio) {
            $colunasPatrimonio[] = 'codigo_patrimonio';
        }

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->with([
                'cliente:id_clientes,nome',
                'produtos:id_produto_locacao,id_locacao,id_produto,id_patrimonio,quantidade,observacoes,voltou_com_defeito,quantidade_com_defeito,observacao_defeito',
                'produtos.produto:id_produto,nome',
                'produtos.patrimonio' => function ($query) use ($colunasPatrimonio) {
                    $query->select($colunasPatrimonio);
                },
            ])
            ->firstOrFail();

        $fotos = LocacaoChecklistFoto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (LocacaoChecklistFoto $foto) => $foto->id_produto_locacao . ':' . $foto->tipo);

        $checklists = LocacaoChecklist::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->whereIn('tipo', ['saida', 'entrada'])
            ->orderByDesc('id_locacao_checklist')
            ->get()
            ->groupBy('tipo');

        $itens = $locacao->produtos->map(function ($item) use ($fotos) {
            $saida = ($fotos[$item->id_produto_locacao . ':saida'] ?? collect())->values();
            $entrada = ($fotos[$item->id_produto_locacao . ':entrada'] ?? collect())->values();

            return [
                'id_produto_locacao' => $item->id_produto_locacao,
                'nome' => $item->produto->nome ?? 'Item sem nome',
                'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? null,
                'quantidade' => (int) ($item->quantidade ?? 1),
                'voltou_com_defeito' => (bool) ($item->voltou_com_defeito ?? false),
                'quantidade_com_defeito' => (int) ($item->quantidade_com_defeito ?? 0),
                'observacao_defeito' => $item->observacao_defeito,
                'saida_fotos' => $saida->map(fn (LocacaoChecklistFoto $foto) => $this->fotoPayload($foto))->all(),
                'entrada_fotos' => $entrada->map(fn (LocacaoChecklistFoto $foto) => $this->fotoPayload($foto))->all(),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'locacao' => [
                'id_locacao' => $locacao->id_locacao,
                'numero_contrato' => $locacao->numero_contrato,
                'cliente' => $locacao->cliente->nome ?? 'Cliente não informado',
            ],
            'itens' => $itens,
            'checklists' => [
                'saida' => $this->checklistPayload($checklists->get('saida')?->first()),
                'entrada' => $this->checklistPayload($checklists->get('entrada')?->first()),
            ],
        ]);
    }

    public function imprimirChecklist(Request $request, int $idLocacao)
    {
        abort_unless(Perm::pode(auth()->user(), 'expedicao.logistica.checklist'), 403);

        $dados = $request->validate([
            'tipo' => ['nullable', 'string', 'in:saida,entrada'],
        ]);

        $tipo = (string) ($dados['tipo'] ?? 'saida');
        $idEmpresa = session('id_empresa') ?? Auth::user()?->id_empresa;

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->with([
                'cliente:id_clientes,nome',
                'produtos:id_produto_locacao,id_locacao,id_produto,quantidade',
                'produtos.produto:id_produto,nome',
            ])
            ->firstOrFail();

        $checklist = LocacaoChecklist::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('tipo', $tipo)
            ->latest('id_locacao_checklist')
            ->first();

        $fotosSaida = LocacaoChecklistFoto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('tipo', 'saida')
            ->orderBy('created_at')
            ->get()
            ->groupBy('id_produto_locacao')
            ->map(fn ($fotos) => $fotos->map(function ($foto) use ($idEmpresa) {
                $urlNormalizada = $this->normalizarUrlChecklistFoto($foto->url_foto, (int) $idEmpresa);
                return [
                    'url_foto' => $urlNormalizada,
                    'src_pdf' => $this->resolverImagemParaPdf($urlNormalizada),
                    'capturado_em' => optional($foto->capturado_em)->format('d/m H:i'),
                ];
            })->values());

        $fotosEntrada = LocacaoChecklistFoto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('tipo', 'entrada')
            ->orderBy('created_at')
            ->get()
            ->groupBy('id_produto_locacao')
            ->map(fn ($fotos) => $fotos->map(function ($foto) use ($idEmpresa) {
                $urlNormalizada = $this->normalizarUrlChecklistFoto($foto->url_foto, (int) $idEmpresa);
                return [
                    'url_foto' => $urlNormalizada,
                    'src_pdf' => $this->resolverImagemParaPdf($urlNormalizada),
                    'capturado_em' => optional($foto->capturado_em)->format('d/m H:i'),
                ];
            })->values());

        $empresa = Empresa::query()
            ->where('id_empresa', $idEmpresa)
            ->first();

        $operadorNome = null;
        if (!empty($checklist?->assinado_por)) {
            $operadorNome = User::query()
                ->where('id_usuario', $checklist->assinado_por)
                ->value('nome');
        }

        $assinaturaOperadorPdfSrc = $this->resolverImagemParaPdf((string) ($checklist->assinatura_base64 ?? ''));
        $logoEmpresaPdfSrc = $this->resolverLogoEmpresaParaPdfExpedicao($empresa);

        $pdf = Pdf::loadView('locacoes.expedicao-checklist-impressao', [
            'tipo' => $tipo,
            'locacao' => $locacao,
            'checklist' => $checklist,
            'fotosSaida' => $fotosSaida,
            'fotosEntrada' => $fotosEntrada,
            'empresa' => $empresa,
            'operadorNome' => $operadorNome,
            'corPrimariaDocumento' => '#1f97ea',
            'assinaturaOperadorPdfSrc' => $assinaturaOperadorPdfSrc,
            'logoEmpresaPdfSrc' => $logoEmpresaPdfSrc,
        ])->setPaper('a4', 'portrait');

        $nomeArquivo = 'checklist-' . $tipo . '-contrato-' . ($locacao->numero_contrato ?: $locacao->id_locacao) . '.pdf';

        return $pdf->stream($nomeArquivo);
    }

    public function uploadFotoChecklist(Request $request, int $idLocacao)
    {
        abort_unless(Perm::pode(auth()->user(), 'expedicao.logistica.checklist.foto'), 403);

        $arquivoFoto = $request->file('foto');
        if ($arquivoFoto && !$arquivoFoto->isValid()) {
            $codigoErroUpload = (int) $arquivoFoto->getError();
            $mensagemErroUpload = $this->mensagemErroUploadArquivo($codigoErroUpload);

            Log::warning('Falha de upload da foto do checklist antes da validação.', [
                'id_locacao' => $idLocacao,
                'upload_error_code' => $codigoErroUpload,
                'upload_error_message' => $arquivoFoto->getErrorMessage(),
                'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                'php_post_max_size' => ini_get('post_max_size'),
                'php_upload_tmp_dir' => ini_get('upload_tmp_dir'),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $mensagemErroUpload,
                'errors' => [
                    'foto' => [$mensagemErroUpload],
                ],
                'upload_error_code' => $codigoErroUpload,
            ], 422);
        }

        $dados = $request->validate(
            [
                'tipo' => ['required', 'string', 'in:saida,entrada'],
                'id_produto_locacao' => ['required', 'integer'],
                'foto' => [
                    'required',
                    'file',
                    'mimes:jpg,jpeg,png,webp,heic,heif',
                    'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif',
                    'max:20480',
                ],
                'voltou_com_defeito' => ['nullable', 'boolean'],
                'observacao' => ['nullable', 'string', 'max:1000'],
            ],
            [
                'foto.required' => 'Selecione ou capture uma foto antes de enviar.',
                'foto.file' => 'Arquivo de foto inválido.',
                'foto.uploaded' => 'Falha no envio da foto. Verifique sua conexão e tente novamente.',
                'foto.mimes' => 'Formato de imagem inválido. Use JPG, PNG, WEBP ou HEIC.',
                'foto.mimetypes' => 'Tipo de imagem inválido para upload.',
                'foto.max' => 'A foto excede o limite de 20MB.',
            ]
        );

        $idEmpresa = session('id_empresa') ?? Auth::user()?->id_empresa;

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->firstOrFail();

        $itemLocacao = $locacao->produtos()
            ->where('id_produto_locacao', $dados['id_produto_locacao'])
            ->firstOrFail();

        $checklist = $this->obterOuCriarChecklist($locacao, $dados['tipo']);

        $textoWatermark = sprintf(
            'Contrato #%s | %s | %s',
            (string) ($locacao->numero_contrato ?: $locacao->id_locacao),
            Carbon::now()->format('d/m/Y H:i'),
            strtoupper($dados['tipo'])
        );

        $urlFoto = $this->salvarFotoComWatermark(
            arquivo: $request->file('foto'),
            idEmpresa: (int) $idEmpresa,
            idLocacao: (int) $locacao->id_locacao,
            idProduto: (int) ($itemLocacao->id_produto ?? 0),
            tipoChecklist: (string) $dados['tipo'],
            textoWatermark: $textoWatermark
        );

        $voltouComDefeito = (bool) ($dados['voltou_com_defeito'] ?? false);
        $alertaAvaria = $dados['tipo'] === 'entrada' && $voltouComDefeito;

        $foto = LocacaoChecklistFoto::create([
            'id_locacao_checklist' => $checklist->id_locacao_checklist,
            'id_empresa' => $idEmpresa,
            'id_locacao' => $locacao->id_locacao,
            'id_produto_locacao' => $itemLocacao->id_produto_locacao,
            'tipo' => $dados['tipo'],
            'url_foto' => $urlFoto,
            'texto_watermark' => $textoWatermark,
            'voltou_com_defeito' => $voltouComDefeito,
            'alerta_avaria' => $alertaAvaria,
            'observacao' => $dados['observacao'] ?? null,
            'capturado_em' => now(),
        ]);

        if ($alertaAvaria) {
            $checklist->update([
                'possui_avaria' => true,
            ]);
        }

        return response()->json([
            'ok' => true,
            'foto' => $this->fotoPayload($foto),
            'alerta_avaria' => $alertaAvaria,
        ]);
    }

    public function removerFotoChecklist(int $idLocacao, int $idFoto)
    {
        abort_unless(Perm::pode(auth()->user(), 'expedicao.logistica.checklist.foto'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()?->id_empresa;

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->firstOrFail();

        $foto = LocacaoChecklistFoto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('id_locacao_checklist_foto', $idFoto)
            ->firstOrFail();

        $this->deletarArquivoChecklistApi($foto->url_foto, (int) $idEmpresa);

        $foto->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Foto removida com sucesso.',
        ]);
    }

    public function confirmarChecklist(Request $request, int $idLocacao)
    {
        abort_unless(Perm::pode(auth()->user(), 'expedicao.logistica.checklist.confirmar'), 403);

        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:saida,entrada'],
            'assinatura_base64' => ['required', 'string'],
            'observacoes_gerais' => ['nullable', 'string', 'max:2000'],
            'itens_avaria' => ['nullable', 'array'],
            'itens_avaria.*.id_produto_locacao' => ['required_with:itens_avaria', 'integer'],
            'itens_avaria.*.quantidade_defeito' => ['nullable', 'integer', 'min:1'],
            'itens_avaria.*.observacao' => ['nullable', 'string', 'max:500'],
        ]);

        $idEmpresa = session('id_empresa') ?? Auth::user()?->id_empresa;

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->firstOrFail();

        $checklist = LocacaoChecklist::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('tipo', $dados['tipo'])
            ->where('status', 'aberto')
            ->latest('id_locacao_checklist')
            ->first();

        if (!$checklist) {
            $checklist = $this->obterOuCriarChecklist($locacao, $dados['tipo']);
        }

        $assinaturaBase64 = trim((string) $dados['assinatura_base64']);
        if (!Str::startsWith($assinaturaBase64, 'data:image/')) {
            return response()->json([
                'ok' => false,
                'message' => 'Assinatura inválida.',
            ], 422);
        }

        $conteudoAssinatura = $this->decodeDataUrl($assinaturaBase64);
        if ($conteudoAssinatura === null) {
            throw ValidationException::withMessages([
                'assinatura_base64' => 'Assinatura inválida (base64). Tente assinar novamente.',
            ]);
        }

        $idUsuario = (int) (Auth::user()?->id_usuario ?? 0);
        if ($idUsuario <= 0) {
            throw ValidationException::withMessages([
                'assinatura_base64' => 'Usuário inválido para envio de assinatura na API de arquivos.',
            ]);
        }

        $nomeArquivoAssinatura = 'checklist-assinatura-' . $locacao->id_locacao . '-' . $dados['tipo'] . '-' . now()->format('YmdHis') . '.png';
        $urlAssinatura = $this->uploadImagemChecklistApi(
            conteudo: $conteudoAssinatura,
            nomeArquivo: $nomeArquivoAssinatura,
            idEmpresa: (int) $idEmpresa,
            idLocacao: (int) $locacao->id_locacao,
            tipo: 'assinatura',
            mimeType: 'image/png',
            extras: [
                'idUsuario' => $idUsuario,
                'nomeImagemChecklist' => 'assinatura_checklist_' . pathinfo($nomeArquivoAssinatura, PATHINFO_FILENAME),
            ]
        );

        if (!is_string($urlAssinatura) || $urlAssinatura === '' || !str_contains($urlAssinatura, '/uploads/checklist/imagens/assinatura/')) {
            $detalhesErro = $this->detalhesUltimoErroUploadChecklist();
            throw ValidationException::withMessages([
                'assinatura_base64' => 'Não foi possível enviar a assinatura para API de arquivos.' . ($detalhesErro ? ' ' . $detalhesErro : ''),
            ]);
        }

        $assinaturaParaSalvar = $urlAssinatura;

        $itensAvaria = collect($dados['itens_avaria'] ?? [])
            ->filter(fn ($item) => !empty($item['id_produto_locacao']))
            ->values();

        if ($dados['tipo'] === 'entrada') {
            $colunasDefeitoDisponiveis = $this->temColunasDefeitoProdutoLocacao();

            if ($colunasDefeitoDisponiveis) {
                $locacao->produtos()
                    ->update([
                        'voltou_com_defeito' => false,
                        'quantidade_com_defeito' => null,
                        'observacao_defeito' => null,
                    ]);

                foreach ($itensAvaria as $itemAvaria) {
                    $idProdutoLocacao = (int) ($itemAvaria['id_produto_locacao'] ?? 0);
                    if ($idProdutoLocacao <= 0) {
                        continue;
                    }

                    $produtoLocacao = $locacao->produtos()
                        ->where('id_produto_locacao', $idProdutoLocacao)
                        ->first();

                    if (!$produtoLocacao) {
                        continue;
                    }

                    $quantidadeItem = max(1, (int) ($produtoLocacao->quantidade ?? 1));
                    $quantidadeDefeitoInformada = (int) ($itemAvaria['quantidade_defeito'] ?? 1);
                    $quantidadeDefeito = !empty($produtoLocacao->id_patrimonio)
                        ? 1
                        : max(1, min($quantidadeItem, $quantidadeDefeitoInformada));

                    $observacaoDefeito = trim((string) ($itemAvaria['observacao'] ?? ''));

                    $produtoLocacao->update([
                        'voltou_com_defeito' => true,
                        'quantidade_com_defeito' => $quantidadeDefeito,
                        'observacao_defeito' => $observacaoDefeito !== '' ? $observacaoDefeito : null,
                    ]);
                }
            }
        }

        if ($itensAvaria->isNotEmpty()) {
            $observacaoAvarias = $itensAvaria->map(function ($item) {
                $obs = trim((string) ($item['observacao'] ?? ''));
                $qtd = max(1, (int) ($item['quantidade_defeito'] ?? 1));
                return 'Item #' . $item['id_produto_locacao'] . ' (qtd defeito: ' . $qtd . ')' . ($obs ? ': ' . $obs : '');
            })->implode(' | ');

            $textoAtual = trim((string) ($dados['observacoes_gerais'] ?? ''));
            $dados['observacoes_gerais'] = trim($textoAtual . ($textoAtual ? "\n" : '') . 'Avarias: ' . $observacaoAvarias);
        }

        $checklist->update([
            'status' => 'concluido',
            'assinatura_base64' => $assinaturaParaSalvar,
            'assinado_por' => Auth::user()?->id_usuario,
            'assinado_em' => now(),
            'observacoes_gerais' => $dados['observacoes_gerais'] ?? null,
            'possui_avaria' => $checklist->possui_avaria || $itensAvaria->isNotEmpty(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Checklist confirmado com sucesso.',
            'alerta_avaria' => (bool) $checklist->possui_avaria || $itensAvaria->isNotEmpty(),
        ]);
    }

    private function obterOuCriarChecklist(Locacao $locacao, string $tipo): LocacaoChecklist
    {
        return LocacaoChecklist::query()
            ->firstOrCreate(
                [
                    'id_empresa' => $locacao->id_empresa,
                    'id_locacao' => $locacao->id_locacao,
                    'tipo' => $tipo,
                    'status' => 'aberto',
                ],
                [
                    'possui_avaria' => false,
                ]
            );
    }

    private function fotoPayload(LocacaoChecklistFoto $foto): array
    {
        return [
            'id' => $foto->id_locacao_checklist_foto,
            'url_foto' => $this->normalizarUrlChecklistFoto($foto->url_foto, (int) $foto->id_empresa),
            'tipo' => $foto->tipo,
            'capturado_em' => optional($foto->capturado_em)->format('d/m/Y H:i'),
            'observacao' => $foto->observacao,
            'voltou_com_defeito' => (bool) $foto->voltou_com_defeito,
            'alerta_avaria' => (bool) $foto->alerta_avaria,
        ];
    }

    private function checklistPayload(?LocacaoChecklist $checklist): ?array
    {
        if (!$checklist) {
            return null;
        }

        return [
            'id' => $checklist->id_locacao_checklist,
            'tipo' => $checklist->tipo,
            'status' => $checklist->status,
            'observacoes_gerais' => $checklist->observacoes_gerais,
            'assinatura_base64' => $checklist->assinatura_base64,
            'assinado_em' => optional($checklist->assinado_em)->format('d/m/Y H:i'),
            'possui_avaria' => (bool) $checklist->possui_avaria,
        ];
    }

    private function resolverStatusLogistica(Locacao $locacao): string
    {
        if ($this->temColunaStatusLogistica() && !empty($locacao->status_logistica)) {
            return (string) $locacao->status_logistica;
        }

        return match ((string) $locacao->status) {
            'encerrado' => 'aguardando_coleta',
            'em_andamento', 'retirada', 'atrasada' => 'em_rota',
            default => 'para_separar',
        };
    }

    private function resolverUrgencia(Locacao $locacao): array
    {
        if (!$locacao->data_inicio) {
            return ['label' => 'Sem data', 'class' => 'secondary'];
        }

        $inicio = Carbon::parse($locacao->data_inicio)->startOfDay();
        $hoje = Carbon::now()->startOfDay();

        if ($inicio->lt($hoje)) {
            return ['label' => 'Urgente', 'class' => 'danger'];
        }

        if ($inicio->equalTo($hoje)) {
            return ['label' => 'Hoje', 'class' => 'warning'];
        }

        return ['label' => $inicio->format('d/m'), 'class' => 'info'];
    }

    private function salvarFotoComWatermark($arquivo, int $idEmpresa, int $idLocacao, int $idProduto, string $tipoChecklist, string $textoWatermark): string
    {
        $nomeArquivo = 'checklist-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(8)) . '.jpg';

        $conteudoOriginal = @file_get_contents($arquivo->getRealPath());
        $imagem = $conteudoOriginal ? @imagecreatefromstring($conteudoOriginal) : false;

        $conteudoFinal = $conteudoOriginal ?: '';
        $mimeType = (string) ($arquivo->getMimeType() ?: 'image/jpeg');

        if ($imagem !== false) {
            $largura = imagesx($imagem);
            $altura = imagesy($imagem);

            $fundo = imagecolorallocatealpha($imagem, 0, 0, 0, 70);
            $branco = imagecolorallocate($imagem, 255, 255, 255);

            imagefilledrectangle($imagem, 0, $altura - 26, $largura, $altura, $fundo);
            imagestring($imagem, 3, 10, $altura - 20, $textoWatermark, $branco);

            ob_start();
            imagejpeg($imagem, null, 88);
            $conteudoFinal = (string) ob_get_clean();
            imagedestroy($imagem);
            $mimeType = 'image/jpeg';
        }

        $extrasUpload = [
            'nomeImagemChecklist' => 'checklist_' . $tipoChecklist . '_locacao_' . $idLocacao . '_' . pathinfo($nomeArquivo, PATHINFO_FILENAME),
        ];

        if ($idProduto > 0) {
            $extrasUpload['idProduto'] = $idProduto;
        }

        $urlApi = $this->uploadImagemChecklistApi(
            conteudo: $conteudoFinal,
            nomeArquivo: $nomeArquivo,
            idEmpresa: $idEmpresa,
            idLocacao: $idLocacao,
            tipo: 'foto_produto_' . $tipoChecklist,
            mimeType: $mimeType,
            extras: $extrasUpload
        );

        if (!is_string($urlApi) || $urlApi === '') {
            $detalhesErro = $this->detalhesUltimoErroUploadChecklist();
            throw ValidationException::withMessages([
                'foto' => 'Não foi possível enviar a foto para API de arquivos.' . ($detalhesErro ? ' ' . $detalhesErro : ''),
            ]);
        }

        return $urlApi;
    }

    private function uploadImagemProdutoApi(string $conteudo, string $nomeArquivo, int $idEmpresa, int $idProduto, string $tipoChecklist, int $idLocacao, string $mimeType): ?string
    {
        $baseUrl = $this->getApiFilesBaseUrl();
        if ($baseUrl === '' || $conteudo === '') {
            return null;
        }

        $endpoint = rtrim($baseUrl, '/') . '/api/produtos/imagens';
        $nomeImagemProduto = 'checklist_' . $tipoChecklist . '_locacao_' . $idLocacao . '_' . pathinfo($nomeArquivo, PATHINFO_FILENAME);

        try {
            $response = Http::timeout(30)
                ->attach('file', $conteudo, $nomeArquivo, ['Content-Type' => $mimeType])
                ->post($endpoint, [
                    'idEmpresa' => $idEmpresa,
                    'idProduto' => $idProduto,
                    'nomeImagemProduto' => $nomeImagemProduto,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $url = data_get($data, 'data.file.url')
                    ?? data_get($data, 'data.url')
                    ?? data_get($data, 'url');

                return $this->normalizarUrlApiFiles($url, $baseUrl);
            }

            Log::warning('Falha no upload de foto checklist para API de produtos.', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
                'id_empresa' => $idEmpresa,
                'id_produto' => $idProduto,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Exceção ao enviar foto checklist para API de produtos.', [
                'endpoint' => $endpoint,
                'erro' => $e->getMessage(),
                'id_empresa' => $idEmpresa,
                'id_produto' => $idProduto,
            ]);
        }

        return null;
    }

    private function uploadImagemChecklistApi(string $conteudo, string $nomeArquivo, int $idEmpresa, int $idLocacao, string $tipo, string $mimeType, array $extras = []): ?string
    {
        $this->ultimoErroUploadChecklist = null;

        $baseUrl = $this->getApiFilesBaseUrl();
        if ($baseUrl === '' || $conteudo === '') {
            $this->ultimoErroUploadChecklist = [
                'endpoint' => null,
                'status' => null,
                'body' => 'Base URL vazia ou conteúdo vazio.',
            ];
            return null;
        }

        $payloadChecklist = array_merge([
            'idEmpresa' => $idEmpresa,
            'idLocacao' => $idLocacao,
            'tipo' => $tipo,
            'nomeImagemChecklist' => pathinfo($nomeArquivo, PATHINFO_FILENAME),
        ], $extras);

        $endpointsChecklist = $this->getChecklistUploadEndpoints($baseUrl);
        $endpointProdutoApi = rtrim($baseUrl, '/') . '/api/produtos/imagens';

        $isFotoProduto = str_starts_with($tipo, 'foto_produto_');
        $idProduto = (int) ($extras['idProduto'] ?? 0);
        $permitirFallbackProduto = $this->permiteFallbackProdutoNoChecklist();

        $tentativas = collect($endpointsChecklist)
            ->map(fn (string $endpointChecklist) => [
                'endpoint' => $endpointChecklist,
                'payload' => $payloadChecklist,
                'origem' => 'checklist',
            ])
            ->values()
            ->all();

        if ($permitirFallbackProduto && $isFotoProduto && $idProduto > 0) {
            $tentativas[] = [
                'endpoint' => $endpointProdutoApi,
                'payload' => [
                    'idEmpresa' => $idEmpresa,
                    'idProduto' => $idProduto,
                    'nomeImagemProduto' => (string) ($extras['nomeImagemChecklist'] ?? ('checklist_' . pathinfo($nomeArquivo, PATHINFO_FILENAME))),
                ],
                'origem' => 'produto',
            ];
        }

        $detalhesTentativas = [];

        try {
            foreach ($tentativas as $tentativa) {
                $response = Http::timeout(30)
                    ->attach('file', $conteudo, $nomeArquivo, ['Content-Type' => $mimeType])
                    ->post($tentativa['endpoint'], $tentativa['payload']);

                if ($response->successful()) {
                    $data = $response->json();
                    $filename = data_get($data, 'data.file.filename')
                        ?? data_get($data, 'data.filename')
                        ?? data_get($data, 'filename');

                    $urlRetornada = data_get($data, 'data.file.url')
                        ?? data_get($data, 'data.url')
                        ?? data_get($data, 'url');

                    if ((!is_string($filename) || trim($filename) === '') && is_string($urlRetornada) && trim($urlRetornada) !== '') {
                        $pathUrl = parse_url($urlRetornada, PHP_URL_PATH);
                        $basename = $pathUrl ? basename($pathUrl) : basename($urlRetornada);
                        $filename = is_string($basename) && $basename !== '' ? $basename : null;
                    }

                    if (!is_string($filename) || trim($filename) === '') {
                        $filename = $nomeArquivo;
                    }

                    if ($tentativa['origem'] === 'produto') {
                        $urlNormalizada = $this->normalizarUrlApiFiles($urlRetornada, $baseUrl);
                        if (is_string($urlNormalizada) && $urlNormalizada !== '') {
                            return $urlNormalizada;
                        }

                        return rtrim($baseUrl, '/') . '/uploads/produtos/imagens/' . $idEmpresa . '/' . ltrim((string) $filename, '/');
                    }

                    $subPasta = $tipo === 'assinatura' ? '/assinatura' : '';
                    return rtrim($baseUrl, '/') . '/uploads/checklist/imagens' . $subPasta . '/' . $idEmpresa . '/' . ltrim((string) $filename, '/');
                }

                Log::warning('Falha no upload de checklist na API de arquivos.', [
                    'endpoint' => $tentativa['endpoint'],
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'id_empresa' => $idEmpresa,
                    'id_locacao' => $idLocacao,
                    'tipo' => $tipo,
                ]);

                $detalhesTentativas[] = [
                    'endpoint' => $tentativa['endpoint'],
                    'status' => $response->status(),
                    'body' => (string) $response->body(),
                ];
            }

            $this->ultimoErroUploadChecklist = [
                'endpoint' => $detalhesTentativas[0]['endpoint'] ?? null,
                'status' => $detalhesTentativas[0]['status'] ?? null,
                'body' => $detalhesTentativas[0]['body'] ?? null,
                'tentativas' => $detalhesTentativas,
                'fallback_produto_ativo' => $permitirFallbackProduto,
            ];
        } catch (\Throwable $e) {
            Log::warning('Exceção no upload de checklist na API de arquivos.', [
                'endpoint' => $tentativas[0]['endpoint'] ?? null,
                'erro' => $e->getMessage(),
                'id_empresa' => $idEmpresa,
                'id_locacao' => $idLocacao,
                'tipo' => $tipo,
            ]);

            $this->ultimoErroUploadChecklist = [
                'endpoint' => $tentativas[0]['endpoint'] ?? null,
                'status' => null,
                'body' => $e->getMessage(),
                'fallback_produto_ativo' => $permitirFallbackProduto,
            ];
        }

        return null;
    }

    private function normalizarUrlApiFiles($url, string $baseUrl): ?string
    {
        if (!is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $url = str_replace('/api/produtos/imagens/', '/uploads/produtos/imagens/', $url);
            return str_replace('/produtos/imagens/', '/uploads/produtos/imagens/', $url);
        }

        $normalizada = '/' . ltrim($url, '/');
        $normalizada = str_replace('/api/produtos/imagens/', '/uploads/produtos/imagens/', $normalizada);
        $normalizada = str_replace('/produtos/imagens/', '/uploads/produtos/imagens/', $normalizada);

        return rtrim($baseUrl, '/') . $normalizada;
    }

    private function normalizarUrlChecklistFoto($url, int $idEmpresa): ?string
    {
        $baseUrl = $this->getApiFilesBaseUrl();
        $normalizada = $this->normalizarUrlApiFiles($url, $baseUrl);

        if (is_string($normalizada) && trim($normalizada) !== '') {
            return $normalizada;
        }

        $valor = trim((string) $url);
        if ($valor === '') {
            return null;
        }

        if (str_contains($valor, '/checklist/imagens/') && !str_contains($valor, '/uploads/checklist/imagens/')) {
            $valor = str_replace('/checklist/imagens/', '/uploads/checklist/imagens/', $valor);
        }

        if (!str_contains($valor, '/')) {
            return rtrim($baseUrl, '/') . '/uploads/produtos/imagens/' . $idEmpresa . '/' . ltrim($valor, '/');
        }

        return $valor;
    }

    private function getChecklistUploadEndpoints(string $baseUrl): array
    {
        $custom = trim((string) config('custom.api_files_checklist_upload_endpoints', env('API_FILES_CHECKLIST_UPLOAD_ENDPOINTS', '')));
        $paths = [];

        if ($custom !== '') {
            $paths = array_filter(array_map('trim', explode(',', $custom)));
        }

        if (empty($paths)) {
            $paths = [
                '/uploads/checklist/imagens',
                '/api/uploads/checklist/imagens',
            ];
        }

        $endpoints = array_map(function (string $path) use ($baseUrl) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return rtrim($path, '/');
            }

            return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        }, $paths);

        return array_values(array_unique($endpoints));
    }

    private function permiteFallbackProdutoNoChecklist(): bool
    {
        return filter_var((string) config('custom.api_files_checklist_allow_produto_fallback', env('API_FILES_CHECKLIST_ALLOW_PRODUTO_FALLBACK', false)), FILTER_VALIDATE_BOOL);
    }

    private function decodeDataUrl(?string $dataUrl): ?string
    {
        $dataUrl = trim((string) $dataUrl);
        if ($dataUrl === '') {
            return null;
        }

        if (!preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', $dataUrl)) {
            return null;
        }

        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $decoded = base64_decode($base64, true);

        return $decoded !== false ? $decoded : null;
    }

    private function resolverImagemParaPdf(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'data:image/')) {
            return $url;
        }

        $url = str_replace(['https//', 'http//'], ['https://', 'http://'], $url);

        $arquivoLocal = $this->resolverArquivoLocalParaPdf($url, [
            'uploads/checklist/imagens',
            'uploads/checklists',
            'uploads/assinaturas',
            'assets/logos-empresa',
            'storage/logos-empresa',
        ]);

        if ($arquivoLocal !== null) {
            return $arquivoLocal;
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = rtrim($this->getApiFilesBaseUrl(), '/') . '/' . ltrim($url, '/');
        }

        try {
            $response = Http::timeout(20)->get($url);
            if ($response->successful()) {
                $mime = (string) ($response->header('Content-Type') ?: 'image/jpeg');
                return 'data:' . $mime . ';base64,' . base64_encode((string) $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao resolver imagem para PDF.', [
                'url' => $url,
                'erro' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function resolverLogoEmpresaParaPdfExpedicao($empresa): ?string
    {
        if (!$empresa) {
            return null;
        }

        $configuracoes = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $logoUrl = trim((string) ($configuracoes['logo_url'] ?? $empresa->logo_url ?? ''));

        return $this->resolverImagemParaPdf($logoUrl);
    }

    private function resolverArquivoLocalParaPdf(string $url, array $pathsConhecidos = []): ?string
    {
        $caminhosTentar = [];

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $caminhosTentar[] = public_path(ltrim($path, '/'));

            foreach ($pathsConhecidos as $pathConhecido) {
                if (strpos($path, $pathConhecido) !== false) {
                    $partes = explode($pathConhecido, $path);
                    if (count($partes) > 1) {
                        $nomeArquivo = ltrim(end($partes), '/');
                        $caminhosTentar[] = public_path($pathConhecido . '/' . $nomeArquivo);
                    }
                }
            }
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $caminhosTentar[] = public_path(ltrim($url, '/'));
            foreach ($pathsConhecidos as $pathConhecido) {
                $nomeArquivo = basename($url);
                $caminhosTentar[] = public_path($pathConhecido . '/' . $nomeArquivo);
            }
        }

        $caminhosTentar = array_unique($caminhosTentar);
        foreach ($caminhosTentar as $caminho) {
            if (File::exists($caminho)) {
                try {
                    $conteudo = File::get($caminho);
                    $mime = $this->detectarMimeType($caminho);
                    return 'data:' . $mime . ';base64,' . base64_encode($conteudo);
                } catch (\Throwable $e) {
                    Log::warning('Falha ao ler arquivo local para PDF.', [
                        'caminho' => $caminho,
                        'erro' => $e->getMessage(),
                    ]);
                }
            }
        }

        return null;
    }

    private function detectarMimeType(string $caminho): string
    {
        $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
        ];

        return $mimeTypes[$extensao] ?? 'image/jpeg';
    }

    private function temColunasDefeitoProdutoLocacao(): bool
    {
        return Schema::hasTable('produto_locacao')
            && Schema::hasColumn('produto_locacao', 'voltou_com_defeito')
            && Schema::hasColumn('produto_locacao', 'quantidade_com_defeito')
            && Schema::hasColumn('produto_locacao', 'observacao_defeito');
    }

    private function getApiFilesBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com')), '/');
        return str_replace(['api.gestornow.comn', 'api.gestornow.comN'], 'api.gestornow.com', $baseUrl);
    }

    private function detalhesUltimoErroUploadChecklist(): string
    {
        if (!$this->ultimoErroUploadChecklist) {
            return '';
        }

        $endpoint = (string) ($this->ultimoErroUploadChecklist['endpoint'] ?? 'N/A');
        $status = (string) ($this->ultimoErroUploadChecklist['status'] ?? 'N/A');
        $body = trim((string) ($this->ultimoErroUploadChecklist['body'] ?? ''));

        if (mb_strlen($body) > 700) {
            $body = mb_substr($body, 0, 700) . '...';
        }

        $detalhes = sprintf('Endpoint: %s | Status: %s | Resposta: %s', $endpoint, $status, $body ?: 'vazia');

        $tentativas = $this->ultimoErroUploadChecklist['tentativas'] ?? [];
        if (is_array($tentativas) && count($tentativas) > 1) {
            $partes = [];
            foreach ($tentativas as $tentativa) {
                $ep = (string) ($tentativa['endpoint'] ?? 'N/A');
                $st = (string) ($tentativa['status'] ?? 'N/A');
                $partes[] = $ep . ' (' . $st . ')';
            }

            $detalhes .= ' | Tentativas: ' . implode(' -> ', $partes);
        }

        $fallbackProdutoAtivo = (bool) ($this->ultimoErroUploadChecklist['fallback_produto_ativo'] ?? false);
        $detalhes .= ' | Fallback produto: ' . ($fallbackProdutoAtivo ? 'ativo' : 'inativo');

        return $detalhes;
    }

    private function mensagemErroUploadArquivo(int $codigoErro): string
    {
        return match ($codigoErro) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A foto excede o limite de upload do servidor. Comprima a imagem e tente novamente.',
            UPLOAD_ERR_PARTIAL => 'O upload da foto foi interrompido antes de concluir. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhuma foto foi recebida no upload. Selecione a imagem e tente novamente.',
            UPLOAD_ERR_NO_TMP_DIR => 'Servidor sem diretório temporário para upload. Contate o suporte técnico.',
            UPLOAD_ERR_CANT_WRITE => 'Servidor não conseguiu gravar o arquivo temporário. Contate o suporte técnico.',
            UPLOAD_ERR_EXTENSION => 'Uma extensão do servidor bloqueou o upload da foto. Contate o suporte técnico.',
            default => 'Falha no envio da foto. Verifique sua conexão e tente novamente.',
        };
    }

    private function deletarArquivoChecklistApi(?string $urlFoto, int $idEmpresa): void
    {
        $urlFoto = trim((string) $urlFoto);
        if ($urlFoto === '') {
            return;
        }

        $path = parse_url($urlFoto, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return;
        }

        $filename = basename($path);
        if ($filename === '' || $filename === '/' || $filename === '.') {
            return;
        }

        $baseUrl = $this->getApiFilesBaseUrl();
        $pathLower = strtolower($path);
        if (str_contains($pathLower, '/uploads/produtos/imagens/') || str_contains($pathLower, '/produtos/imagens/') || str_contains($pathLower, '/api/produtos/imagens/')) {
            $endpoints = [
                rtrim($baseUrl, '/') . '/uploads/produtos/imagens/' . $idEmpresa . '/' . $filename,
            ];
        } else {
            $sub = str_contains($pathLower, '/assinatura/') ? 'assinatura/' : '';
            $endpoints = [
                rtrim($baseUrl, '/') . '/uploads/checklist/imagens/' . $sub . $idEmpresa . '/' . $filename,
                rtrim($baseUrl, '/') . '/api/uploads/checklist/imagens/' . $sub . $idEmpresa . '/' . $filename,
            ];
        }

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(20)->delete($endpoint);
                if ($response->successful() || $response->status() === 404) {
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao remover arquivo checklist na API.', [
                    'endpoint' => $endpoint,
                    'erro' => $e->getMessage(),
                ]);
            }
        }
    }

    private function temColunaStatusLogistica(): bool
    {
        static $temColuna = null;

        if ($temColuna === null) {
            $temColuna = Schema::hasTable('locacao') && Schema::hasColumn('locacao', 'status_logistica');
        }

        return $temColuna;
    }
}
