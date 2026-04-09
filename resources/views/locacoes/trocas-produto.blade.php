@extends('layouts.layoutMaster')

@section('title', 'Troca de Produto')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <h5 class="mb-0">Troca de Produto</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Contrato (não faturado)</label>
                    <select class="form-select" id="trocaContratoSelect">
                        <option value="">Selecione...</option>
                        @foreach($locacoesElegiveis as $locacao)
                            <option value="{{ $locacao->id_locacao }}" {{ (int) $idLocacaoFiltro === (int) $locacao->id_locacao ? 'selected' : '' }}>
                                #{{ $locacao->codigo_display }} - {{ $locacao->cliente->nome ?? 'Cliente' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Item atual do contrato</label>
                    <select class="form-select" id="trocaItemSelect">
                        <option value="">Selecione o contrato primeiro</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Novo produto</label>
                    <select class="form-select" id="trocaNovoProdutoSelect">
                        <option value="">Selecione...</option>
                        @foreach($produtosAtivos as $produto)
                            @php
                                $usaPatrimonio = (int) ($produto->patrimonios_ativos_count ?? 0) > 0;
                                $patrimoniosDisponiveis = collect($produto->patrimonios ?? [])->map(function ($patrimonio) {
                                    return [
                                        'id_patrimonio' => (int) $patrimonio->id_patrimonio,
                                        'codigo' => $patrimonio->codigo_patrimonio ?: $patrimonio->numero_serie ?: ('PAT-' . $patrimonio->id_patrimonio),
                                    ];
                                })->values();

                                $estoqueDisponivel = $usaPatrimonio
                                    ? (int) ($produto->patrimonios_disponiveis_count ?? $patrimoniosDisponiveis->count())
                                    : (int) ($produto->quantidade ?? 0);
                            @endphp
                            <option
                                value="{{ $produto->id_produto }}"
                                data-nome="{{ e($produto->nome) }}"
                                data-estoque="{{ $estoqueDisponivel }}"
                                data-usa-patrimonio="{{ $usaPatrimonio ? 1 : 0 }}"
                                data-patrimonios='@json($patrimoniosDisponiveis)'>
                                {{ $produto->nome }}{{ $produto->codigo ? ' (' . $produto->codigo . ')' : '' }} - Estoque: {{ $estoqueDisponivel }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted d-block mt-1" id="trocaEstoqueInfo">Selecione um item e um produto para consultar disponibilidade no período.</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Qtd troca</label>
                    <input type="number" class="form-control" id="trocaQuantidade" min="1" value="1">
                    <small class="text-muted d-block mt-1" id="trocaQuantidadeInfo">Qtd disponível no item: 0</small>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Motivo</label>
                    <input type="text" class="form-control" id="trocaMotivo" maxlength="255" placeholder="Motivo da troca">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Observações</label>
                    <input type="text" class="form-control" id="trocaObservacoes" maxlength="2000" placeholder="Observações adicionais">
                </div>
                <div class="col-12 d-none" id="trocaPatrimoniosWrapper">
                    <label class="form-label">Patrimônios do novo produto</label>
                    <div class="border rounded p-2" id="trocaPatrimoniosChecklist" style="max-height: 180px; overflow-y: auto;"></div>
                    <small class="text-muted d-block mt-1" id="trocaPatrimoniosInfo">Selecione os patrimônios do produto novo.</small>
                </div>
                @pode('expedicao.troca.executar')
                <div class="col-12">
                    <button type="button" class="btn btn-primary" id="btnRegistrarTrocaProduto">
                        <i class="ti ti-switch-horizontal me-1"></i>Registrar troca
                    </button>
                </div>
                @endpode
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Histórico de Trocas</h5>
            <form method="GET" action="{{ route('locacoes.trocas-produto') }}" class="d-flex gap-2">
                <select class="form-select form-select-sm" name="id_locacao" style="min-width:280px;">
                    <option value="">Todos os contratos</option>
                    @foreach($locacoesElegiveis as $locacao)
                        <option value="{{ $locacao->id_locacao }}" {{ (int) $idLocacaoFiltro === (int) $locacao->id_locacao ? 'selected' : '' }}>
                            #{{ $locacao->codigo_display }} - {{ $locacao->cliente->nome ?? 'Cliente' }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit">Filtrar</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('locacoes.trocas-produto') }}">Limpar</a>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Contrato</th>
                            <th>Cliente</th>
                            <th>Produto anterior</th>
                            <th>Novo produto</th>
                            <th>Patrimônio anterior</th>
                            <th>Patrimônio novo</th>
                            <th>Qtd</th>
                            <th>Usuário</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trocas as $troca)
                            <tr>
                                <td>{{ optional($troca->created_at)->format('d/m/Y H:i') }}</td>
                                <td>#{{ $troca->locacao->codigo_display ?? $troca->locacao->numero_contrato ?? '-' }}</td>
                                <td>{{ $troca->locacao->cliente->nome ?? '-' }}</td>
                                <td>{{ $troca->produtoAnterior->nome ?? '-' }}</td>
                                <td>{{ $troca->produtoNovo->nome ?? '-' }}</td>
                                <td>{{ $troca->patrimonio_anterior_troca ?? '-' }}</td>
                                <td>{{ $troca->patrimonio_novo_troca ?? '-' }}</td>
                                <td>{{ (int) ($troca->quantidade ?? 1) }}</td>
                                <td>{{ $troca->usuario->nome ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('locacoes.trocas.pdf', ['troca' => $troca->id_locacao_troca_produto]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-file-text"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">Nenhuma troca registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($trocas, 'links') && $trocas->total() > 0)
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">Mostrando {{ $trocas->firstItem() }} até {{ $trocas->lastItem() }} de {{ $trocas->total() }} registros</div>
                    {{ $trocas->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
$(function () {
    const csrfToken = '{{ csrf_token() }}';
    const baseLocacoesUrl = '{{ url('locacoes') }}';

    let disponibilidadeAtual = null;
    let disponibilidadeRequestSeq = 0;

    function obterPatrimoniosNovoProdutoSelecionado() {
        const $option = $('#trocaNovoProdutoSelect option:selected');
        const raw = $option.attr('data-patrimonios') || '[]';

        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function obterIdsPatrimoniosChecklistSelecionados() {
        return $('#trocaPatrimoniosChecklist input.troca-patrimonio-checkbox:checked').map(function () {
            return Number($(this).val() || 0);
        }).get().filter((idPatrimonio) => idPatrimonio > 0);
    }

    function formatarDataBr(dataValor) {
        const texto = String(dataValor || '').trim();
        if (!texto) {
            return '';
        }

        const partesIso = texto.split('-');
        if (partesIso.length === 3) {
            return `${partesIso[2]}/${partesIso[1]}/${partesIso[0]}`;
        }

        const dataObj = new Date(texto);
        if (!Number.isNaN(dataObj.getTime())) {
            const dia = String(dataObj.getDate()).padStart(2, '0');
            const mes = String(dataObj.getMonth() + 1).padStart(2, '0');
            const ano = dataObj.getFullYear();
            return `${dia}/${mes}/${ano}`;
        }

        return texto;
    }

    function formatarPeriodoDisponibilidade(periodo) {
        if (!periodo || !periodo.data_inicio || !periodo.data_fim) {
            return '';
        }

        const horaInicio = periodo.hora_inicio ? String(periodo.hora_inicio).slice(0, 5) : '00:00';
        const horaFim = periodo.hora_fim ? String(periodo.hora_fim).slice(0, 5) : '23:59';
        const inicio = `${formatarDataBr(periodo.data_inicio)} ${horaInicio}`;
        const fim = `${formatarDataBr(periodo.data_fim)} ${horaFim}`;

        return `${inicio} até ${fim}`;
    }

    function resetarDisponibilidadeTroca() {
        disponibilidadeAtual = null;
        disponibilidadeRequestSeq += 1;
    }

    function atualizarInfoEstoqueDisponibilidade(mensagem = null) {
        const $info = $('#trocaEstoqueInfo');

        if (mensagem) {
            $info.text(mensagem);
            return;
        }

        const idProdutoNovo = Number($('#trocaNovoProdutoSelect').val() || 0);
        const idItem = Number($('#trocaItemSelect').val() || 0);

        if (!idProdutoNovo) {
            $info.text('Selecione um item e um produto para consultar disponibilidade no período.');
            return;
        }

        if (!idItem) {
            $info.text('Selecione um item do contrato para consultar disponibilidade do novo produto.');
            return;
        }

        if (!disponibilidadeAtual || !disponibilidadeAtual.disponibilidade) {
            $info.text('Não foi possível obter a disponibilidade atual para este produto.');
            return;
        }

        const disponibilidade = disponibilidadeAtual.disponibilidade;
        const quantidadeDisponivel = Number(disponibilidade.disponivel || 0);
        const quantidadeReservada = Number(disponibilidade.reservado || 0);
        const quantidadeEmLocacao = Number(disponibilidade.em_locacao || 0);

        const patrimoniosDisponiveis = Array.isArray(disponibilidade.patrimonios_disponiveis)
            ? disponibilidade.patrimonios_disponiveis
            : [];
        const patrimoniosOcupados = Array.isArray(disponibilidade.patrimonios_ocupados)
            ? disponibilidade.patrimonios_ocupados
            : [];
        const produtoUsaPatrimonio = Boolean(disponibilidadeAtual?.produto_usa_patrimonio);

        let quantidadeTotal = Number(disponibilidade.patrimonios_total || disponibilidade.estoque_total || 0);

        if (produtoUsaPatrimonio) {
            if (quantidadeTotal <= 0) {
                quantidadeTotal = patrimoniosDisponiveis.length + patrimoniosOcupados.length;
            }
        } else {
            if (quantidadeTotal <= 0) {
                quantidadeTotal = quantidadeDisponivel + quantidadeReservada + quantidadeEmLocacao;
            }

            if (quantidadeTotal <= 0 && patrimoniosDisponiveis.length > 0) {
                quantidadeTotal = patrimoniosDisponiveis.length + patrimoniosOcupados.length;
            }
        }

        if (quantidadeTotal < quantidadeDisponivel) {
            quantidadeTotal = quantidadeDisponivel;
        }

        const quantidadeOcupada = Math.max(0, quantidadeTotal - quantidadeDisponivel);

        const periodoTexto = formatarPeriodoDisponibilidade(disponibilidadeAtual.periodo);
        const sufixoPeriodo = periodoTexto ? ` no período ${periodoTexto}` : '';

        $info.text(`Disponibilidade${sufixoPeriodo}: ${quantidadeDisponivel} de ${quantidadeTotal} disponível(is), ${quantidadeOcupada} ocupada(s).`);
    }

    function atualizarEstadoCheckboxPatrimonios() {
        const quantidadeTroca = Math.max(1, Number($('#trocaQuantidade').val() || 1));
        const $checkboxes = $('#trocaPatrimoniosChecklist input.troca-patrimonio-checkbox');

        if (!$checkboxes.length) {
            return;
        }

        const $selecionados = $checkboxes.filter(':checked');
        if ($selecionados.length > quantidadeTroca) {
            $selecionados.slice(quantidadeTroca).prop('checked', false);
        }

        const totalSelecionados = $checkboxes.filter(':checked').length;

        $checkboxes.each(function () {
            const $checkbox = $(this);
            const desabilitadoBackend = Number($checkbox.data('backend-disabled') || 0) === 1;

            if (desabilitadoBackend) {
                $checkbox.prop('disabled', true);
                return;
            }

            const desabilitarPorLimite = totalSelecionados >= quantidadeTroca && !$checkbox.is(':checked');
            $checkbox.prop('disabled', desabilitarPorLimite);
        });

        const totalDisponiveis = $checkboxes.filter(function () {
            return Number($(this).data('backend-disabled') || 0) !== 1;
        }).length;

        $('#trocaPatrimoniosInfo').text(`Selecione ${quantidadeTroca} patrimônio(s). Selecionados: ${$checkboxes.filter(':checked').length}. Disponíveis no período: ${totalDisponiveis}.`);
    }

    function renderizarChecklistPatrimoniosNovo() {
        const $wrapper = $('#trocaPatrimoniosWrapper');
        const $checklist = $('#trocaPatrimoniosChecklist');
        const $info = $('#trocaPatrimoniosInfo');

        const $novoProdutoOption = $('#trocaNovoProdutoSelect option:selected');
        const $itemOption = $('#trocaItemSelect option:selected');

        const idProdutoNovo = Number($('#trocaNovoProdutoSelect').val() || 0);
        const novoProdutoUsaPatrimonio = Number($novoProdutoOption.data('usa-patrimonio') || 0) === 1;

        if (!idProdutoNovo || !novoProdutoUsaPatrimonio) {
            $wrapper.addClass('d-none');
            $checklist.html('');
            $info.text('Selecione os patrimônios do produto novo.');
            return;
        }

        const quantidadeTroca = Math.max(1, Number($('#trocaQuantidade').val() || 1));
        const idProdutoAtual = Number($itemOption.data('id-produto') || 0);
        const idPatrimonioAtual = Number($itemOption.data('id-patrimonio') || 0);
        const selecionadosAtuais = obterIdsPatrimoniosChecklistSelecionados().map((idPatrimonio) => String(idPatrimonio));

        const possuiDisponibilidadeCalculada = disponibilidadeAtual && disponibilidadeAtual.disponibilidade;
        const disponibilidade = possuiDisponibilidadeCalculada ? disponibilidadeAtual.disponibilidade : null;
        const patrimonios = possuiDisponibilidadeCalculada
            ? (Array.isArray(disponibilidade.patrimonios_disponiveis) ? disponibilidade.patrimonios_disponiveis : [])
            : obterPatrimoniosNovoProdutoSelecionado();

        if (!patrimonios.length) {
            $checklist.html('<div class="text-muted small">Nenhum patrimônio disponível para o período selecionado.</div>');
            $info.text('Nenhum patrimônio disponível para o período selecionado.');
            $wrapper.removeClass('d-none');
            return;
        }

        let html = '';

        patrimonios.forEach((patrimonio) => {
            const idPatrimonio = Number(patrimonio.id_patrimonio || 0);
            if (!idPatrimonio) {
                return;
            }

            const codigoBase = patrimonio.codigo || patrimonio.numero_serie || `PAT-${idPatrimonio}`;
            const bloquearMesmoPatrimonio = idProdutoNovo === idProdutoAtual
                && idPatrimonioAtual > 0
                && idPatrimonioAtual === idPatrimonio;

            const checkboxId = `trocaPatrimonioNovo_${idPatrimonio}`;
            const checked = selecionadosAtuais.includes(String(idPatrimonio)) && !bloquearMesmoPatrimonio ? 'checked' : '';
            const disabled = bloquearMesmoPatrimonio ? 'disabled' : '';
            const sufixo = bloquearMesmoPatrimonio ? ' (patrimônio atual)' : '';

            html += `
                <div class="form-check mb-1">
                    <input class="form-check-input troca-patrimonio-checkbox" type="checkbox" id="${checkboxId}" value="${idPatrimonio}" data-backend-disabled="${bloquearMesmoPatrimonio ? 1 : 0}" ${checked} ${disabled}>
                    <label class="form-check-label" for="${checkboxId}">${codigoBase}${sufixo}</label>
                </div>
            `;
        });

        if (!html.trim()) {
            $checklist.html('<div class="text-muted small">Nenhum patrimônio elegível para seleção.</div>');
            $info.text('Nenhum patrimônio elegível para seleção.');
            $wrapper.removeClass('d-none');
            return;
        }

        $checklist.html(html);
        $wrapper.removeClass('d-none');

        const $selecionados = $checklist.find('input.troca-patrimonio-checkbox:checked');
        if (!$selecionados.length) {
            $checklist.find('input.troca-patrimonio-checkbox:not(:disabled)').slice(0, quantidadeTroca).prop('checked', true);
        }

        atualizarEstadoCheckboxPatrimonios();
    }

    function carregarDisponibilidadeNovoProduto() {
        const idLocacao = Number($('#trocaContratoSelect').val() || 0);
        const idProdutoLocacao = Number($('#trocaItemSelect').val() || 0);
        const idProdutoNovo = Number($('#trocaNovoProdutoSelect').val() || 0);

        disponibilidadeAtual = null;

        if (!idProdutoNovo) {
            atualizarInfoEstoqueDisponibilidade();
            renderizarChecklistPatrimoniosNovo();
            return;
        }

        if (!idLocacao || !idProdutoLocacao) {
            atualizarInfoEstoqueDisponibilidade('Selecione um item válido para consultar disponibilidade do novo produto.');
            renderizarChecklistPatrimoniosNovo();
            return;
        }

        const requestId = ++disponibilidadeRequestSeq;

        atualizarInfoEstoqueDisponibilidade('Consultando disponibilidade no período...');

        $.ajax({
            url: `${baseLocacoesUrl}/${idLocacao}/disponibilidade-troca-produto`,
            type: 'GET',
            data: {
                id_produto_locacao: idProdutoLocacao,
                id_produto_novo: idProdutoNovo,
            },
            success: function (response) {
                if (requestId !== disponibilidadeRequestSeq) {
                    return;
                }

                disponibilidadeAtual = response || null;
                atualizarInfoEstoqueDisponibilidade();
                renderizarChecklistPatrimoniosNovo();
            },
            error: function (xhr) {
                if (requestId !== disponibilidadeRequestSeq) {
                    return;
                }

                const response = xhr.responseJSON || {};
                const mensagem = response.message || 'Não foi possível consultar a disponibilidade do novo produto.';
                disponibilidadeAtual = null;
                atualizarInfoEstoqueDisponibilidade(mensagem);
                renderizarChecklistPatrimoniosNovo();
            }
        });
    }

    function atualizarCampoQuantidade() {
        const $itemSelect = $('#trocaItemSelect');
        const $option = $itemSelect.find('option:selected');
        const idItem = Number($itemSelect.val() || 0);
        const $qtdInput = $('#trocaQuantidade');
        const $qtdInfo = $('#trocaQuantidadeInfo');

        if (!idItem) {
            $qtdInput.val(1).attr('max', 1).prop('disabled', false);
            $qtdInfo.text('Qtd disponível no item: 0');
            return;
        }

        const quantidadeMaxima = Math.max(1, Number($option.data('qtd') || 1));
        const itemUsaPatrimonio = Number($option.data('usa-patrimonio') || 0) === 1;

        if (itemUsaPatrimonio) {
            $qtdInput.val(1).attr('max', 1).prop('disabled', true);
            $qtdInfo.text('Item com patrimônio: troca unitária.');
            return;
        }

        const quantidadeAtual = Math.max(1, Number($qtdInput.val() || 1));
        const quantidadeNormalizada = Math.min(quantidadeMaxima, quantidadeAtual);

        $qtdInput.val(quantidadeNormalizada).attr('max', quantidadeMaxima).prop('disabled', false);
        $qtdInfo.text(`Qtd disponível no item: ${quantidadeMaxima}`);
    }

    function carregarItensContrato(idLocacao) {
        const $itemSelect = $('#trocaItemSelect');
        $itemSelect.html('<option value="">Carregando itens...</option>');

        resetarDisponibilidadeTroca();

        if (!idLocacao) {
            $itemSelect.html('<option value="">Selecione o contrato primeiro</option>');
            atualizarCampoQuantidade();
            atualizarInfoEstoqueDisponibilidade();
            renderizarChecklistPatrimoniosNovo();
            return;
        }

        $.ajax({
            url: `${baseLocacoesUrl}/${idLocacao}/itens-troca-produto`,
            type: 'GET',
            success: function (response) {
                const itens = Array.isArray(response?.itens) ? response.itens : [];
                if (!itens.length) {
                    $itemSelect.html('<option value="">Sem itens elegíveis para troca</option>');
                    atualizarCampoQuantidade();
                    atualizarInfoEstoqueDisponibilidade();
                    renderizarChecklistPatrimoniosNovo();
                    return;
                }

                let options = '<option value="">Selecione...</option>';
                itens.forEach((item) => {
                    const labelPatrimonio = item.patrimonio_codigo ? ` - Patrimônio: ${item.patrimonio_codigo}` : '';
                    options += `<option value="${item.id_produto_locacao}" data-id-produto="${item.id_produto}" data-qtd="${item.quantidade}" data-usa-patrimonio="${item.usa_patrimonio ? 1 : 0}" data-id-patrimonio="${item.id_patrimonio || ''}">${item.produto}${labelPatrimonio} (Qtd: ${item.quantidade})</option>`;
                });

                $itemSelect.html(options);
                atualizarCampoQuantidade();
                carregarDisponibilidadeNovoProduto();
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                $itemSelect.html('<option value="">Não foi possível carregar os itens</option>');
                atualizarCampoQuantidade();
                atualizarInfoEstoqueDisponibilidade();
                renderizarChecklistPatrimoniosNovo();
                Swal.fire('Erro', response.message || 'Falha ao buscar itens do contrato.', 'error');
            }
        });
    }

    $('#trocaContratoSelect').on('change', function () {
        carregarItensContrato($(this).val());
    });

    $('#trocaItemSelect').on('change', function () {
        atualizarCampoQuantidade();
        carregarDisponibilidadeNovoProduto();
    });

    $('#trocaNovoProdutoSelect').on('change', function () {
        carregarDisponibilidadeNovoProduto();
    });

    $('#trocaQuantidade').on('input change', function () {
        const max = Math.max(1, Number($(this).attr('max') || 1));
        const valor = Math.max(1, Number($(this).val() || 1));
        $(this).val(Math.min(max, valor));
        renderizarChecklistPatrimoniosNovo();
    });

    $(document).on('change', '.troca-patrimonio-checkbox', function () {
        atualizarEstadoCheckboxPatrimonios();
    });

    if ($('#trocaContratoSelect').val()) {
        carregarItensContrato($('#trocaContratoSelect').val());
    } else {
        atualizarInfoEstoqueDisponibilidade();
        renderizarChecklistPatrimoniosNovo();
    }

    $('#btnRegistrarTrocaProduto').on('click', function () {
        const idLocacao = $('#trocaContratoSelect').val();
        const idProdutoLocacao = $('#trocaItemSelect').val();
        const idProdutoNovo = $('#trocaNovoProdutoSelect').val();
        const quantidadeTroca = Math.max(1, Number($('#trocaQuantidade').val() || 1));
        const motivo = ($('#trocaMotivo').val() || '').trim();
        const observacoes = ($('#trocaObservacoes').val() || '').trim();
        const patrimoniosNovo = obterIdsPatrimoniosChecklistSelecionados();

        if (!idLocacao || !idProdutoLocacao || !idProdutoNovo) {
            Swal.fire('Atenção', 'Selecione contrato, item e novo produto.', 'warning');
            return;
        }

        const $itemOption = $('#trocaItemSelect option:selected');
        const $novoProdutoOption = $('#trocaNovoProdutoSelect option:selected');

        const idProdutoAtual = Number($itemOption.data('id-produto') || 0);
        const idPatrimonioAtual = Number($itemOption.data('id-patrimonio') || 0);
        const quantidadeMaxima = Math.max(1, Number($itemOption.data('qtd') || 1));
        const itemUsaPatrimonio = Number($itemOption.data('usa-patrimonio') || 0) === 1;
        const novoProdutoUsaPatrimonio = Number($novoProdutoOption.data('usa-patrimonio') || 0) === 1;

        if (itemUsaPatrimonio && quantidadeTroca !== 1) {
            Swal.fire('Atenção', 'Itens com patrimônio permitem troca unitária.', 'warning');
            return;
        }

        if (quantidadeTroca > quantidadeMaxima) {
            Swal.fire('Atenção', 'A quantidade da troca não pode ser maior que a quantidade do item atual.', 'warning');
            return;
        }

        if (novoProdutoUsaPatrimonio && patrimoniosNovo.length !== quantidadeTroca) {
            Swal.fire('Atenção', `Selecione exatamente ${quantidadeTroca} patrimônio(s) para o novo produto.`, 'warning');
            return;
        }

        if (!novoProdutoUsaPatrimonio && patrimoniosNovo.length > 0) {
            Swal.fire('Atenção', 'O novo produto selecionado não usa patrimônio.', 'warning');
            return;
        }

        if (idProdutoAtual > 0 && Number(idProdutoNovo) === idProdutoAtual) {
            if (!novoProdutoUsaPatrimonio) {
                Swal.fire('Atenção', 'Selecione um produto novo diferente do atual.', 'warning');
                return;
            }

            if (itemUsaPatrimonio && idPatrimonioAtual > 0 && patrimoniosNovo.includes(idPatrimonioAtual)) {
                Swal.fire('Atenção', 'Selecione um patrimônio diferente do patrimônio atual para concluir a troca.', 'warning');
                return;
            }
        }

        if (novoProdutoUsaPatrimonio) {
            const totalDisponiveis = $('#trocaPatrimoniosChecklist input.troca-patrimonio-checkbox').filter(function () {
                return Number($(this).data('backend-disabled') || 0) !== 1;
            }).length;

            if (totalDisponiveis < quantidadeTroca) {
                Swal.fire('Atenção', 'Não há patrimônios disponíveis suficientes para a quantidade informada.', 'warning');
                return;
            }
        }

        const quantidadeDisponivelPeriodo = Number(disponibilidadeAtual?.disponibilidade?.disponivel ?? NaN);
        if (Number.isFinite(quantidadeDisponivelPeriodo) && quantidadeTroca > quantidadeDisponivelPeriodo) {
            Swal.fire('Atenção', 'A disponibilidade atual para o período é menor que a quantidade informada para troca.', 'warning');
            return;
        }

        const idProdutoNovoNumero = Number(idProdutoNovo || 0);
        if (idProdutoNovoNumero <= 0) {
            Swal.fire('Atenção', 'Selecione um novo produto válido.', 'warning');
            return;
        }

        $.ajax({
            url: `{{ url('locacoes') }}/${idLocacao}/trocar-produto`,
            type: 'POST',
            data: {
                _token: csrfToken,
                id_produto_locacao: idProdutoLocacao,
                id_produto_novo: idProdutoNovoNumero,
                quantidade_troca: quantidadeTroca,
                patrimonios_novo: patrimoniosNovo,
                motivo,
                observacoes,
            },
            success: function (response) {
                Swal.fire('Sucesso', response.message || 'Troca registrada.', 'success').then(() => {
                    window.location.href = `{{ route('locacoes.trocas-produto') }}?id_locacao=${idLocacao}`;
                });
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                Swal.fire('Erro', response.message || 'Não foi possível registrar a troca.', 'error');
            }
        });
    });
});
</script>
@endsection
