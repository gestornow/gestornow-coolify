// ============================================
// CLIENTES INDEX - Gerenciamento de Seleção e Exclusão Múltipla
// ============================================

$(document).ready(function() {
    const $checkAll = $('#checkAll');
    const $checkItems = $('.check-item');
    const $btnExcluir = $('#btnExcluirSelecionados');
    const $countSpan = $('#countSelecionados');

    // Check All functionality
    $checkAll.on('change', function() {
        const isChecked = $(this).is(':checked');
        $checkItems.prop('checked', isChecked);
        updateDeleteButton();
    });

    // Individual checkbox change
    $checkItems.on('change', function() {
        updateCheckAllState();
        updateDeleteButton();
    });

    // Update "Check All" state
    function updateCheckAllState() {
        const totalItems = $checkItems.length;
        const checkedItems = $checkItems.filter(':checked').length;
        
        $checkAll.prop('checked', totalItems > 0 && checkedItems === totalItems);
        $checkAll.prop('indeterminate', checkedItems > 0 && checkedItems < totalItems);
    }

    // Show/hide delete button and update count
    function updateDeleteButton() {
        const checkedItems = $checkItems.filter(':checked');
        const count = checkedItems.length;
        
        if (count > 0) {
            $btnExcluir.show();
            $countSpan.text(count);
        } else {
            $btnExcluir.hide();
        }
    }

    // Delete multiple clientes
    $btnExcluir.on('click', function() {
        const checkedItems = $checkItems.filter(':checked');
        const ids = checkedItems.map(function() {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            return;
        }

        Swal.fire({
            title: 'Tem certeza?',
            text: `Você está prestes a excluir ${ids.length} cliente(s). Esta ação não pode ser desfeita!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const deleteUrl = $btnExcluir.data('url');
                
                $.ajax({
                    url: deleteUrl,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Excluídos!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: xhr.responseJSON?.message || 'Erro ao excluir clientes.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    });

    // Delete single cliente
    $('.form-delete-cliente').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        
        Swal.fire({
            title: 'Tem certeza?',
            text: 'Esta ação não pode ser desfeita!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $form[0].submit();
            }
        });
    });

    // Initialize
    updateCheckAllState();
    updateDeleteButton();
});

// ============================================
// Log de Atividades (Clientes)
// ============================================
function verLogAtividadesCliente(idCliente, nome) {
    $.ajax({
        url: `/clientes/${idCliente}/logs-atividades`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                mostrarModalLogAtividadesCliente(nome, response.logs || []);
            } else {
                mostrarErroCliente(response.message || 'Não foi possível carregar o log de atividades.');
            }
        },
        error: function(xhr) {
            mostrarErroCliente(xhr.responseJSON?.message || 'Erro ao carregar o log de atividades.');
        }
    });
}

function mostrarModalLogAtividadesCliente(nome, logs) {
    const html = gerarHtmlLogAtividadesCliente(nome, logs);

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

function gerarHtmlLogAtividadesCliente(nome, logs) {
    const totalLogs = Array.isArray(logs) ? logs.length : 0;

    let html = `
        <div class="text-start p-4 border-bottom bg-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Cliente</div>
                    <div class="fw-bold mb-0" style="font-size: 1.05rem; line-height: 1.4;">${escapeHtmlCliente(nome || '-')}</div>
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
                    <div class="text-muted small mt-1">Este cliente ainda não possui histórico de atividades.</div>
                </div>
            </div>
        `;
        return html;
    }

    html += '<div class="bg-body-secondary" style="max-height: 600px; overflow-y: auto; padding: 1.5rem;">';

    logs.forEach((item, index) => {
        const cor = normalizarCorLogCliente(item.cor);
        const icone = item.icone || 'activity';
        const responsavel = escapeHtmlCliente(item.nome_responsavel || item.email_responsavel || 'Sistema');
        const dataHora = formatDateTimeCliente(item.ocorrido_em);
        const acao = formatarAcaoLogCliente(item.acao || '-');
        const descricaoItem = escapeHtmlCliente(item.descricao || 'Atividade registrada');

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
                                                ${formatarObjetoDetalhadoCliente(item.antes)}
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
                                                ${formatarObjetoDetalhadoCliente(item.depois)}
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

function normalizarCorLogCliente(cor) {
    const mapa = {
        'verde': 'success',
        'amarelo': 'warning',
        'vermelho': 'danger',
        'azul': 'primary',
        'azul-escuro': 'info',
        'laranja': 'warning',
        'cinza': 'secondary',
        'vermelho-escuro': 'danger'
    };

    return mapa[cor] || 'primary';
}

function formatarObjetoDetalhadoCliente(obj) {
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

        const valorTexto = valor === null || valor === undefined || valor === ''
            ? '(vazio)'
            : String(valor);

        html += `
            <div class="d-flex gap-2">
                <span class="fw-semibold text-nowrap text-body-secondary" style="min-width: 120px;">${escapeHtmlCliente(chaveFormatada)}:</span>
                <span class="flex-grow-1 text-break">${escapeHtmlCliente(valorTexto)}</span>
            </div>
        `;
    });

    html += '</div>';
    return html;
}

function formatarAcaoLogCliente(acao) {
    if (!acao || acao === '-') return '-';
    return String(acao)
        .replace(/[_\.]+/g, ' ')
        .trim();
}

function formatDateTimeCliente(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('pt-BR');
}

function escapeHtmlCliente(texto) {
    return String(texto)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function mostrarErroCliente(mensagem) {
    Swal.fire({
        icon: 'error',
        title: 'Erro!',
        text: mensagem,
        showConfirmButton: true,
        confirmButtonText: 'OK'
    });
}

window.verLogAtividadesCliente = verLogAtividadesCliente;
