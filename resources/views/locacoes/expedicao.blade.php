@extends('layouts.layoutMaster')

@section('title', 'Expedição e Logística')

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .kanban-wrap {
        --kanban-col-bg: #fff;
        --kanban-col-border: #e6e9ed;
        --kanban-col-header-border: #edf0f2;
        --kanban-card-bg: #fff;
        --kanban-card-border: #edf0f2;
        --kanban-text-muted: #6b7280;
        --foto-box-border: #d6dbe1;
        --foto-card-border: #e7ebef;
        --foto-legenda-border: #eef1f4;
        --assinatura-border: #d6dbe1;
        --assinatura-bg: #fff;

        display: grid;
        grid-template-columns: repeat(5, minmax(260px, 1fr));
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: .35rem;
    }

    html.dark-style .kanban-wrap {
        --kanban-col-bg: #2b3046;
        --kanban-col-border: #434a67;
        --kanban-col-header-border: #434a67;
        --kanban-card-bg: #303753;
        --kanban-card-border: #4a5273;
        --kanban-text-muted: #b9c2e0;
        --foto-box-border: #4a5273;
        --foto-card-border: #4a5273;
        --foto-legenda-border: #4a5273;
        --assinatura-border: #4a5273;
        --assinatura-bg: #303753;
    }

    .kanban-col {
        border: 1px solid var(--kanban-col-border);
        border-radius: .6rem;
        background: var(--kanban-col-bg);
        min-height: 70vh;
        display: flex;
        flex-direction: column;
    }

    .kanban-col-header {
        padding: .75rem .9rem;
        border-bottom: 1px solid var(--kanban-col-header-border);
        font-weight: 700;
        font-size: .86rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .kanban-list {
        padding: .75rem;
        display: flex;
        flex-direction: column;
        gap: .75rem;
        min-height: 140px;
    }

    .kanban-card {
        border: 1px solid var(--kanban-card-border);
        border-radius: .55rem;
        padding: .75rem;
        background: var(--kanban-card-bg);
        cursor: move;
    }

    .kanban-card .small.text-muted {
        color: var(--kanban-text-muted) !important;
    }

    .kanban-card h6 {
        font-size: .86rem;
        margin-bottom: .3rem;
    }

    .checklist-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .75rem;
    }

    .foto-box {
        border: 1px dashed var(--foto-box-border);
        border-radius: .45rem;
        padding: .4rem;
        min-height: 80px;
    }

    .foto-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: .45rem;
        margin-top: .4rem;
    }

    .foto-card {
        border: 1px solid var(--foto-card-border);
        border-radius: .45rem;
        background: var(--kanban-card-bg);
        overflow: hidden;
        position: relative;
    }

    .btn-remover-foto {
        position: absolute;
        top: 4px;
        right: 4px;
        border: 0;
        width: 22px;
        height: 22px;
        border-radius: 999px;
        background: rgba(220, 53, 69, .92);
        color: #fff;
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
    }

    .foto-thumb {
        width: 100%;
        aspect-ratio: 1 / 1;
        height: auto;
        object-fit: cover;
        display: block;
    }

    .foto-legenda {
        font-size: .7rem;
        color: var(--kanban-text-muted);
        padding: .3rem .4rem;
        border-top: 1px solid var(--foto-legenda-border);
    }

    #assinaturaCanvas {
        width: 100%;
        height: 140px;
        border: 1px solid var(--assinatura-border);
        border-radius: .45rem;
        background: var(--assinatura-bg);
        touch-action: none;
    }

    html.dark-style #modalChecklist .modal-content,
    html.dark-style #modalChecklist .card {
        background: #2b3046;
        border-color: #434a67;
    }
</style>
@endsection

@section('content')
@php
    $podeMoverCardExpedicao = \Perm::pode(auth()->user(), 'expedicao.logistica.mover-card');
    $podeChecklistExpedicao = \Perm::pode(auth()->user(), 'expedicao.logistica.checklist');
    $podeChecklistFotoExpedicao = \Perm::pode(auth()->user(), 'expedicao.logistica.checklist.foto');
    $podeChecklistConfirmarExpedicao = \Perm::pode(auth()->user(), 'expedicao.logistica.checklist.confirmar');
@endphp
<div class="container-xxl flex-grow-1 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Expedição e Logística</h4>
        <small class="text-muted">Arraste os contratos entre as colunas para atualizar o status.</small>
    </div>

    <div class="kanban-wrap">
        @foreach($colunasKanban as $status => $titulo)
            <div class="kanban-col" data-status-coluna="{{ $status }}">
                <div class="kanban-col-header">
                    <span>{{ $titulo }}</span>
                    <span class="badge bg-label-primary" id="count-{{ $status }}">0</span>
                </div>
                <div class="kanban-list" data-status="{{ $status }}">
                    @foreach($cards->where('status_logistica', $status) as $card)
                        <div class="kanban-card" data-id-locacao="{{ $card['id_locacao'] }}">
                            <h6>#{{ $card['numero_contrato'] }} · {{ $card['cliente'] }}</h6>
                            <div class="small text-muted mb-2">{{ $card['itens_resumo'] }}</div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <a href="{{ $card['map_url'] ?: '#' }}" class="small {{ $card['map_url'] ? '' : 'disabled' }}" target="_blank" rel="noopener">
                                    <i class="ti ti-map-pin me-1"></i>{{ $card['endereco'] }}
                                </a>
                                <span class="badge bg-label-{{ $card['urgencia']['class'] }}">{{ $card['urgencia']['label'] }}</span>
                            </div>
                            @if($podeChecklistExpedicao)
                                <button class="btn btn-primary btn-sm w-100 btn-abrir-checklist" data-id-locacao="{{ $card['id_locacao'] }}">
                                    Abrir Checklist
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="modal fade" id="modalChecklist" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="checklistTitulo">Checklist Fotográfico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-outline-primary btn-sm btn-tipo-checklist active" data-tipo="saida">Checklist de Saída</button>
                    <button class="btn btn-outline-primary btn-sm btn-tipo-checklist" data-tipo="entrada">Checklist de Entrada</button>
                </div>

                <div id="checklistItens"></div>

                <div class="mt-3">
                    <label class="form-label">Assinatura digital</label>
                    <canvas id="assinaturaCanvas" width="900" height="240"></canvas>
                    <div class="mt-2">
                        <button class="btn btn-outline-secondary btn-sm" id="btnLimparAssinatura" type="button">Limpar assinatura</button>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Observações gerais</label>
                    <textarea id="observacoesGerais" class="form-control" rows="2" placeholder="Observações da saída/entrada"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Fechar</button>
                @if($podeChecklistExpedicao)
                    <button type="button" class="btn btn-outline-dark" id="btnImprimirChecklist">Imprimir PDF de Saída</button>
                @endif
                @if($podeChecklistConfirmarExpedicao)
                    <button type="button" class="btn btn-primary" id="btnConfirmarChecklist">Confirmar e Assinar</button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
(() => {
    const token = '{{ csrf_token() }}';
    const permissoesExpedicao = {
        moverCard: @json($podeMoverCardExpedicao),
        checklist: @json($podeChecklistExpedicao),
        checklistFoto: @json($podeChecklistFotoExpedicao),
        checklistConfirmar: @json($podeChecklistConfirmarExpedicao),
    };
    const modalEl = document.getElementById('modalChecklist');
    const modalChecklist = new bootstrap.Modal(modalEl);
    const checklistItensEl = document.getElementById('checklistItens');
    const checklistTituloEl = document.getElementById('checklistTitulo');
    const observacoesGeraisEl = document.getElementById('observacoesGerais');
    const btnImprimirChecklist = document.getElementById('btnImprimirChecklist');
    const btnConfirmarChecklist = document.getElementById('btnConfirmarChecklist');

    let checklistAtual = null;
    let tipoChecklistAtual = 'saida';
    let locacaoAtual = null;
    let observacoesPorTipo = { saida: '', entrada: '' };

    const notificarErro = async (mensagem) => {
        await Swal.fire({ icon: 'error', title: 'Erro', text: mensagem || 'Ocorreu um erro.' });
    };

    const notificarSucesso = async (mensagem) => {
        await Swal.fire({ icon: 'success', title: 'Sucesso', text: mensagem || 'Operação concluída.' });
    };

    const notificarAviso = async (mensagem) => {
        await Swal.fire({ icon: 'warning', title: 'Atenção', text: mensagem || 'Verifique as informações.' });
    };

    const confirmarAcao = async (mensagem) => {
        const resposta = await Swal.fire({
            icon: 'question',
            title: 'Confirmar ação',
            text: mensagem || 'Deseja continuar?',
            showDenyButton: false,
            showCancelButton: true,
            confirmButtonText: 'Sim',
            cancelButtonText: 'Cancelar'
        });
        return !!resposta.isConfirmed;
    };

    const kanbanWrapEl = document.querySelector('.kanban-wrap');

    document.querySelectorAll('.kanban-list').forEach(coluna => {
        new Sortable(coluna, {
            group: 'kanban-expedicao',
            disabled: !permissoesExpedicao.moverCard,
            animation: 150,
            scroll: true,
            bubbleScroll: true,
            scrollSensitivity: 80,
            scrollSpeed: 18,
            forceAutoScrollFallback: true,
            fallbackOnBody: true,
            onMove: (evt) => {
                if (!kanbanWrapEl || !evt.originalEvent) {
                    return true;
                }

                const rect = kanbanWrapEl.getBoundingClientRect();
                const x = evt.originalEvent.clientX;
                const margem = 90;
                const passo = 28;

                if (x > rect.right - margem) {
                    kanbanWrapEl.scrollLeft += passo;
                } else if (x < rect.left + margem) {
                    kanbanWrapEl.scrollLeft -= passo;
                }

                return true;
            },
            onEnd: async (evt) => {
                if (!evt.to?.dataset?.status || !evt.item?.dataset?.idLocacao) return;

                const idLocacao = evt.item.dataset.idLocacao;
                const status = evt.to.dataset.status;

                try {
                    const response = await fetch(`/locacoes/expedicao/${idLocacao}/mover`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ status_logistica: status })
                    });

                    const result = await response.json();
                    if (!response.ok || !result.ok) {
                        throw new Error(result.message || 'Falha ao atualizar status.');
                    }
                } catch (error) {
                    await notificarErro(error.message || 'Erro ao mover card.');
                    location.reload();
                } finally {
                    atualizarContadores();
                }
            }
        });
    });

    function atualizarContadores() {
        document.querySelectorAll('.kanban-list').forEach(coluna => {
            const status = coluna.dataset.status;
            const countEl = document.getElementById(`count-${status}`);
            if (countEl) countEl.textContent = coluna.querySelectorAll('.kanban-card').length;
        });
    }

    atualizarContadores();

    document.querySelectorAll('.btn-abrir-checklist').forEach(btn => {
        btn.addEventListener('click', async () => {
            const idLocacao = btn.dataset.idLocacao;
            await carregarChecklist(idLocacao);
        });
    });

    document.querySelectorAll('.btn-tipo-checklist').forEach(btn => {
        btn.addEventListener('click', () => {
            salvarObservacaoDigitada();
            document.querySelectorAll('.btn-tipo-checklist').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            tipoChecklistAtual = btn.dataset.tipo;
            renderChecklist();
        });
    });

    async function carregarChecklist(idLocacao) {
        try {
            const response = await fetch(`/locacoes/expedicao/${idLocacao}/checklist`, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Erro ao carregar checklist.');

            checklistAtual = result;
            locacaoAtual = idLocacao;
            tipoChecklistAtual = 'saida';
            observacoesPorTipo.saida = result?.checklists?.saida?.observacoes_gerais || '';
            observacoesPorTipo.entrada = result?.checklists?.entrada?.observacoes_gerais || '';
            document.querySelectorAll('.btn-tipo-checklist').forEach((b, i) => b.classList.toggle('active', i === 0));
            checklistTituloEl.textContent = `Checklist #${result.locacao.numero_contrato} · ${result.locacao.cliente}`;
            limparAssinatura();
            renderChecklist();
            modalChecklist.show();
        } catch (error) {
            await notificarErro(error.message || 'Erro ao abrir checklist.');
        }
    }

    function renderChecklist() {
        if (!checklistAtual) return;

        const isEntrada = tipoChecklistAtual === 'entrada';
        observacoesGeraisEl.value = observacoesPorTipo[tipoChecklistAtual] || '';
        carregarAssinaturaExistente();
        if (btnImprimirChecklist) {
            btnImprimirChecklist.textContent = isEntrada ? 'Imprimir PDF de Entrada' : 'Imprimir PDF de Saída';
        }
        const html = checklistAtual.itens.map(item => {
            const saidaFotos = item.saida_fotos || [];
            const entradaFotos = item.entrada_fotos || [];
            const voltouComDefeitoItem = !!item.voltou_com_defeito;
            const quantidadeItem = Math.max(1, Number(item.quantidade || 1));
            const quantidadeDefeitoItem = Math.max(1, Math.min(quantidadeItem, Number(item.quantidade_com_defeito || 1)));
            const isPatrimonio = !!item.patrimonio;
            const observacaoDefeitoItem = (item.observacao_defeito || '').toString();

            const renderFotos = (fotos, altBase) => {
                if (!fotos.length) {
                    return '<div class="small text-muted mt-2">Sem fotos.</div>';
                }

                return `<div class="foto-grid">${fotos.map((f, idx) => `
                    <div class="foto-card">
                        ${permissoesExpedicao.checklistFoto ? `<button type="button" class="btn-remover-foto" data-id-foto="${f.id}" title="Remover foto">×</button>` : ''}
                        <img src="${f.url_foto}" class="foto-thumb" alt="${altBase}">
                        <div class="foto-legenda">${f.capturado_em || ''}${f.observacao ? ` · ${f.observacao}` : ''}</div>
                    </div>
                `).join('')}</div>`;
            };

            const blocoSaida = `
                <div class="foto-box">
                    <small class="text-muted">Fotos de Saída</small>
                    ${renderFotos(saidaFotos, 'Saída')}
                </div>`;

            const blocoEntrada = `
                <div class="foto-box">
                    <small class="text-muted">Fotos de Entrada</small>
                    ${renderFotos(entradaFotos, 'Entrada')}
                </div>`;

            return `
                <div class="card mb-2">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>${item.quantidade}x ${item.nome}</strong>
                                ${item.patrimonio ? `<span class="badge bg-label-info ms-1">Patrimônio: ${item.patrimonio}</span>` : ''}
                            </div>
                            ${permissoesExpedicao.checklistFoto ? `
                            <label class="btn btn-sm btn-outline-primary mb-0">
                                Adicionar Foto
                                <input type="file" class="d-none input-foto" accept="image/*" capture="environment"
                                    data-id-produto-locacao="${item.id_produto_locacao}">
                            </label>
                            ` : ''}
                        </div>

                        ${isEntrada ? `
                            <div class="checklist-grid mb-2">
                                ${blocoSaida}
                                ${blocoEntrada}
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input toggle-defeito" type="checkbox" data-id-produto-locacao="${item.id_produto_locacao}" ${voltouComDefeitoItem ? 'checked' : ''}>
                                <label class="form-check-label">Voltou com defeito?</label>
                            </div>
                            <div class="mb-2 ${voltouComDefeitoItem ? '' : 'd-none'} qtd-defeito-wrap" data-id-produto-locacao="${item.id_produto_locacao}">
                                <label class="form-label form-label-sm mb-1">Quantidade com defeito</label>
                                <input
                                    type="number"
                                    class="form-control form-control-sm qtd-defeito"
                                    data-id-produto-locacao="${item.id_produto_locacao}"
                                    min="1"
                                    max="${quantidadeItem}"
                                    value="${isPatrimonio ? 1 : quantidadeDefeitoItem}"
                                    ${isPatrimonio ? 'readonly' : ''}
                                >
                                <small class="text-muted">de ${quantidadeItem}</small>
                            </div>
                            <textarea class="form-control form-control-sm obs-defeito ${voltouComDefeitoItem ? '' : 'd-none'}" rows="2"
                                data-id-produto-locacao="${item.id_produto_locacao}"
                                placeholder="Descreva a avaria...">${observacaoDefeitoItem}</textarea>
                        ` : blocoSaida}
                    </div>
                </div>`;
        }).join('');

        checklistItensEl.innerHTML = html;
        bindChecklistEventos();
    }

    function bindChecklistEventos() {
        if (!permissoesExpedicao.checklistFoto) {
            return;
        }

        checklistItensEl.querySelectorAll('.input-foto').forEach(input => {
            input.addEventListener('change', async () => {
                const arquivo = input.files?.[0];
                if (!arquivo || !locacaoAtual) return;

                const arquivoPreparado = await prepararArquivoParaUpload(arquivo);

                const idProdutoLocacao = input.dataset.idProdutoLocacao;
                const obsEl = checklistItensEl.querySelector(`.obs-defeito[data-id-produto-locacao="${idProdutoLocacao}"]`);
                const toggleEl = checklistItensEl.querySelector(`.toggle-defeito[data-id-produto-locacao="${idProdutoLocacao}"]`);

                const formData = new FormData();
                formData.append('tipo', tipoChecklistAtual);
                formData.append('id_produto_locacao', idProdutoLocacao);
                formData.append('foto', arquivoPreparado, arquivoPreparado?.name || arquivo.name);
                formData.append('voltou_com_defeito', toggleEl?.checked ? '1' : '0');
                formData.append('observacao', obsEl?.value || '');

                try {
                    const response = await fetch(`/locacoes/expedicao/${locacaoAtual}/checklist/foto`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: formData
                    });
                    const result = await response.json();
                    if (!response.ok || !result.ok) throw new Error(result.message || 'Falha ao enviar foto.');
                    salvarObservacaoDigitada();
                    await carregarChecklist(locacaoAtual);
                    tipoChecklistAtual = formData.get('tipo');
                    document.querySelectorAll('.btn-tipo-checklist').forEach(b => b.classList.toggle('active', b.dataset.tipo === tipoChecklistAtual));
                    renderChecklist();
                    if (result.alerta_avaria) {
                        await notificarAviso('Alerta de avaria gerado para este item.');
                    }
                } catch (error) {
                    await notificarErro(error.message || 'Erro no upload da foto.');
                } finally {
                    input.value = '';
                }
            });
        });

        checklistItensEl.querySelectorAll('.toggle-defeito').forEach(toggle => {
            toggle.addEventListener('change', () => {
                const idProdutoLocacao = toggle.dataset.idProdutoLocacao;
                const obsEl = checklistItensEl.querySelector(`.obs-defeito[data-id-produto-locacao="${idProdutoLocacao}"]`);
                const qtdWrapEl = checklistItensEl.querySelector(`.qtd-defeito-wrap[data-id-produto-locacao="${idProdutoLocacao}"]`);
                if (!obsEl) return;
                obsEl.classList.toggle('d-none', !toggle.checked);
                if (qtdWrapEl) {
                    qtdWrapEl.classList.toggle('d-none', !toggle.checked);
                }
            });
        });

        checklistItensEl.querySelectorAll('.btn-remover-foto').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!locacaoAtual) return;

                const idFoto = btn.dataset.idFoto;
                if (!idFoto) return;

                if (!await confirmarAcao('Remover esta foto do checklist?')) {
                    return;
                }

                try {
                    const response = await fetch(`/locacoes/expedicao/${locacaoAtual}/checklist/foto/${idFoto}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        }
                    });

                    const result = await response.json();
                    if (!response.ok || !result.ok) {
                        throw new Error(result.message || 'Falha ao remover foto.');
                    }

                    salvarObservacaoDigitada();
                    await carregarChecklist(locacaoAtual);
                    renderChecklist();
                    await notificarSucesso('Foto removida com sucesso.');
                } catch (error) {
                    await notificarErro(error.message || 'Erro ao remover foto.');
                }
            });
        });
    }

    async function prepararArquivoParaUpload(arquivo) {
        if (!(arquivo instanceof File) || !String(arquivo.type || '').startsWith('image/')) {
            return arquivo;
        }

        const limiteSemCompressao = 1.8 * 1024 * 1024;
        if (arquivo.size <= limiteSemCompressao) {
            return arquivo;
        }

        try {
            return await comprimirImagem(arquivo, {
                maxLado: 1920,
                qualidadeInicial: 0.86,
                qualidadeMinima: 0.56,
                maxBytes: Math.round(1.8 * 1024 * 1024),
            });
        } catch (_e) {
            return arquivo;
        }
    }

    function comprimirImagem(arquivo, opcoes = {}) {
        const maxLado = Number(opcoes.maxLado || 1920);
        const qualidadeInicial = Number(opcoes.qualidadeInicial || 0.86);
        const qualidadeMinima = Number(opcoes.qualidadeMinima || 0.56);
        const maxBytes = Number(opcoes.maxBytes || (1.8 * 1024 * 1024));

        return new Promise((resolve, reject) => {
            const imageUrl = URL.createObjectURL(arquivo);
            const imagem = new Image();

            imagem.onload = () => {
                const larguraOriginal = imagem.width || 1;
                const alturaOriginal = imagem.height || 1;
                const maiorLado = Math.max(larguraOriginal, alturaOriginal);
                const escala = maiorLado > maxLado ? (maxLado / maiorLado) : 1;

                const largura = Math.max(1, Math.round(larguraOriginal * escala));
                const altura = Math.max(1, Math.round(alturaOriginal * escala));

                const canvasTmp = document.createElement('canvas');
                canvasTmp.width = largura;
                canvasTmp.height = altura;

                const ctxTmp = canvasTmp.getContext('2d');
                if (!ctxTmp) {
                    URL.revokeObjectURL(imageUrl);
                    reject(new Error('Falha ao preparar imagem para upload.'));
                    return;
                }

                ctxTmp.drawImage(imagem, 0, 0, largura, altura);

                const nomeBase = (arquivo.name || 'foto').replace(/\.[^.]+$/, '');
                const nomeFinal = `${nomeBase}.jpg`;

                const gerarBlob = (qualidadeAtual) => new Promise((resolveBlob) => {
                    canvasTmp.toBlob((blob) => resolveBlob(blob), 'image/jpeg', qualidadeAtual);
                });

                (async () => {
                    let qualidadeAtual = qualidadeInicial;
                    let blobFinal = await gerarBlob(qualidadeAtual);

                    while (blobFinal && blobFinal.size > maxBytes && qualidadeAtual > qualidadeMinima) {
                        qualidadeAtual = Math.max(qualidadeMinima, Number((qualidadeAtual - 0.06).toFixed(2)));
                        blobFinal = await gerarBlob(qualidadeAtual);
                    }

                    URL.revokeObjectURL(imageUrl);

                    if (!blobFinal) {
                        reject(new Error('Falha ao comprimir imagem.'));
                        return;
                    }

                    const arquivoFinal = new File([blobFinal], nomeFinal, {
                        type: 'image/jpeg',
                        lastModified: Date.now(),
                    });

                    resolve(arquivoFinal);
                })().catch((erro) => {
                    URL.revokeObjectURL(imageUrl);
                    reject(erro);
                });
            };

            imagem.onerror = () => {
                URL.revokeObjectURL(imageUrl);
                reject(new Error('Falha ao ler imagem.'));
            };

            imagem.src = imageUrl;
        });
    }

    if (btnConfirmarChecklist) {
    btnConfirmarChecklist.addEventListener('click', async () => {
        if (!locacaoAtual) return;
        salvarObservacaoDigitada();
        const assinatura = obterAssinaturaBase64();

        if (!assinatura) {
            await notificarAviso('Assine no campo antes de confirmar.');
            return;
        }

        const itensAvaria = [];
        if (tipoChecklistAtual === 'entrada') {
            checklistItensEl.querySelectorAll('.toggle-defeito:checked').forEach(toggle => {
                const idProdutoLocacao = toggle.dataset.idProdutoLocacao;
                const obsEl = checklistItensEl.querySelector(`.obs-defeito[data-id-produto-locacao="${idProdutoLocacao}"]`);
                const qtdEl = checklistItensEl.querySelector(`.qtd-defeito[data-id-produto-locacao="${idProdutoLocacao}"]`);
                const qtdMax = Number(qtdEl?.max || 1);
                let qtdDefeito = Math.max(1, Number(qtdEl?.value || 1));
                if (qtdDefeito > qtdMax) qtdDefeito = qtdMax;

                if (qtdEl) {
                    qtdEl.value = String(qtdDefeito);
                }

                itensAvaria.push({
                    id_produto_locacao: Number(idProdutoLocacao),
                    quantidade_defeito: qtdDefeito,
                    observacao: obsEl?.value || ''
                });
            });
        }

        try {
            const response = await fetch(`/locacoes/expedicao/${locacaoAtual}/checklist/confirmar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    tipo: tipoChecklistAtual,
                    assinatura_base64: assinatura,
                    observacoes_gerais: observacoesPorTipo[tipoChecklistAtual] || '',
                    itens_avaria: itensAvaria
                })
            });

            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Falha ao confirmar checklist.');

            if (result.alerta_avaria) {
                await notificarAviso('Checklist confirmado com alerta de avaria.');
            } else {
                await notificarSucesso('Checklist confirmado com sucesso.');
            }

            modalChecklist.hide();
        } catch (error) {
            await notificarErro(error.message || 'Erro ao confirmar checklist.');
        }
    });
    }

    if (btnImprimirChecklist) {
        btnImprimirChecklist.addEventListener('click', () => {
            if (!locacaoAtual) return;
            const url = `/locacoes/expedicao/${locacaoAtual}/checklist/imprimir?tipo=${tipoChecklistAtual}`;
            window.open(url, '_blank');
        });
    }

    function salvarObservacaoDigitada() {
        observacoesPorTipo[tipoChecklistAtual] = observacoesGeraisEl.value || '';
    }

    observacoesGeraisEl.addEventListener('input', salvarObservacaoDigitada);

    const canvas = document.getElementById('assinaturaCanvas');
    const ctx = canvas.getContext('2d');
    let desenhando = false;

    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1f2d3d';

    function posicaoCanvas(event) {
        const rect = canvas.getBoundingClientRect();
        const touch = event.touches?.[0];
        const x = ((touch ? touch.clientX : event.clientX) - rect.left) * (canvas.width / rect.width);
        const y = ((touch ? touch.clientY : event.clientY) - rect.top) * (canvas.height / rect.height);
        return { x, y };
    }

    function iniciarDesenho(event) {
        desenhando = true;
        const { x, y } = posicaoCanvas(event);
        ctx.beginPath();
        ctx.moveTo(x, y);
    }

    function desenhar(event) {
        if (!desenhando) return;
        event.preventDefault();
        const { x, y } = posicaoCanvas(event);
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    function pararDesenho() {
        desenhando = false;
        ctx.closePath();
    }

    canvas.addEventListener('mousedown', iniciarDesenho);
    canvas.addEventListener('mousemove', desenhar);
    canvas.addEventListener('mouseup', pararDesenho);
    canvas.addEventListener('mouseleave', pararDesenho);

    canvas.addEventListener('touchstart', iniciarDesenho, { passive: false });
    canvas.addEventListener('touchmove', desenhar, { passive: false });
    canvas.addEventListener('touchend', pararDesenho);

    function limparAssinatura() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    function carregarAssinaturaExistente() {
        limparAssinatura();

        const assinaturaExistente = checklistAtual?.checklists?.[tipoChecklistAtual]?.assinatura_base64;
        if (!assinaturaExistente) {
            return;
        }

        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            limparAssinatura();

            const escala = Math.min(canvas.width / img.width, canvas.height / img.height);
            const largura = img.width * escala;
            const altura = img.height * escala;
            const x = (canvas.width - largura) / 2;
            const y = (canvas.height - altura) / 2;

            ctx.drawImage(img, x, y, largura, altura);
        };
        img.onerror = () => {
            limparAssinatura();
        };
        img.src = assinaturaExistente;
    }

    function obterAssinaturaBase64() {
        const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        let vazio = true;
        for (let i = 3; i < pixels.length; i += 4) {
            if (pixels[i] !== 0) { vazio = false; break; }
        }
        return vazio ? null : canvas.toDataURL('image/png');
    }

    document.getElementById('btnLimparAssinatura').addEventListener('click', limparAssinatura);
})();
</script>
@endsection
