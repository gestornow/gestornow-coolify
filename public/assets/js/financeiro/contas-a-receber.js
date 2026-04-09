/**
 * Contas a Receber - JavaScript
 * Gerenciamento de contas a receber
 */

// ============================================
// Variáveis Globais
// ============================================
let parcelasCache = {};
let recorrenciasCache = {};
let expandedAtual = null;

// ============================================
// Inicialização
// ============================================
function initContasAReceber(config) {
    // Configuração recebida do blade
    window.contasAReceberConfig = config;

    // Initialize Select2
    if ($('.select2').length) {
        $('.select2').select2({
            placeholder: 'Selecione...',
            allowClear: true
        });
    }

    // Inicializar gerenciamento de seleção
    initSelecaoMultipla();
}

// ============================================
// Exclusão de Conta Individual
// ============================================
function excluirConta(id, descricao) {
    Swal.fire({
        title: 'Confirmar exclusão',
        text: `Deseja realmente excluir a conta "${descricao}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#696cff',
        cancelButtonColor: '#8592a3',
        confirmButtonText: '<i class="ti ti-check me-1"></i>Sim, excluir!',
        cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
        customClass: {
            confirmButton: 'btn btn-primary me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/financeiro/contas-a-receber/${id}`;

            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = window.contasAReceberConfig.csrfToken;
            form.appendChild(csrfToken);

            const methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);

            document.body.appendChild(form);
            form.submit();
        }
    });
}

// ============================================
// Gerenciamento de Parcelas
// ============================================
function toggleParcelas(contaId, idParcelamento) {
    console.log('toggleParcelas chamado:', { contaId, idParcelamento });
    const row = document.getElementById(`parcelas-row-${contaId}`);
    const icon = document.getElementById(`icon-parcela-${contaId}`);

    if (!row) {
        console.error('Row de parcelas não encontrada:', contaId);
        return;
    }

    if (row.style.display === 'none' && expandedAtual && expandedAtual !== `parcelas-${contaId}`) {
        fecharExpandedAnterior(expandedAtual);
    }

    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        setTimeout(() => row.classList.add('show'), 10);
        if (icon) {
            icon.classList.remove('ti-list');
            icon.classList.add('ti-chevron-up');
        }
        expandedAtual = `parcelas-${contaId}`;

        if (!parcelasCache[idParcelamento]) {
            carregarParcelas(contaId, idParcelamento);
        } else {
            renderizarParcelas(contaId, parcelasCache[idParcelamento]);
        }
    } else {
        row.classList.remove('show');
        setTimeout(() => row.style.display = 'none', 300);
        if (icon) {
            icon.classList.remove('ti-chevron-up');
            icon.classList.add('ti-list');
        }
        expandedAtual = null;
    }
}

function carregarParcelas(contaId, idParcelamento) {
    console.log('Carregando parcelas:', { contaId, idParcelamento });
    
    fetch(`/financeiro/contas-a-receber/parcelas-data/${idParcelamento}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Dados recebidos:', data);
            if (data.success) {
                parcelasCache[idParcelamento] = data.parcelas;
                renderizarParcelas(contaId, data.parcelas);
            } else {
                console.error('Resposta sem sucesso:', data);
                document.getElementById(`parcelas-content-${contaId}`).innerHTML =
                    '<div class="alert alert-warning">Nenhuma parcela encontrada</div>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar parcelas:', error);
            document.getElementById(`parcelas-content-${contaId}`).innerHTML =
                `<div class="alert alert-danger">Erro ao carregar parcelas: ${error.message}</div>`;
        });
}

function renderizarParcelas(contaId, parcelas) {
    console.log('Renderizando parcelas:', { contaId, parcelas });
    const content = document.getElementById(`parcelas-content-${contaId}`);

    let html = '<div class="table-responsive"><table class="table table-sm">';
    html += '<thead><tr>';
    html += '<th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th>';
    html += '</tr></thead><tbody>';

    parcelas.forEach(parcela => {
        const statusClass = getStatusClass(parcela.status);
        const vencido = isVencida(parcela.data_vencimento, parcela.status);
        const isParcelaAtual = parcela.id_contas == contaId;
        const rowClass = isParcelaAtual ? 'conta-atual-row' : '';
        const isPago = parcela.status === 'pago';
        const isParcial = parcela.valor_pago > 0 && parcela.valor_pago < parcela.valor_total;

        html += `<tr class="${rowClass}">`;
        html += `<td><strong>${parcela.numero_parcela}/${parcela.total_parcelas}</strong>${isParcelaAtual ? ' <span class="badge bg-primary badge-sm badge-atual">Atual</span>' : ''}</td>`;
        html += `<td>${formatDate(parcela.data_vencimento)}${vencido ? '<br><small class="text-danger"><i class="ti ti-alert-circle"></i> Vencida</small>' : ''}</td>`;
        html += `<td>
            R$ ${formatMoney(parcela.valor_total)}
            ${isParcial ? `<br><small class="text-success">Recebido: R$ ${formatMoney(parcela.valor_pago)}</small><br><small class="text-warning">Restante: R$ ${formatMoney(parcela.valor_total - parcela.valor_pago)}</small>` : ''}
        </td>`;
        html += `<td><span class="badge ${statusClass}">${getStatusLabel(parcela.status)}</span></td>`;
        html += `<td>`;
        
        // Botão de histórico se houver recebimentos
        if (parcela.valor_pago > 0) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-outline-info me-1" 
                        onclick="verHistoricoRecebimentos(${parcela.id_contas}, '${parcela.descricao}')" 
                        title="Ver recebimentos realizados">
                        <i class="ti ti-receipt"></i>
                    </button>`;
        }
        
        // Botão de dar baixa se não estiver totalmente pago
        if (!isPago) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-outline-success me-1" 
                        onclick="abrirModalBaixa(${parcela.id_contas}, '${parcela.descricao}', ${parcela.valor_total}, ${parcela.valor_pago || 0})" 
                        title="Dar baixa">
                        <i class="ti ti-cash"></i>
                    </button>`;
        }
        
        // Botão de editar
        html += `<a href="/financeiro/contas-a-receber/${parcela.id_contas}/edit" 
                    class="btn btn-sm btn-icon btn-outline-primary" 
                    title="Editar">
                    <i class="ti ti-edit"></i>
                </a>`;
        
        html += `</td>`;
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    content.innerHTML = html;
}

// ============================================
// Gerenciamento de Recorrências
// ============================================
function toggleRecorrencias(contaId, idRecorrencia) {
    const row = document.getElementById(`recorrencias-row-${contaId}`);
    const icon = document.getElementById(`icon-recorrencia-${contaId}`);

    if (!row) {
        console.error('Row de recorrências não encontrada:', contaId);
        return;
    }

    if (row.style.display === 'none' && expandedAtual && expandedAtual !== `recorrencias-${contaId}`) {
        fecharExpandedAnterior(expandedAtual);
    }

    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        setTimeout(() => row.classList.add('show'), 10);
        if (icon) {
            icon.classList.remove('ti-repeat');
            icon.classList.add('ti-chevron-up');
        }
        expandedAtual = `recorrencias-${contaId}`;

        if (!recorrenciasCache[idRecorrencia]) {
            carregarRecorrencias(contaId, idRecorrencia);
        } else {
            renderizarRecorrencias(contaId, recorrenciasCache[idRecorrencia]);
        }
    } else {
        row.classList.remove('show');
        setTimeout(() => row.style.display = 'none', 300);
        if (icon) {
            icon.classList.remove('ti-chevron-up');
            icon.classList.add('ti-repeat');
        }
        expandedAtual = null;
    }
}

function carregarRecorrencias(contaId, idRecorrencia) {
    fetch(`/financeiro/contas-a-receber/recorrencias-data/${idRecorrencia}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                recorrenciasCache[idRecorrencia] = data.recorrencias;
                renderizarRecorrencias(contaId, data.recorrencias);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar recorrências:', error);
            document.getElementById(`recorrencias-content-${contaId}`).innerHTML =
                '<div class="alert alert-danger">Erro ao carregar recorrências</div>';
        });
}

function renderizarRecorrencias(contaId, recorrencias) {
    const content = document.getElementById(`recorrencias-content-${contaId}`);

    let html = '<div class="table-responsive"><table class="table table-sm">';
    html += '<thead><tr>';
    html += '<th>#</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th>';
    html += '</tr></thead><tbody>';

    recorrencias.forEach((recorrencia, index) => {
        const statusClass = getStatusClass(recorrencia.status);
        const vencido = isVencida(recorrencia.data_vencimento, recorrencia.status);
        const isRecorrenciaAtual = recorrencia.id_contas == contaId;
        const rowClass = isRecorrenciaAtual ? 'conta-atual-warning' : '';
        const isPago = recorrencia.status === 'pago';
        const isParcial = recorrencia.valor_pago > 0 && recorrencia.valor_pago < recorrencia.valor_total;

        html += `<tr class="${rowClass}">`;
        html += `<td><strong>${index + 1}</strong>${isRecorrenciaAtual ? ' <span class="badge bg-warning badge-sm badge-atual">Atual</span>' : ''}</td>`;
        html += `<td>${formatDate(recorrencia.data_vencimento)}${vencido ? '<br><small class="text-danger"><i class="ti ti-alert-circle"></i> Vencida</small>' : ''}</td>`;
        html += `<td>
            R$ ${formatMoney(recorrencia.valor_total)}
            ${isParcial ? `<br><small class="text-success">Recebido: R$ ${formatMoney(recorrencia.valor_pago)}</small><br><small class="text-warning">Restante: R$ ${formatMoney(recorrencia.valor_total - recorrencia.valor_pago)}</small>` : ''}
        </td>`;
        html += `<td><span class="badge ${statusClass}">${getStatusLabel(recorrencia.status)}</span></td>`;
        html += `<td>`;
        
        // Botão de histórico se houver recebimentos
        if (recorrencia.valor_pago > 0) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-outline-info me-1" 
                        onclick="verHistoricoRecebimentos(${recorrencia.id_contas}, '${recorrencia.descricao}')" 
                        title="Ver recebimentos realizados">
                        <i class="ti ti-receipt"></i>
                    </button>`;
        }
        
        // Botão de dar baixa se não estiver totalmente pago
        if (!isPago) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-outline-success me-1" 
                        onclick="abrirModalBaixa(${recorrencia.id_contas}, '${recorrencia.descricao}', ${recorrencia.valor_total}, ${recorrencia.valor_pago || 0})" 
                        title="Dar baixa">
                        <i class="ti ti-cash"></i>
                    </button>`;
        }
        
        // Botão de editar
        html += `<a href="/financeiro/contas-a-receber/${recorrencia.id_contas}/edit" 
                    class="btn btn-sm btn-icon btn-outline-primary" 
                    title="Editar">
                    <i class="ti ti-edit"></i>
                </a>`;
        
        html += `</td>`;
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    content.innerHTML = html;
}

function fecharExpandedAnterior(expandedId) {
    const [tipo, contaId] = expandedId.split('-');
    const row = document.getElementById(`${tipo}-row-${contaId}`);
    const icon = document.getElementById(`icon-${tipo === 'parcelas' ? 'parcela' : 'recorrencia'}-${contaId}`);

    if (row) {
        row.classList.remove('show');
        setTimeout(() => row.style.display = 'none', 300);
        
        if (icon) {
            icon.classList.remove('ti-chevron-up');
            if (tipo === 'parcelas') {
                icon.classList.add('ti-list');
            } else {
                icon.classList.add('ti-repeat');
            }
        }
    }
}

// ============================================
// Modal de Dar Baixa
// ============================================
function abrirModalBaixa(idConta, descricao, valorTotal, valorPago = 0) {
    const config = window.contasAReceberConfig;
    const valorRestante = valorTotal - valorPago;

    Swal.fire({
        title: 'Dar Baixa na Conta',
        html: gerarHtmlModalBaixa(descricao, valorTotal, valorRestante, valorPago, config),
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-check me-1"></i>Confirmar Recebimento',
        cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
        customClass: {
            confirmButton: 'btn btn-success me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false,
        width: '600px',
        didOpen: () => setupModalBaixa(valorRestante),
        preConfirm: () => validarModalBaixa(valorRestante)
    }).then((result) => {
        if (result.isConfirmed) {
            darBaixa(idConta, result.value);
        }
    });
}

function gerarHtmlModalBaixa(descricao, valorTotal, valorRestante, valorPago, config) {
    let formasPagamentoOptions = '<option value="">Selecione...</option>';
    config.formasPagamento.forEach(forma => {
        formasPagamentoOptions += `<option value="${forma.id_forma_pagamento}">${forma.nome}</option>`;
    });

    let bancosOptions = '<option value="">Selecione...</option>';
    config.bancos.forEach(banco => {
        bancosOptions += `<option value="${banco.id_bancos}">${banco.nome_banco}</option>`;
    });

    let infoHtml = `
        <div class="text-start">
            <p class="mb-3"><strong>Conta:</strong> ${descricao}</p>
            <div class="alert alert-info mb-3">
                <strong>Valor Total:</strong> R$ ${valorTotal.toFixed(2).replace('.', ',')}`;
    
    if (valorPago > 0) {
        infoHtml += `<br><strong>Já Recebido:</strong> R$ ${valorPago.toFixed(2).replace('.', ',')}`;
        infoHtml += `<br><strong class="text-primary">Valor Restante:</strong> R$ ${valorRestante.toFixed(2).replace('.', ',')}`;
    }
    
    infoHtml += `
            </div>
            
            <div class="mb-3">
                <label class="form-label">Data de Recebimento <span class="text-danger">*</span></label>
                <input type="date" id="data_pagamento" class="form-control" value="${new Date().toISOString().split('T')[0]}" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Valor a Receber <span class="text-danger">*</span></label>
                <input type="text" id="valor_pago" class="form-control" value="" required>
                <small class="text-muted">Se receber valor menor que o restante, a conta continuará como parcialmente recebida</small>
            </div>
            
            <div id="alerta_parcial" class="alert alert-warning mb-3" style="display: none;">
                <i class="ti ti-alert-triangle me-1"></i>
                <strong>Atenção:</strong> Valor a receber é menor que o restante. A conta continuará como <strong>Parcialmente Recebida</strong>.
            </div>
            <div id="alerta_maior" class="alert alert-danger mb-3" style="display: none;">
                <i class="ti ti-alert-circle me-1"></i>
                <strong>Erro:</strong> O valor a receber não pode ser maior que o valor restante da conta.
            </div>
            
            <div class="mb-3">
                <label class="form-label">Forma de Pagamento</label>
                <select id="id_forma_pagamento" class="form-select">
                    ${formasPagamentoOptions}
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Banco</label>
                <select id="id_bancos" class="form-select">
                    ${bancosOptions}
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Observações</label>
                <textarea id="observacoes" class="form-control" rows="3"></textarea>
            </div>
        </div>
    `;
    
    return infoHtml;
}

function setupModalBaixa(valorRestante) {
    const valorInput = document.getElementById('valor_pago');

    // Definir valor formatado
    if (window.formatFloatToMoney) {
        valorInput.value = window.formatFloatToMoney(valorRestante);
    } else {
        valorInput.value = valorRestante.toFixed(2).replace('.', ',');
    }

    // Aplicar máscara monetária
    if (window.applyMoneyMask) {
        window.applyMoneyMask(valorInput);
    }

    // Monitorar mudanças no valor recebido para mostrar alerta
    valorInput.addEventListener('input', function () {
        const valorDigitado = window.parseMoneyToFloat
            ? window.parseMoneyToFloat(this.value)
            : parseFloat(this.value.replace(/\./g, '').replace(',', '.'));

        const alertaParcial = document.getElementById('alerta_parcial');
        const alertaMaior = document.getElementById('alerta_maior');

        if (valorDigitado > valorRestante) {
            alertaMaior.style.display = 'block';
            alertaParcial.style.display = 'none';
        } else if (valorDigitado > 0 && valorDigitado < valorRestante) {
            alertaParcial.style.display = 'block';
            alertaMaior.style.display = 'none';
        } else {
            alertaParcial.style.display = 'none';
            alertaMaior.style.display = 'none';
        }
    });
}

function validarModalBaixa(valorRestante) {
    const dataPagamento = document.getElementById('data_pagamento').value;
    const valorPago = document.getElementById('valor_pago').value;
    const idFormaPagamento = document.getElementById('id_forma_pagamento').value;
    const idBancos = document.getElementById('id_bancos').value;
    const observacoes = document.getElementById('observacoes').value;

    if (!dataPagamento || !valorPago) {
        Swal.showValidationMessage('Por favor, preencha os campos obrigatórios');
        return false;
    }

    // Converter valor de formato BR para decimal
    let valorDecimal = window.parseMoneyToFloat
        ? window.parseMoneyToFloat(valorPago)
        : parseFloat(valorPago.replace(/\./g, '').replace(',', '.'));

    if (valorDecimal > valorRestante) {
        Swal.showValidationMessage(`O valor a receber (R$ ${valorDecimal.toFixed(2).replace('.', ',')}) não pode ser maior que o valor restante (R$ ${valorRestante.toFixed(2).replace('.', ',')})`);
        return false;
    }

    if (valorDecimal <= 0) {
        Swal.showValidationMessage('O valor recebido deve ser maior que zero');
        return false;
    }

    return {
        data_pagamento: dataPagamento,
        valor_pago: valorDecimal,
        id_forma_pagamento: idFormaPagamento || null,
        id_bancos: idBancos || null,
        observacoes: observacoes || null
    };
}

function darBaixa(idConta, dados) {
    $.ajax({
        url: `/financeiro/contas-a-receber/${idConta}/dar-baixa`,
        method: 'POST',
        data: dados,
        success: function (response) {
            if (response.success) {
                mostrarSucesso(response.message);
                setTimeout(() => location.reload(), 2000);
            }
        },
        error: function (xhr) {
            mostrarErro(xhr.responseJSON?.message || 'Erro ao dar baixa na conta.');
        }
    });
}

// ============================================
// Seleção Múltipla e Exclusão em Lote
// ============================================
function initSelecaoMultipla() {
    const $checkAll = $('#checkAll');
    const $checkItems = $('.check-item');
    const $btnExcluirSelecionados = $('#btnExcluirSelecionados');
    const $countSelecionados = $('#countSelecionados');

    $checkAll.on('change', function () {
        const isChecked = $(this).is(':checked');
        $checkItems.prop('checked', isChecked);
        updateDeleteButton();
    });

    $checkItems.on('change', function () {
        updateCheckAllState();
        updateDeleteButton();
    });

    $btnExcluirSelecionados.on('click', function () {
        const checkedItems = $checkItems.filter(':checked');
        const ids = checkedItems.map(function () {
            return $(this).val();
        }).get();

        if (ids.length === 0) return;

        Swal.fire({
            title: 'Confirmar exclusão múltipla',
            text: `Você está prestes a excluir ${ids.length} conta(s). Esta ação não pode ser desfeita!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#8592a3',
            confirmButtonText: '<i class="ti ti-check me-1"></i>Sim, excluir!',
            cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                excluirMultiplos(ids, $btnExcluirSelecionados.data('url'));
            }
        });
    });

    function updateCheckAllState() {
        const totalItems = $checkItems.length;
        const checkedItems = $checkItems.filter(':checked').length;

        $checkAll.prop('checked', totalItems > 0 && checkedItems === totalItems);
        $checkAll.prop('indeterminate', checkedItems > 0 && checkedItems < totalItems);
    }

    function updateDeleteButton() {
        const count = $checkItems.filter(':checked').length;

        if (count > 0) {
            $btnExcluirSelecionados.show();
            $countSelecionados.text(count);
        } else {
            $btnExcluirSelecionados.hide();
        }
    }
}

function excluirMultiplos(ids, url) {
    $.ajax({
        url: url,
        method: 'POST',
        data: {
            _token: window.contasAReceberConfig.csrfToken,
            ids: ids
        },
        success: function (response) {
            if (response.success) {
                mostrarSucesso(response.message);
                setTimeout(() => location.reload(), 2000);
            }
        },
        error: function (xhr) {
            mostrarErro(xhr.responseJSON?.message || 'Erro ao excluir contas.');
        }
    });
}

// ============================================
// Histórico de Recebimentos
// ============================================
function verHistoricoRecebimentos(idConta, descricao) {
    $.ajax({
        url: `/financeiro/contas-a-receber/${idConta}/historico-recebimentos`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                mostrarModalHistorico(descricao, response);
            }
        },
        error: function(xhr) {
            mostrarErro(xhr.responseJSON?.message || 'Erro ao carregar histórico de recebimentos.');
        }
    });
}

function mostrarModalHistorico(descricao, data) {
    // Armazenar dados para uso posterior
    window.currentHistoricoData = { idConta: data.id_conta, descricao: descricao };
    
    let html = `
        <div class="text-start">
            <p class="mb-3 text-center"><strong>Conta:</strong> ${descricao}</p>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="alert alert-info mb-0">
                        <small>Valor Total</small><br>
                        <strong>R$ ${formatMoney(data.valor_total)}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-success mb-0">
                        <small>Total Recebido</small><br>
                        <strong>R$ ${formatMoney(data.total_pago)}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning mb-0">
                        <small>Restante</small><br>
                        <strong>R$ ${formatMoney(data.valor_restante)}</strong>
                    </div>
                </div>
            </div>
            
            <h6 class="mb-2 text-center">Histórico de Recebimentos</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Forma Pagamento</th>
                            <th>Banco</th>
                            <th>Usuário</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>`;
    
    if (data.recebimentos.length === 0) {
        html += '<tr><td colspan="6" class="text-center text-muted">Nenhum recebimento registrado</td></tr>';
    } else {
        data.recebimentos.forEach(recebimento => {
            html += `
                <tr>
                    <td>${recebimento.data_pagamento}</td>
                    <td><strong class="text-success">R$ ${formatMoney(recebimento.valor_pago)}</strong></td>
                    <td>${recebimento.forma_pagamento}</td>
                    <td>${recebimento.banco}</td>
                    <td>
                        <small>${recebimento.usuario}</small><br>
                        <small class="text-muted">${recebimento.created_at}</small>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-icon btn-outline-danger" 
                                onclick="excluirRecebimentoParcial(${data.id_conta}, ${recebimento.id})" 
                                title="Excluir recebimento">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>`;
            
            if (recebimento.observacoes) {
                html += `
                    <tr class="table-active">
                        <td colspan="6">
                            <small class="text-muted">
                                <i class="ti ti-note me-1"></i>
                                ${recebimento.observacoes}
                            </small>
                        </td>
                    </tr>`;
            }
        });
    }
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    Swal.fire({
        title: '<div class="text-center">Histórico de Recebimentos</div>',
        html: html,
        showCancelButton: false,
        showConfirmButton: false,
        showCloseButton: true,
        buttonsStyling: false,
        width: '900px'
    });
}

// ============================================
// Log de Atividades
// ============================================
function verLogAtividadesConta(idConta, descricao) {
    $.ajax({
        url: `/financeiro/contas-a-receber/${idConta}/logs-atividades`,
        method: 'GET',
        success: function (response) {
            if (response.success) {
                mostrarModalLogAtividades(descricao, response.logs || []);
            } else {
                mostrarErro(response.message || 'Não foi possível carregar o log de atividades.');
            }
        },
        error: function (xhr) {
            mostrarErro(xhr.responseJSON?.message || 'Erro ao carregar o log de atividades.');
        }
    });
}

function mostrarModalLogAtividades(descricao, logs) {
    const html = gerarHtmlLogAtividades(descricao, logs);

    Swal.fire({
        title: '<div class="text-center fw-bold mb-0">Log de Atividades</div>',
        html: html,
        showCancelButton: false,
        showConfirmButton: false,
        showCloseButton: true,
        buttonsStyling: false,
        width: '1100px',
        customClass: {
            popup: 'p-0',
            htmlContainer: 'm-0 p-0',
            title: 'pt-4 pb-0'
        }
    });
}

function gerarHtmlLogAtividades(descricao, logs) {
    const totalLogs = Array.isArray(logs) ? logs.length : 0;

    let html = `
        <div class="text-start p-4 border-bottom bg-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Conta</div>
                    <div class="fw-bold mb-0" style="font-size: 1.05rem; line-height: 1.4;">${escapeHtml(descricao || '-')}</div>
                </div>
                <div>
                    <span class="badge bg-label-primary fw-semibold px-3 py-2" style="font-size: 0.85rem;">
                        <i class="ti ti-list-check me-1"></i>${totalLogs} registro${totalLogs !== 1 ? 's' : ''}
                    </span>
                </div>
            </div>
        </div>
    `;

    if (!logs || logs.length === 0) {
        html += `
            <div class="p-4">
                <div class="alert alert-info mb-0 text-center rounded-3" style="padding: 2rem;">
                    <i class="ti ti-info-circle mb-2" style="font-size: 2rem;"></i>
                    <div class="fw-semibold">Nenhum log de atividade encontrado</div>
                    <div class="text-muted small mt-1">Esta conta ainda não possui histórico de atividades.</div>
                </div>
            </div>
        `;
        return html;
    }

    html += '<div class="bg-body-secondary" style="max-height: 600px; overflow-y: auto; padding: 1.5rem;">';

    logs.forEach((item, index) => {
        const cor = normalizarCorLog(item.cor);
        const icone = item.icone || 'activity';
        const responsavel = escapeHtml(item.nome_responsavel || item.email_responsavel || 'Sistema');
        const dataHora = formatDateTime(item.ocorrido_em);
        const acao = formatarAcaoLog(item.acao || '-');
        const descricaoItem = escapeHtml(item.descricao || 'Atividade registrada');
        const resumoContexto = gerarResumoContextoLog(item.contexto || {});

        const temAntes = item.antes && Object.keys(item.antes).length > 0;
        const temDepois = item.depois && Object.keys(item.depois).length > 0;

        html += `
            <div class="card mb-3 shadow-sm position-relative" style="border-left: 5px solid var(--bs-${cor}) !important;">
                <div class="position-absolute top-0 end-0 mt-3 me-3">
                    <span class="badge bg-label-secondary" style="font-size: 0.7rem;">#${logs.length - index}</span>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="avatar avatar-md bg-label-${cor} flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="ti ti-${icone}" style="font-size: 1.5rem;"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <h6 class="mb-2 fw-bold" style="line-height: 1.4;">${descricaoItem}</h6>
                            <div class="d-flex flex-wrap gap-3 text-muted small">
                                <span class="d-flex align-items-center">
                                    <i class="ti ti-user me-1" style="font-size: 1rem;"></i>
                                    <span class="fw-medium">${responsavel}</span>
                                </span>
                                <span class="d-flex align-items-center">
                                    <i class="ti ti-calendar-event me-1" style="font-size: 1rem;"></i>
                                    <span>${dataHora}</span>
                                </span>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-${cor} fw-semibold" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                                    ${acao}
                                </span>
                            </div>
                        </div>
                    </div>
                    ${resumoContexto ? `
                        <div class="border-top pt-3 mt-3">
                            ${resumoContexto}
                        </div>
                    ` : ''}
                    ${(temAntes || temDepois) ? `
                        <div class="border-top pt-3 mt-3">
                            <div class="row g-3">
                                ${temAntes ? `
                                    <div class="col-md-${temDepois ? '6' : '12'}">
                                        <div class="p-3 rounded-3 h-100 bg-label-danger border border-danger border-opacity-25">
                                            <div class="d-flex align-items-center mb-3">
                                                <span class="avatar avatar-xs bg-label-danger me-2">
                                                    <i class="ti ti-arrow-left" style="font-size: 0.8rem;"></i>
                                                </span>
                                                <strong class="text-danger" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Antes</strong>
                                            </div>
                                            <div class="small" style="line-height: 1.8;">
                                                ${formatarObjetoDetalhado(item.antes)}
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                                ${temDepois ? `
                                    <div class="col-md-${temAntes ? '6' : '12'}">
                                        <div class="p-3 rounded-3 h-100 bg-label-success border border-success border-opacity-25">
                                            <div class="d-flex align-items-center mb-3">
                                                <span class="avatar avatar-xs bg-label-success me-2">
                                                    <i class="ti ti-arrow-right" style="font-size: 0.8rem;"></i>
                                                </span>
                                                <strong class="text-success" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Depois</strong>
                                            </div>
                                            <div class="small" style="line-height: 1.8;">
                                                ${formatarObjetoDetalhado(item.depois)}
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    });

    html += '</div>';
    return html;
}

function normalizarCorLog(cor) {
    const mapa = {
        'verde': 'success',
        'verde-escuro': 'success',
        'amarelo': 'warning',
        'vermelho': 'danger',
        'vermelho-escuro': 'danger',
        'azul': 'primary',
        'azul-escuro': 'info',
        'ciano': 'info',
        'laranja': 'warning'
    };

    return mapa[cor] || 'primary';
}

function formatarObjetoDetalhado(obj) {
    if (!obj || typeof obj !== 'object') {
        return '<span class="text-muted fst-italic">Sem dados</span>';
    }

    const entries = Object.entries(obj);
    if (entries.length === 0) {
        return '<span class="text-muted fst-italic">Sem alterações</span>';
    }

    let html = '<div class="d-flex flex-column gap-2">';

    entries.forEach(([chave, valor]) => {
        const chaveFormatada = chave
            .replace(/_/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());

        let valorFormatado;
        if (valor === null || valor === undefined || valor === '') {
            valorFormatado = '<span class="text-muted fst-italic">(vazio)</span>';
        } else if (typeof valor === 'boolean') {
            valorFormatado = valor ? '<span class="badge bg-success">Sim</span>' : '<span class="badge bg-secondary">Não</span>';
        } else if (ehCampoMonetario(chave) && valorEhNumero(valor)) {
            valorFormatado = `<span class="fw-medium">${formatarMoedaLog(Number(valor))}</span>`;
        } else if (ehCampoPercentual(chave) && valorEhNumero(valor)) {
            valorFormatado = `<span class="fw-medium">${Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%</span>`;
        } else if (typeof valor === 'number') {
            valorFormatado = `<span class="fw-medium">${valor.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}</span>`;
        } else {
            const valorStr = String(valor);
            if (ehDataISO(valorStr)) {
                valorFormatado = `<span class="text-break">${escapeHtml(formatDateTime(valorStr))}</span>`;
            } else if (valorStr.length > 80) {
                valorFormatado = `<span class="text-break">${escapeHtml(valorStr.substring(0, 80))}...</span>`;
            } else {
                valorFormatado = `<span class="text-break">${escapeHtml(valorStr)}</span>`;
            }
        }

        html += `
            <div class="d-flex gap-2">
                <span class="fw-semibold text-nowrap text-body-secondary" style="min-width: 120px;">${escapeHtml(chaveFormatada)}:</span>
                <span class="flex-grow-1">${valorFormatado}</span>
            </div>
        `;
    });

    html += '</div>';
    return html;
}

function gerarResumoContextoLog(contexto) {
    if (!contexto || typeof contexto !== 'object') {
        return '';
    }

    const valorBaixa = contexto.valor_baixa ?? contexto.valor_recebido_parcela;
    const restanteDepois = contexto.valor_restante_depois ?? contexto.saldo_restante_depois;

    const temDadosBaixa = valorBaixa !== undefined
        || contexto.valor_pago_antes !== undefined
        || contexto.valor_pago_depois !== undefined
        || restanteDepois !== undefined;

    if (!temDadosBaixa) {
        return '';
    }

    const tipoBaixa = contexto.tipo_baixa === 'total' ? 'Total' : 'Parcial';
    const linhas = [
        { label: 'Tipo', valor: tipoBaixa },
        { label: 'Valor da baixa', valor: formatarMoedaLog(valorBaixa) },
        { label: 'Pago antes', valor: formatarMoedaLog(contexto.valor_pago_antes) },
        { label: 'Pago depois', valor: formatarMoedaLog(contexto.valor_pago_depois) },
        { label: 'Restante', valor: formatarMoedaLog(restanteDepois) },
        { label: 'Forma', valor: contexto.forma_pagamento || '-' },
        { label: 'Banco', valor: contexto.banco || '-' },
    ];

    return `
        <div class="bg-label-info rounded-3 p-3 border border-info border-opacity-25">
            <div class="small fw-semibold text-info mb-2 text-uppercase" style="letter-spacing: .4px;">Resumo da baixa</div>
            <div class="row g-2">
                ${linhas.map((linha) => `
                    <div class="col-md-6">
                        <div class="small d-flex justify-content-between gap-2">
                            <span class="text-body-secondary">${escapeHtml(linha.label)}</span>
                            <span class="fw-semibold text-end">${escapeHtml(String(linha.valor ?? '-'))}</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function formatarMoedaLog(valor) {
    if (!valorEhNumero(valor)) return '-';
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function valorEhNumero(valor) {
    if (typeof valor === 'number') return Number.isFinite(valor);
    if (typeof valor === 'string') {
        const normalizado = valor.trim().replace(',', '.');
        return normalizado !== '' && !Number.isNaN(Number(normalizado));
    }
    return false;
}

function ehCampoMonetario(chave) {
    return /valor|total|restante|saldo|multa|juros|desconto|pago/i.test(String(chave || ''));
}

function ehCampoPercentual(chave) {
    return /percentual|porcentagem/i.test(String(chave || ''));
}

function ehDataISO(valor) {
    return /^\d{4}-\d{2}-\d{2}(?:[T\s].*)?$/.test(String(valor || ''));
}

function formatarAcaoLog(acao) {
    if (!acao || acao === '-') return '-';
    return String(acao).replace(/[_\.]+/g, ' ').trim();
}

function escapeHtml(texto) {
    return String(texto)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// Função para excluir recebimento parcial
function excluirRecebimentoParcial(idConta, idRecebimento) {
    Swal.fire({
        title: 'Confirmar exclusão',
        text: 'Deseja realmente excluir este recebimento? O valor será deduzido do total recebido da conta.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#8592a3',
        confirmButtonText: '<i class="ti ti-trash me-1"></i>Sim, excluir!',
        cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            const token = $('meta[name="csrf-token"]').attr('content');
            
            $.ajax({
                url: `/financeiro/contas-a-receber/${idConta}/recebimentos/${idRecebimento}`,
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                success: function(response) {
                    if (response.success) {
                        mostrarSucesso(response.message);
                        setTimeout(() => location.reload(), 1500);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 419 || xhr.status === 401) {
                        // Usuário não está logado
                        Swal.fire({
                            title: 'Não autenticado',
                            text: 'Faça login para continuar.',
                            icon: 'info',
                            confirmButtonText: 'Fazer Login',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        }).then(() => {
                            window.location.href = '/login';
                        });
                    } else {
                        mostrarErro(xhr.responseJSON?.message || 'Erro ao excluir recebimento.');
                    }
                }
            });
        }
    });
}

// ============================================
// Funções Auxiliares
// ============================================
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('pt-BR');
}

function formatMoney(value) {
    return parseFloat(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function getStatusClass(status) {
    const classes = {
        'pago': 'bg-success',
        'pendente': 'bg-warning',
        'vencido': 'bg-danger',
        'parcelado': 'bg-info',
        'cancelado': 'bg-secondary'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusLabel(status) {
    const labels = {
        'pago': 'Recebido',
        'pendente': 'Pendente',
        'vencido': 'Vencido',
        'parcelado': 'Parcelado',
        'cancelado': 'Cancelado'
    };
    return labels[status] || status;
}

function isVencida(dataVencimento, status) {
    if (status === 'pago' || status === 'cancelado') return false;
    const hoje = new Date();
    const vencimento = new Date(dataVencimento);
    return vencimento < hoje;
}

function mostrarSucesso(mensagem) {
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: mensagem,
        showConfirmButton: true,
        confirmButtonText: 'OK',
        timer: 2000,
        timerProgressBar: true,
        customClass: {
            confirmButton: 'btn btn-primary'
        },
        buttonsStyling: false
    });
}

function mostrarErro(mensagem) {
    Swal.fire({
        icon: 'error',
        title: 'Erro!',
        text: mensagem,
        showConfirmButton: true,
        confirmButtonText: 'OK',
        customClass: {
            confirmButton: 'btn btn-primary'
        },
        buttonsStyling: false
    });
}

// ============================================
// Gerar HTML do Modal de Baixa
// ============================================
function gerarHtmlModalBaixa(descricao, valorTotal, valorRestante, valorPago, config) {
    let formasPagamentoOptions = '<option value="">Selecione...</option>';
    config.formasPagamento.forEach(forma => {
        formasPagamentoOptions += `<option value="${forma.id_forma_pagamento}">${forma.nome}</option>`;
    });

    let bancosOptions = '<option value="">Selecione...</option>';
    config.bancos.forEach(banco => {
        bancosOptions += `<option value="${banco.id_bancos}">${banco.nome_banco}</option>`;
    });

    let infoHtml = `
        <div class="text-start">
            <p class="mb-3"><strong>Conta:</strong> ${descricao}</p>
            <div class="alert alert-info mb-3">
                <strong>Valor Total:</strong> R$ ${valorTotal.toFixed(2).replace('.', ',')}`;
    
    if (valorPago > 0) {
        infoHtml += `<br><strong>Já Recebido:</strong> R$ ${valorPago.toFixed(2).replace('.', ',')}`;
        infoHtml += `<br><strong class="text-primary">Valor Restante:</strong> R$ ${valorRestante.toFixed(2).replace('.', ',')}`;
    }
    
    infoHtml += `
            </div>
            
            <div class="mb-3">
                <label class="form-label">Data de Recebimento <span class="text-danger">*</span></label>
                <input type="date" id="data_pagamento" class="form-control" value="${new Date().toISOString().split('T')[0]}" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Valor a Receber <span class="text-danger">*</span></label>
                <input type="text" id="valor_pago" class="form-control" value="" required>
                <small class="text-muted">Se receber valor menor que o restante, a conta continuará como parcialmente recebida</small>
            </div>
            
            <div id="alerta_parcial" class="alert alert-warning mb-3" style="display: none;">
                <i class="ti ti-alert-triangle me-1"></i>
                <strong>Atenção:</strong> Valor a receber é menor que o restante. A conta continuará como <strong>Parcialmente Recebida</strong>.
            </div>
            <div id="alerta_maior" class="alert alert-danger mb-3" style="display: none;">
                <i class="ti ti-alert-circle me-1"></i>
                <strong>Erro:</strong> O valor a receber não pode ser maior que o valor restante da conta.
            </div>
            
            <div class="mb-3">
                <label class="form-label">Forma de Pagamento</label>
                <select id="id_forma_pagamento" class="form-select">
                    ${formasPagamentoOptions}
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Banco</label>
                <select id="id_bancos" class="form-select">
                    ${bancosOptions}
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Observações</label>
                <textarea id="observacoes" class="form-control" rows="3"></textarea>
            </div>
        </div>
    `;
    
    return infoHtml;
}

function setupModalBaixa(valorRestante) {
    const valorInput = document.getElementById('valor_pago');

    // Definir valor formatado
    if (window.formatFloatToMoney) {
        valorInput.value = window.formatFloatToMoney(valorRestante);
    } else {
        valorInput.value = valorRestante.toFixed(2).replace('.', ',');
    }

    // Aplicar máscara monetária
    if (window.applyMoneyMask) {
        window.applyMoneyMask(valorInput);
    }

    // Monitorar mudanças no valor recebido para mostrar alerta
    valorInput.addEventListener('input', function () {
        const valorDigitado = window.parseMoneyToFloat
            ? window.parseMoneyToFloat(this.value)
            : parseFloat(this.value.replace(/\./g, '').replace(',', '.'));

        const alertaParcial = document.getElementById('alerta_parcial');
        const alertaMaior = document.getElementById('alerta_maior');

        if (valorDigitado > valorRestante) {
            alertaMaior.style.display = 'block';
            alertaParcial.style.display = 'none';
        } else if (valorDigitado > 0 && valorDigitado < valorRestante) {
            alertaParcial.style.display = 'block';
            alertaMaior.style.display = 'none';
        } else {
            alertaParcial.style.display = 'none';
            alertaMaior.style.display = 'none';
        }
    });
}

function validarModalBaixa(valorRestante) {
    const dataPagamento = document.getElementById('data_pagamento').value;
    const valorPago = document.getElementById('valor_pago').value;
    const idFormaPagamento = document.getElementById('id_forma_pagamento').value;
    const idBancos = document.getElementById('id_bancos').value;
    const observacoes = document.getElementById('observacoes').value;

    if (!dataPagamento || !valorPago) {
        Swal.showValidationMessage('Por favor, preencha os campos obrigatórios');
        return false;
    }

    // Converter valor de formato BR para decimal
    let valorDecimal = window.parseMoneyToFloat
        ? window.parseMoneyToFloat(valorPago)
        : parseFloat(valorPago.replace(/\./g, '').replace(',', '.'));

    if (valorDecimal > valorRestante) {
        Swal.showValidationMessage(`O valor a receber (R$ ${valorDecimal.toFixed(2).replace('.', ',')}) não pode ser maior que o valor restante (R$ ${valorRestante.toFixed(2).replace('.', ',')})`);
        return false;
    }

    if (valorDecimal <= 0) {
        Swal.showValidationMessage('O valor recebido deve ser maior que zero');
        return false;
    }

    return {
        data_pagamento: dataPagamento,
        valor_pago: valorDecimal,
        id_forma_pagamento: idFormaPagamento || null,
        id_bancos: idBancos || null,
        observacoes: observacoes || null
    };
}

// ============================================
// Expor funções globalmente
// ============================================
window.initContasAReceber = initContasAReceber;
window.excluirConta = excluirConta;
window.toggleParcelas = toggleParcelas;
window.toggleRecorrencias = toggleRecorrencias;
window.abrirModalBaixa = abrirModalBaixa;
window.verHistoricoRecebimentos = verHistoricoRecebimentos;
window.verLogAtividadesConta = verLogAtividadesConta;
