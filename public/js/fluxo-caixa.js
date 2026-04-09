/**
 * Fluxo de Caixa - Gestão Financeira
 * @author GestorNow 2.0
 * @library Chart.js 4.x
 */

let chartFluxoCaixa, chartEntradas, chartSaidas;
let tipoVisualizacaoAtual = 'line';

function normalizarNumero(valor) {
    if (typeof valor === 'number') {
        return Number.isFinite(valor) ? valor : 0;
    }

    if (valor === null || valor === undefined) {
        return 0;
    }

    let texto = String(valor).trim();

    if (!texto) {
        return 0;
    }

    texto = texto
        .replace(/\s+/g, '')
        .replace(/[^\d.,-]/g, '');

    const ultimaVirgula = texto.lastIndexOf(',');
    const ultimoPonto = texto.lastIndexOf('.');

    if (ultimaVirgula > ultimoPonto) {
        texto = texto.replace(/\./g, '').replace(',', '.');
    } else {
        texto = texto.replace(/,/g, '');
    }

    const numero = parseFloat(texto);
    return Number.isFinite(numero) ? numero : 0;
}

function normalizarValoresCategoria(dados) {
    if (!dados || !Array.isArray(dados.valores)) {
        return [];
    }

    return dados.valores.map(function(valor) {
        return normalizarNumero(valor);
    });
}

function calcularPercentualSeguro(parte, total) {
    const parteNumero = normalizarNumero(parte);
    const totalNumero = normalizarNumero(total);

    if (totalNumero <= 0) {
        return 0;
    }

    return (parteNumero / totalNumero) * 100;
}

$(document).ready(function() {
    // Inicializar componentes
    $('.select2').select2({
        placeholder: 'Selecione...',
        allowClear: true
    });
});

// Carregar dados após todo conteúdo da página estar pronto
$(window).on('load', function() {
    if (document.getElementById('formFiltros')) {
        gerarRelatorio();
    } else {
        setTimeout(function() {
            if (document.getElementById('formFiltros')) {
                gerarRelatorio();
            }
        }, 500);
    }
    
    // Observar mudanças de tema e atualizar gráficos
    setupThemeObserver();
});

/**
 * Configurar observador de mudanças de tema
 */
function setupThemeObserver() {
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && (mutation.attributeName === 'class' || mutation.attributeName === 'data-style')) {
                atualizarCoresGraficos();
            }
        });
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class', 'data-style']
    });
}

/**
 * Atualizar saldo quando seleciona um banco
 */
function atualizarSaldoBanco() {
    const select = $('#banco_saldo');
    const valorSelecionado = select.val();
    const divSaldoManual = $('#div_saldo_manual');
    let saldo = 0;
    
    if (valorSelecionado === 'manual') {
        // Mostrar campo de saldo manual
        divSaldoManual.show();
        const valorManual = $('#saldo_manual').val().replace(/\./g, '').replace(',', '.');
        saldo = parseFloat(valorManual) || 0;
    } else {
        // Ocultar campo de saldo manual
        divSaldoManual.hide();
        
        if (valorSelecionado === 'todos') {
            // Somar todos os bancos
            select.find('option[data-saldo]').each(function() {
                saldo += parseFloat($(this).data('saldo')) || 0;
            });
        } else {
            // Pegar saldo do banco selecionado
            saldo = parseFloat(select.find('option:selected').data('saldo')) || 0;
        }
    }
    
    // Atualizar campos
    $('#saldo_inicial').val(saldo);
    $('#info_saldo').text('Saldo: R$ ' + formatarMoeda(saldo));
}

/**
 * Atualizar saldo manual
 */
function atualizarSaldoManual() {
    const valorManual = $('#saldo_manual').val().replace(/\./g, '').replace(',', '.');
    const saldo = parseFloat(valorManual) || 0;
    $('#saldo_inicial').val(saldo);
    $('#info_saldo').text('Saldo: R$ ' + formatarMoeda(saldo));
}

/**
 * Atualizar cores dos gráficos quando o tema muda
 */
function atualizarCoresGraficos() {
    const cores = getCoresThema();
    
    // Atualizar gráfico de fluxo de caixa
    if (chartFluxoCaixa) {
        chartFluxoCaixa.options.plugins.legend.labels.color = cores.textColor;
        chartFluxoCaixa.options.plugins.tooltip.backgroundColor = cores.tooltipBg;
        chartFluxoCaixa.options.plugins.tooltip.titleColor = cores.textColor;
        chartFluxoCaixa.options.plugins.tooltip.bodyColor = cores.textColor;
        chartFluxoCaixa.options.plugins.tooltip.borderColor = cores.tooltipBorder;
        chartFluxoCaixa.options.scales.x.grid.color = cores.gridColor;
        chartFluxoCaixa.options.scales.x.ticks.color = cores.textColor;
        chartFluxoCaixa.options.scales.y.grid.color = cores.gridColor;
        chartFluxoCaixa.options.scales.y.ticks.color = cores.textColor;
        chartFluxoCaixa.update();
    }
    
    // Atualizar gráfico de entradas
    if (chartEntradas) {
        chartEntradas.options.plugins.legend.labels.color = cores.textColor;
        chartEntradas.options.plugins.tooltip.backgroundColor = cores.tooltipBg;
        chartEntradas.options.plugins.tooltip.titleColor = cores.textColor;
        chartEntradas.options.plugins.tooltip.bodyColor = cores.textColor;
        chartEntradas.options.plugins.tooltip.borderColor = cores.tooltipBorder;
        chartEntradas.update();
    }
    
    // Atualizar gráfico de saídas
    if (chartSaidas) {
        chartSaidas.options.plugins.legend.labels.color = cores.textColor;
        chartSaidas.options.plugins.tooltip.backgroundColor = cores.tooltipBg;
        chartSaidas.options.plugins.tooltip.titleColor = cores.textColor;
        chartSaidas.options.plugins.tooltip.bodyColor = cores.textColor;
        chartSaidas.options.plugins.tooltip.borderColor = cores.tooltipBorder;
        chartSaidas.update();
    }
}

/**
 * Obter cores do tema atual
 */
function getCoresThema() {
    const isDark = document.documentElement.classList.contains('dark-style') || 
                   document.documentElement.getAttribute('data-style') === 'dark' ||
                   document.documentElement.getAttribute('data-user-theme') === 'dark';
    
    return {
        isDark: isDark,
        textColor: isDark ? '#ffffff' : '#373d3f',
        labelColor: isDark ? '#ffffff' : '#373d3f',
        gridColor: isDark ? '#444444' : '#e0e0e0',
        backgroundColor: isDark ? '#2b2c40' : '#ffffff',
        tooltipBg: isDark ? '#1e1e2d' : '#ffffff',
        tooltipBorder: isDark ? '#444' : '#ddd'
    };
}

/**
 * Gerar relatório com base nos filtros
 */
function gerarRelatorio() {
    const form = document.getElementById('formFiltros');
    
    if (!form) {
        Swal.fire('Erro!', 'Formulário não encontrado. Recarregue a página.', 'error');
        return;
    }
    
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Gerando Relatório...',
        text: 'Por favor aguarde',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const url = window.fluxoCaixaRoutes.dados;

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro na resposta: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        atualizarCards(data.resumo);
        renderizarGraficoFluxo(data.fluxo, tipoVisualizacaoAtual);
        renderizarGraficoEntradas(data.entradas);
        renderizarGraficoSaidas(data.saidas);
        preencherTabelaEntradas(data.entradas);
        preencherTabelaSaidas(data.saidas);
        preencherTabelaDetalhada(data.lancamentos);
        
        if (data.comparativo) {
            renderizarComparativo(data.comparativo);
        }
        
        Swal.close();
    })
    .catch(error => {
        
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Erro ao gerar relatório: ' + error.message,
            footer: 'Verifique o console do navegador (F12) para mais detalhes'
        });
    });
}

/**
 * Atualizar cards de resumo
 */
function atualizarCards(resumo) {
    $('#card-saldo-inicial').text('R$ ' + formatarMoeda(resumo.saldo_inicial));
    $('#card-total-entradas').text('R$ ' + formatarMoeda(resumo.total_entradas));
    $('#card-total-saidas').text('R$ ' + formatarMoeda(resumo.total_saidas));
    $('#card-saldo-final').text('R$ ' + formatarMoeda(resumo.saldo_final));
}

/**
 * Renderizar gráfico principal de fluxo de caixa (Chart.js)
 */
function renderizarGraficoFluxo(dados, tipo) {
    const cores = getCoresThema();
    const ctx = document.getElementById('chartFluxoCaixa');
    
    if (!ctx) return;
    
    // Destruir gráfico anterior
    if (chartFluxoCaixa) {
        chartFluxoCaixa.destroy();
    }
    
    const isArea = tipo === 'area';
    const isLine = tipo === 'line';
    const isBarra = tipo === 'bar';
    
    let datasets = [];
    
    if (isLine) {
        // Gráfico Misto: Barras para Entradas/Saídas + Linha para Saldo
        datasets = [
            {
                label: 'Entradas',
                type: 'bar',
                data: dados.entradas,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: '#28a745',
                borderWidth: 1
            },
            {
                label: 'Saídas',
                type: 'bar',
                data: dados.saidas,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: '#dc3545',
                borderWidth: 1
            },
            {
                label: 'Saldo Acumulado',
                type: 'line',
                data: dados.saldo,
                backgroundColor: 'rgba(13, 110, 253, 0)',
                borderColor: '#0d6efd',
                borderWidth: 4,
                fill: false,
                tension: 0.4,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: '#0d6efd',
                pointBorderColor: '#fff',
                pointBorderWidth: 3
            }
        ];
    } else if (isArea) {
        // Gráfico de Área
        datasets = [
            {
                label: 'Entradas',
                data: dados.entradas,
                backgroundColor: 'rgba(40, 167, 69, 0.3)',
                borderColor: '#28a745',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6
            },
            {
                label: 'Saídas',
                data: dados.saidas,
                backgroundColor: 'rgba(220, 53, 69, 0.3)',
                borderColor: '#dc3545',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6
            },
            {
                label: 'Saldo Acumulado',
                data: dados.saldo,
                backgroundColor: 'rgba(13, 110, 253, 0.3)',
                borderColor: '#0d6efd',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6
            }
        ];
    } else {
        // Gráfico de Barras
        datasets = [
            {
                label: 'Entradas',
                data: dados.entradas,
                backgroundColor: '#28a745',
                borderColor: '#28a745',
                borderWidth: 1
            },
            {
                label: 'Saídas',
                data: dados.saidas,
                backgroundColor: '#dc3545',
                borderColor: '#dc3545',
                borderWidth: 1
            },
            {
                label: 'Saldo Acumulado',
                data: dados.saldo,
                backgroundColor: '#0d6efd',
                borderColor: '#0d6efd',
                borderWidth: 1
            }
        ];
    }
    
    const config = {
        type: isArea ? 'line' : 'bar',
        data: {
            labels: dados.periodos,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            layout: {
                padding: {
                    top: 20,
                    bottom: 20,
                    left: 15,
                    right: 15
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'start',
                    labels: {
                        color: cores.textColor,
                        font: {
                            size: 13,
                            weight: '600'
                        },
                        padding: 30,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 10,
                        boxHeight: 10
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: cores.tooltipBg,
                    titleColor: cores.textColor,
                    bodyColor: cores.textColor,
                    borderColor: cores.tooltipBorder,
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': R$ ' + formatarMoeda(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: cores.gridColor,
                        drawBorder: false
                    },
                    ticks: {
                        color: cores.textColor,
                        font: {
                            size: 11,
                            weight: '500'
                        },
                        maxRotation: 45,
                        minRotation: 45,
                        padding: 8
                    }
                },
                y: {
                    grid: {
                        color: cores.gridColor,
                        drawBorder: false
                    },
                    ticks: {
                        color: cores.textColor,
                        font: {
                            size: 11,
                            weight: '500'
                        },
                        padding: 10,
                        callback: function(value) {
                            return 'R$ ' + formatarMoeda(value);
                        }
                    }
                }
            }
        }
    };
    
    chartFluxoCaixa = new Chart(ctx, config);
}

/**
 * Alternar tipo de visualização do gráfico
 */
function alternarVisualizacao(tipo) {
    const tipoMap = {
        'linha': 'line',
        'barra': 'bar',
        'area': 'area'
    };
    tipoVisualizacaoAtual = tipoMap[tipo] || tipo;
    
    $('.btn-group button').removeClass('active');
    event.target.closest('button').classList.add('active');
    
    gerarRelatorio();
}

/**
 * Renderizar gráfico de entradas (Rosca - Chart.js)
 */
function renderizarGraficoEntradas(dados) {
    const chartElement = document.getElementById('chartEntradas');
    
    if (!chartElement) return;
    
    const valores = normalizarValoresCategoria(dados);
    const total = valores.reduce((acumulado, atual) => acumulado + atual, 0);

    // Verificar se há dados
    if (!dados || !dados.valores || dados.valores.length === 0 || total <= 0) {
        chartElement.innerHTML = '<div class="text-center py-5"><i class="ti ti-chart-pie ti-lg text-muted mb-2"></i><p class="text-muted">Nenhuma entrada registrada no período</p></div>';
        return;
    }
    
    const cores = getCoresThema();
    
    // Destruir gráfico anterior
    if (chartEntradas) {
        chartEntradas.destroy();
    }
    
    // Limpar conteúdo anterior
    chartElement.innerHTML = '<canvas id="canvasEntradas"></canvas>';
    const ctx = document.getElementById('canvasEntradas');
    
    const config = {
        type: 'doughnut',
        data: {
            labels: dados.categorias,
            datasets: [{
                data: valores,
                backgroundColor: [
                    '#28a745',
                    '#198754',
                    '#20c997',
                    '#0dcaf0',
                    '#0d6efd',
                    '#6610f2',
                    '#6f42c1',
                    '#17a2b8',
                    '#fd7e14',
                    '#e83e8c'
                ],
                borderWidth: 2,
                borderColor: cores.backgroundColor
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        color: cores.textColor,
                        font: {
                            size: 12
                        },
                        padding: 12,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: cores.tooltipBg,
                    titleColor: cores.textColor,
                    bodyColor: cores.textColor,
                    borderColor: cores.tooltipBorder,
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = 'R$ ' + formatarMoeda(context.parsed);
                            const totalValores = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percent = calcularPercentualSeguro(context.parsed, totalValores).toFixed(1);
                            return label + ': ' + value + ' (' + percent + '%)';
                        }
                    }
                }
            }
        }
    };
    
    chartEntradas = new Chart(ctx, config);
}

/**
 * Renderizar gráfico de saídas (Rosca - Chart.js)
 */
function renderizarGraficoSaidas(dados) {
    const chartElement = document.getElementById('chartSaidas');
    
    if (!chartElement) return;
    
    const valores = normalizarValoresCategoria(dados);
    const total = valores.reduce((acumulado, atual) => acumulado + atual, 0);

    // Verificar se há dados
    if (!dados || !dados.valores || dados.valores.length === 0 || total <= 0) {
        chartElement.innerHTML = '<div class="text-center py-5"><i class="ti ti-chart-pie ti-lg text-muted mb-2"></i><p class="text-muted">Nenhuma saída registrada no período</p></div>';
        return;
    }
    
    const cores = getCoresThema();
    
    // Destruir gráfico anterior
    if (chartSaidas) {
        chartSaidas.destroy();
    }
    
    // Limpar conteúdo anterior
    chartElement.innerHTML = '<canvas id="canvasSaidas"></canvas>';
    const ctx = document.getElementById('canvasSaidas');
    
    const config = {
        type: 'doughnut',
        data: {
            labels: dados.categorias,
            datasets: [{
                data: valores,
                backgroundColor: [
                    '#dc3545',
                    '#d63384',
                    '#fd7e14',
                    '#ffc107',
                    '#6f42c1',
                    '#e83e8c',
                    '#ff6384',
                    '#e91e63',
                    '#f44336',
                    '#ff5722'
                ],
                borderWidth: 2,
                borderColor: cores.backgroundColor
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        color: cores.textColor,
                        font: {
                            size: 12
                        },
                        padding: 12,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: cores.tooltipBg,
                    titleColor: cores.textColor,
                    bodyColor: cores.textColor,
                    borderColor: cores.tooltipBorder,
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = 'R$ ' + formatarMoeda(context.parsed);
                            const totalValores = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percent = calcularPercentualSeguro(context.parsed, totalValores).toFixed(1);
                            return label + ': ' + value + ' (' + percent + '%)';
                        }
                    }
                }
            }
        }
    };
    
    chartSaidas = new Chart(ctx, config);
}

/**
 * Preencher tabela de entradas
 */
function preencherTabelaEntradas(dados) {
    let html = '';
    
    if (!dados || !dados.valores || dados.valores.length === 0) {
        html = '<tr><td colspan="4" class="text-center text-muted">Nenhuma entrada registrada no período</td></tr>';
        $('#tabelaEntradas tbody').html(html);
        return;
    }
    
    const valores = normalizarValoresCategoria(dados);
    const total = valores.reduce((a, b) => a + b, 0);

    const categoriasOrdenadas = dados.categorias.map((cat, idx) => ({
        categoria: cat,
        valor: valores[idx] || 0
    })).sort((a, b) => b.valor - a.valor);

    categoriasOrdenadas.forEach((item) => {
        const valor = item.valor;
        const percentual = calcularPercentualSeguro(valor, total);
        html += `
            <tr>
                <td><span class="badge bg-success badge-dot me-2"></span>${item.categoria}</td>
                <td class="text-end fw-semibold">R$ ${formatarMoeda(valor)}</td>
                <td class="text-end">${percentual.toFixed(1)}%</td>
                <td>
                    <div class="progress" style="height: 7px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: ${Math.min(percentual, 100)}%" aria-valuenow="${percentual.toFixed(1)}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </td>
            </tr>
        `;
    });
    
    $('#tabelaEntradas tbody').html(html);
}

/**
 * Preencher tabela de saídas
 */
function preencherTabelaSaidas(dados) {
    let html = '';
    
    if (!dados || !dados.valores || dados.valores.length === 0) {
        html = '<tr><td colspan="4" class="text-center text-muted">Nenhuma saída registrada no período</td></tr>';
        $('#tabelaSaidas tbody').html(html);
        return;
    }
    
    const valores = normalizarValoresCategoria(dados);
    const total = valores.reduce((a, b) => a + b, 0);

    const categoriasOrdenadas = dados.categorias.map((cat, idx) => ({
        categoria: cat,
        valor: valores[idx] || 0
    })).sort((a, b) => b.valor - a.valor);

    categoriasOrdenadas.forEach((item) => {
        const valor = item.valor;
        const percentual = calcularPercentualSeguro(valor, total);
        html += `
            <tr>
                <td><span class="badge bg-danger badge-dot me-2"></span>${item.categoria}</td>
                <td class="text-end fw-semibold">R$ ${formatarMoeda(valor)}</td>
                <td class="text-end">${percentual.toFixed(1)}%</td>
                <td>
                    <div class="progress" style="height: 7px;">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: ${Math.min(percentual, 100)}%" aria-valuenow="${percentual.toFixed(1)}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </td>
            </tr>
        `;
    });
    
    $('#tabelaSaidas tbody').html(html);
}

/**
 * Preencher tabela detalhada
 */
function preencherTabelaDetalhada(lancamentos) {
    if (!lancamentos || lancamentos.length === 0) {
        $('#tabelaDetalhadaEntradas tbody').html('<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma entrada registrada no período</td></tr>');
        $('#tabelaDetalhadaSaidas tbody').html('<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma saída registrada no período</td></tr>');
        $('#total-entradas-detalhado').text('R$ 0,00');
        $('#total-saidas-detalhado').text('R$ 0,00');
        $('#saldo-entradas-detalhado').text('R$ 0,00');
        $('#saldo-saidas-detalhado').text('R$ 0,00');
        $('#lucratividade-label-entradas').text('RESULTADO LÍQUIDO');
        $('#lucratividade-label-saidas').text('QUEIMA DE CAIXA');
        $('#lucratividade-entradas').html('<span class="text-muted">R$ 0,00 • Margem 0,0%</span>');
        $('#lucratividade-saidas').html('<span class="text-muted">0,0% das entradas</span>');
        return;
    }
    
    let saldoInicial = parseFloat($('#saldo_inicial').val().replace(/\./g, '').replace(',', '.')) || 0;
    
    // Separar lançamentos por tipo
    const entradas = lancamentos.filter(l => l.tipo === 'entrada');
    const saidas = lancamentos.filter(l => l.tipo === 'saida');
    
    // Processar Entradas
    let htmlEntradas = '';
    let saldoEntradas = saldoInicial;
    let totalEntradas = 0;
    
    entradas.forEach(lanc => {
        const valorLancamento = normalizarNumero(lanc.valor);

        saldoEntradas += valorLancamento;
        totalEntradas += valorLancamento;
        
        const saldoClass = saldoEntradas >= 0 ? 'text-success' : 'text-danger';
        const temVinculo = lanc.id_conta_receber && lanc.id_conta_receber !== null;
        const btnLog = lanc.id_fluxo_caixa
            ? `<button class="btn btn-sm btn-outline-secondary" onclick='verLogAtividadesFluxo(${lanc.id_fluxo_caixa}, ${JSON.stringify(lanc.descricao || "Lançamento")})' title="Log de Atividades">
                <i class="ti ti-activity"></i>
            </button>`
            : '';
        const btnRemoverBaixa = temVinculo ? 
            `<button class="btn btn-sm btn-outline-warning" onclick="removerBaixa(${lanc.id_fluxo_caixa}, ${lanc.id_conta_receber}, 'entrada')" title="Remover baixa e reabrir conta">
                <i class="ti ti-arrow-back-up"></i>
            </button>` : 
            '<span class="text-muted">-</span>';

        const btnRecibo = temVinculo
            ? `<a class="btn btn-sm btn-outline-primary" href="${montarUrlRecibo('entrada', lanc.id_conta_receber)}" target="_blank" title="Ver recibo">
                <i class="ti ti-receipt-2"></i>
            </a>`
            : '';

        // Botão de cupom para vendas PDV
        const btnCupomPDV = (lanc.referencia_tipo === 'venda' && lanc.referencia_id)
            ? `<a class="btn btn-sm btn-outline-success" href="/pdv/cupom/${lanc.referencia_id}" target="_blank" title="Imprimir Cupom PDV">
                <i class="ti ti-printer"></i>
            </a>`
            : '';
        
        // Montar informações adicionais
        let infoAdicional = '';
        if (lanc.forma_pagamento) {
            infoAdicional += `<small class="text-muted"><i class="ti ti-credit-card"></i> ${lanc.forma_pagamento}</small>`;
        }
        if (lanc.banco) {
            infoAdicional += infoAdicional ? ' • ' : '<small class="text-muted">';
            infoAdicional += `<i class="ti ti-building-bank"></i> ${lanc.banco}</small>`;
        }
        
        htmlEntradas += `
            <tr class="table-success-subtle">
                <td>${formatarDataBr(lanc.data)}</td>
                <td>
                    <div>${lanc.descricao}</div>
                    ${infoAdicional ? `<div>${infoAdicional}</div>` : ''}
                </td>
                <td>${lanc.categoria}</td>
                <td class="text-end text-success fw-semibold">R$ ${formatarMoeda(valorLancamento)}</td>
                <td class="text-end ${saldoClass} fw-semibold">R$ ${formatarMoeda(saldoEntradas)}</td>
                <td class="text-center acoes-fluxo">${btnLog}${btnCupomPDV}${btnRecibo}${btnRemoverBaixa}</td>
            </tr>
        `;
    });
    
    // Processar Saídas
    let htmlSaidas = '';
    let saldoSaidas = saldoInicial;
    let totalSaidas = 0;
    
    saidas.forEach(lanc => {
        const valorLancamento = normalizarNumero(lanc.valor);

        saldoSaidas -= valorLancamento;
        totalSaidas += valorLancamento;
        
        const saldoClass = saldoSaidas >= 0 ? 'text-success' : 'text-danger';
        const temVinculo = lanc.id_conta_pagar && lanc.id_conta_pagar !== null;
        const btnLog = lanc.id_fluxo_caixa
            ? `<button class="btn btn-sm btn-outline-secondary" onclick='verLogAtividadesFluxo(${lanc.id_fluxo_caixa}, ${JSON.stringify(lanc.descricao || "Lançamento")})' title="Log de Atividades">
                <i class="ti ti-activity"></i>
            </button>`
            : '';
        const btnRemoverBaixa = temVinculo ? 
            `<button class="btn btn-sm btn-outline-warning" onclick="removerBaixa(${lanc.id_fluxo_caixa}, ${lanc.id_conta_pagar}, 'saida')" title="Remover baixa e reabrir conta">
                <i class="ti ti-arrow-back-up"></i>
            </button>` : 
            '<span class="text-muted">-</span>';

        const btnRecibo = temVinculo
            ? `<a class="btn btn-sm btn-outline-primary" href="${montarUrlRecibo('saida', lanc.id_conta_pagar)}" target="_blank" title="Ver recibo">
                <i class="ti ti-receipt-2"></i>
            </a>`
            : '';
        
        // Montar informações adicionais
        let infoAdicional = '';
        if (lanc.forma_pagamento) {
            infoAdicional += `<small class="text-muted"><i class="ti ti-credit-card"></i> ${lanc.forma_pagamento}</small>`;
        }
        if (lanc.banco) {
            infoAdicional += infoAdicional ? ' • ' : '<small class="text-muted">';
            infoAdicional += `<i class="ti ti-building-bank"></i> ${lanc.banco}</small>`;
        }
        
        htmlSaidas += `
            <tr class="table-danger-subtle">
                <td>${formatarDataBr(lanc.data)}</td>
                <td>
                    <div>${lanc.descricao}</div>
                    ${infoAdicional ? `<div>${infoAdicional}</div>` : ''}
                </td>
                <td>${lanc.categoria}</td>
                <td class="text-end text-danger fw-semibold">R$ ${formatarMoeda(valorLancamento)}</td>
                <td class="text-end ${saldoClass} fw-semibold">R$ ${formatarMoeda(saldoSaidas)}</td>
                <td class="text-center acoes-fluxo">${btnLog}${btnRecibo}${btnRemoverBaixa}</td>
            </tr>
        `;
    });
    
    // Preencher tabelas
    $('#tabelaDetalhadaEntradas tbody').html(htmlEntradas || '<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma entrada registrada no período</td></tr>');
    $('#tabelaDetalhadaSaidas tbody').html(htmlSaidas || '<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma saída registrada no período</td></tr>');
    
    // Atualizar totais
    $('#total-entradas-detalhado').text('R$ ' + formatarMoeda(totalEntradas));
    $('#total-saidas-detalhado').text('R$ ' + formatarMoeda(totalSaidas));
    $('#saldo-entradas-detalhado').text('R$ ' + formatarMoeda(saldoEntradas));
    $('#saldo-saidas-detalhado').text('R$ ' + formatarMoeda(saldoSaidas));
    
    // Calcular e exibir lucratividade
    const resultadoLiquido = totalEntradas - totalSaidas;
    const margemLiquida = calcularPercentualSeguro(resultadoLiquido, totalEntradas);
    const consumoEntradas = calcularPercentualSeguro(totalSaidas, totalEntradas);
    const coberturaSaidas = totalSaidas > 0 ? calcularPercentualSeguro(totalEntradas, totalSaidas) : 0;

    const resultadoClass = resultadoLiquido >= 0 ? 'text-success' : 'text-danger';
    const consumoClass = consumoEntradas > 100 ? 'text-danger' : 'text-warning';

    $('#lucratividade-label-entradas').text('RESULTADO LÍQUIDO');
    $('#lucratividade-entradas').html(
        `<span class="${resultadoClass}">${resultadoLiquido >= 0 ? '+' : '-'}R$ ${formatarMoeda(Math.abs(resultadoLiquido))}</span>` +
        `<small class="text-muted ms-2">• Margem ${margemLiquida.toFixed(1)}%</small>`
    );

    $('#lucratividade-label-saidas').text('QUEIMA DE CAIXA');
    $('#lucratividade-saidas').html(
        `<span class="${consumoClass}">${consumoEntradas.toFixed(1)}% das entradas</span>` +
        `<small class="text-muted ms-2">• Cobertura ${coberturaSaidas.toFixed(1)}%</small>`
    );
}

/**
 * Renderizar comparativo
 */
function renderizarComparativo(dados) {
    const html = `
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Métrica</th>
                        <th class="text-center">Período Atual</th>
                        <th class="text-center">Período Comparado</th>
                        <th class="text-center">Variação</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Total de Entradas</td>
                        <td class="text-center">R$ ${formatarMoeda(dados.atual.entradas)}</td>
                        <td class="text-center">R$ ${formatarMoeda(dados.comparado.entradas)}</td>
                        <td class="text-center">${renderizarVariacao(dados.variacao.entradas)}</td>
                    </tr>
                    <tr>
                        <td>Total de Saídas</td>
                        <td class="text-center">R$ ${formatarMoeda(dados.atual.saidas)}</td>
                        <td class="text-center">R$ ${formatarMoeda(dados.comparado.saidas)}</td>
                        <td class="text-center">${renderizarVariacao(dados.variacao.saidas)}</td>
                    </tr>
                    <tr>
                        <td><strong>Saldo Final</strong></td>
                        <td class="text-center"><strong>R$ ${formatarMoeda(dados.atual.saldo)}</strong></td>
                        <td class="text-center"><strong>R$ ${formatarMoeda(dados.comparado.saldo)}</strong></td>
                        <td class="text-center"><strong>${renderizarVariacao(dados.variacao.saldo)}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    $('#comparativoContent').html(html);
}

/**
 * Renderizar badge de variação
 */
function renderizarVariacao(valor) {
    const sinal = valor >= 0 ? '+' : '';
    const classe = valor >= 0 ? 'success' : 'danger';
    return `<span class="badge bg-${classe}">${sinal}${valor.toFixed(2)}%</span>`;
}

/**
 * Exportar para Excel
 */
function exportarExcel() {
    const form = document.getElementById('formFiltros');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Gerando Excel...',
        text: 'Por favor aguarde',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Criar FormData e adicionar ao URL como query string
    const params = new URLSearchParams(formData);
    const url = window.fluxoCaixaRoutes.excel + '?' + params.toString();
    
    // Fazer download direto
    window.location.href = url;
    
    setTimeout(() => {
        Swal.close();
    }, 1000);
}

/**
 * Exportar para PDF
 */
function exportarPDF() {
    const form = document.getElementById('formFiltros');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Gerando PDF...',
        text: 'Por favor aguarde',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Criar FormData e adicionar ao URL como query string
    const params = new URLSearchParams(formData);
    const url = window.fluxoCaixaRoutes.pdf + '?' + params.toString();
    
    // Fazer download direto
    window.location.href = url;
    
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'PDF gerado!',
            text: 'O download deve iniciar automaticamente',
            timer: 2000,
            showConfirmButton: false
        });
    }, 1000);
}

/**
 * Formatar valor em moeda brasileira
 */
function formatarMoeda(valor) {
    const numero = normalizarNumero(valor);
    return numero.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatarDataBr(dataValor) {
    if (!dataValor) {
        return '-';
    }

    const data = new Date(dataValor);
    if (Number.isNaN(data.getTime())) {
        return dataValor;
    }

    return data.toLocaleDateString('pt-BR');
}

function montarUrlRecibo(tipo, idConta) {
    if (!idConta) {
        return '#';
    }

    if (tipo === 'saida') {
        return window.fluxoCaixaRoutes.reciboPagar.replace('__ID__', idConta);
    }

    return window.fluxoCaixaRoutes.reciboReceber.replace('__ID__', idConta);
}

/**
 * Remover baixa da conta e reabri-la
 */
function removerBaixa(idFluxoCaixa, idConta, tipo) {
    Swal.fire({
        title: 'Confirmar remoção',
        text: 'Deseja remover esta baixa e reabrir a conta?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const rota = tipo === 'saida' 
                ? `/financeiro/contas-a-pagar/${idConta}/remover-baixa`
                : `/financeiro/contas-a-receber/${idConta}/remover-baixa`;
            
            Swal.fire({
                title: 'Removendo baixa...',
                text: 'Por favor aguarde',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(rota, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id_fluxo_caixa: idFluxoCaixa })
            })
            .then(async response => {
                const text = await response.text();
                
                if (!response.ok) {
                    throw new Error(`Erro HTTP ${response.status}: ${text}`);
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
                }
            })
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message || 'Baixa removida com sucesso',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // Recarregar o relatório
                        gerarRelatorio();
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao remover baixa', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire('Erro!', error.message || 'Erro ao processar solicitação', 'error');
            });
        }
    });
}

// ============================================
// Log de Atividades - Fluxo de Caixa
// ============================================
function verLogAtividadesFluxo(idFluxo, descricao) {
    const rotaLogs = (window.fluxoCaixaRoutes.logs || '').replace('__ID__', idFluxo);

    if (!rotaLogs) {
        Swal.fire('Erro!', 'Rota de log do fluxo não configurada.', 'error');
        return;
    }

    fetch(rotaLogs, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(async response => {
            const text = await response.text();

            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}: ${text}`);
            }

            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Resposta inválida do servidor.');
            }
        })
        .then(response => {
            if (response.success) {
                mostrarModalLogAtividadesFluxo(descricao, response.logs || []);
            } else {
                Swal.fire('Erro!', response.message || 'Não foi possível carregar o log de atividades.', 'error');
            }
        })
        .catch(error => {
            Swal.fire('Erro!', error.message || 'Erro ao carregar o log de atividades.', 'error');
        });
}

function mostrarModalLogAtividadesFluxo(descricao, logs) {
    const html = gerarHtmlLogAtividadesFluxo(descricao, logs);

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

function gerarHtmlLogAtividadesFluxo(descricao, logs) {
    const totalLogs = Array.isArray(logs) ? logs.length : 0;

    let html = `
        <div class="text-start p-4 border-bottom bg-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Lançamento</div>
                    <div class="fw-bold mb-0" style="font-size: 1.05rem; line-height: 1.4;">${escapeHtmlFluxo(descricao || '-')}</div>
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
                    <div class="text-muted small mt-1">Este lançamento ainda não possui histórico de atividades.</div>
                </div>
            </div>
        `;
        return html;
    }

    html += '<div class="bg-body-secondary" style="max-height: 600px; overflow-y: auto; padding: 1.5rem;">';

    logs.forEach((item, index) => {
        const cor = normalizarCorLogFluxo(item.cor);
        const icone = item.icone || 'activity';
        const responsavel = escapeHtmlFluxo(item.nome_responsavel || item.email_responsavel || 'Sistema');
        const dataHora = formatarDataHoraFluxo(item.ocorrido_em);
        const acao = formatarAcaoLogFluxo(item.acao || '-');
        const descricaoItem = escapeHtmlFluxo(item.descricao || 'Atividade registrada');
        const resumoContexto = gerarResumoContextoLogFluxo(item.contexto || {});

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
                                                ${formatarObjetoDetalhadoFluxo(item.antes)}
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
                                                ${formatarObjetoDetalhadoFluxo(item.depois)}
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

function normalizarCorLogFluxo(cor) {
    const mapa = {
        'verde': 'success',
        'vermelho': 'danger',
        'amarelo': 'warning',
        'azul': 'primary',
        'azul-claro': 'info',
        'azul-escuro': 'primary',
        'ciano': 'info',
        'roxo': 'secondary',
        'laranja': 'warning',
        'cinza': 'secondary',
        'verde-escuro': 'success',
        'vermelho-escuro': 'danger',
        'secondary': 'secondary',
        'primary': 'primary',
        'success': 'success',
        'danger': 'danger',
        'warning': 'warning',
        'info': 'info'
    };

    return mapa[(cor || '').toLowerCase()] || 'primary';
}

function formatarAcaoLogFluxo(acao) {
    if (!acao) return '-';

    return String(acao)
        .replace(/_/g, ' ')
        .replace(/\./g, ' • ')
        .replace(/\b\w/g, function (l) { return l.toUpperCase(); });
}

function formatarDataHoraFluxo(dataHora) {
    if (!dataHora) return '-';
    const data = new Date(dataHora);
    if (Number.isNaN(data.getTime())) return '-';
    return data.toLocaleString('pt-BR');
}

function escapeHtmlFluxo(valor) {
    if (valor === null || valor === undefined) return '';

    return String(valor)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatarObjetoDetalhadoFluxo(obj) {
    if (!obj || typeof obj !== 'object') {
        return '<span class="text-muted">Sem dados</span>';
    }

    const entries = Object.entries(obj);

    if (entries.length === 0) {
        return '<span class="text-muted">Sem dados</span>';
    }

    return entries.map(([chave, valor]) => `
        <div class="mb-1 d-flex">
            <span class="text-muted me-2" style="min-width: 140px;">${escapeHtmlFluxo(chave)}:</span>
            <span class="fw-medium">${escapeHtmlFluxo(formatarValorLogFluxo(valor))}</span>
        </div>
    `).join('');
}

function formatarValorLogFluxo(valor) {
    if (valor === null || valor === undefined || valor === '') return '-';

    if (typeof valor === 'object') {
        try {
            return JSON.stringify(valor);
        } catch (e) {
            return '[objeto]';
        }
    }

    return String(valor);
}

function gerarResumoContextoLogFluxo(contexto) {
    if (!contexto || typeof contexto !== 'object' || Object.keys(contexto).length === 0) {
        return '';
    }

    const chips = Object.entries(contexto)
        .filter(([, valor]) => valor !== null && valor !== undefined && valor !== '')
        .slice(0, 8)
        .map(([chave, valor]) => `
            <span class="badge bg-label-secondary me-1 mb-1" style="font-size: 0.75rem;">
                ${escapeHtmlFluxo(chave)}: ${escapeHtmlFluxo(formatarValorLogFluxo(valor))}
            </span>
        `)
        .join('');

    if (!chips) {
        return '';
    }

    return `
        <div>
            <div class="small text-muted mb-2" style="text-transform: uppercase; letter-spacing: 0.5px;">Contexto</div>
            <div>${chips}</div>
        </div>
    `;
}
