@extends('layouts.layoutMaster')

@section('title', 'Calendário - Locações')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/fullcalendar/fullcalendar.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/app-calendar.css')}}" />
<style>
    .calendar-product-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .calendar-product-list .badge {
        white-space: normal;
        text-align: left;
    }

    .fc .fc-event {
        border-width: 0;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.18);
    }

    .fc .fc-event-title {
        font-weight: 600;
    }
</style>
@endsection

@section('content')
<div class="card app-calendar-wrapper">
    <div class="row g-0">
        <div class="col app-calendar-sidebar" id="app-calendar-sidebar">
            <div class="border-bottom p-4 my-sm-0 mb-3">
                <div class="d-grid">
                    <button class="btn btn-primary" type="button">
                        <i class="ti ti-calendar me-1"></i>
                        <span class="align-middle">Calendário de Locações</span>
                    </button>
                </div>
            </div>

            <div class="p-3">
                <div class="inline-calendar"></div>

                <hr class="container-m-nx mb-4 mt-3">

                <div class="mb-3 ms-3">
                    <small class="text-small text-muted text-uppercase align-middle">Filtros de locação</small>
                </div>

                <div class="form-check mb-2 ms-3">
                    <input class="form-check-input" type="checkbox" id="selectAllStatus" checked>
                    <label class="form-check-label" for="selectAllStatus">Ver todos</label>
                </div>

                <div class="ms-3" id="statusFilters">
                    <div class="form-check form-check-info mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-orcamento" data-status="orcamento" checked>
                        <label class="form-check-label" for="status-orcamento">Orçamento</label>
                    </div>
                    <div class="form-check form-check-primary mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-aprovado" data-status="aprovado" checked>
                        <label class="form-check-label" for="status-aprovado">Aprovado</label>
                    </div>
                    <div class="form-check form-check-warning mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-retirada" data-status="retirada" checked>
                        <label class="form-check-label" for="status-retirada">Retirada</label>
                    </div>
                    <div class="form-check form-check-dark mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-medicao" data-status="medicao" checked>
                        <label class="form-check-label" for="status-medicao">Medição</label>
                    </div>
                    <div class="form-check form-check-success mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-medicao-finalizada" data-status="medicao_finalizada" checked>
                        <label class="form-check-label" for="status-medicao-finalizada">Medição finalizada</label>
                    </div>
                    <div class="form-check form-check-primary mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-em-andamento" data-status="em_andamento" checked>
                        <label class="form-check-label" for="status-em-andamento">Em andamento</label>
                    </div>
                    <div class="form-check form-check-danger mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-atrasada" data-status="atrasada" checked>
                        <label class="form-check-label" for="status-atrasada">Atrasada</label>
                    </div>
                    <div class="form-check form-check-success mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-encerrado" data-status="encerrado" checked>
                        <label class="form-check-label" for="status-encerrado">Encerrado</label>
                    </div>
                    <div class="form-check form-check-secondary mb-2">
                        <input class="form-check-input status-filter" type="checkbox" id="status-cancelado" data-status="cancelado,cancelada" checked>
                        <label class="form-check-label" for="status-cancelado">Cancelado</label>
                    </div>
                </div>

                <hr class="container-m-nx my-4">

                <div class="mb-2 ms-3">
                    <small class="text-small text-muted text-uppercase align-middle">Cliente</small>
                </div>
                <div class="ms-3 me-3 mb-3">
                    <select id="filtroCliente" class="form-select form-select-sm">
                        <option value="">Todos os clientes</option>
                    </select>
                </div>

                <div class="mb-2 ms-3">
                    <small class="text-small text-muted text-uppercase align-middle">Produto</small>
                </div>
                <div class="ms-3 me-3 mb-3">
                    <select id="filtroProduto" class="form-select form-select-sm">
                        <option value="">Todos os produtos</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="col app-calendar-content">
            <div class="card shadow-none border-0">
                <div class="card-body pb-0">
                    <div id="calendar-feedback" class="alert alert-warning d-none" role="alert"></div>
                    <div id="calendar"></div>
                </div>
            </div>

            <div class="app-overlay"></div>

            <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="addEventSidebar" aria-labelledby="addEventSidebarLabel">
                <div class="offcanvas-header my-1">
                    <h5 class="offcanvas-title" id="addEventSidebarLabel">Resumo</h5>
                    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
                </div>
                <div class="offcanvas-body pt-0">
                    <p class="text-muted mb-0">Clique em um evento no calendário para ver os detalhes da locação.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalhesLocacao" tabindex="-1" aria-labelledby="modalDetalhesLocacaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalhesLocacaoLabel">Detalhes da Locação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8" id="detalhe-cliente">-</dd>

                    <dt class="col-sm-4">Contrato</dt>
                    <dd class="col-sm-8" id="detalhe-contrato">-</dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8" id="detalhe-status">-</dd>

                    <dt class="col-sm-4">Produtos</dt>
                    <dd class="col-sm-8">
                        <div id="detalhe-produtos" class="calendar-product-list">-</div>
                    </dd>

                    <dt class="col-sm-4">Qtd. Itens</dt>
                    <dd class="col-sm-8" id="detalhe-qtd-itens">0</dd>
                </dl>
            </div>
            <div class="modal-footer">
                <a href="#" id="btn-ver-locacao" target="_blank" class="btn btn-primary">Ver locação</a>
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/fullcalendar/fullcalendar.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
@endsection

@section('page-script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarEl = document.getElementById('calendar');
        const modalEl = document.getElementById('modalDetalhesLocacao');
        const selectAllStatusEl = document.getElementById('selectAllStatus');
        const statusFilterEls = document.querySelectorAll('.status-filter');
        const filtroClienteEl = document.getElementById('filtroCliente');
        const filtroProdutoEl = document.getElementById('filtroProduto');
        const calendarFeedbackEl = document.getElementById('calendar-feedback');
        let modal = null;
        let calendar = null;

        const detalheCliente = document.getElementById('detalhe-cliente');
        const detalheContrato = document.getElementById('detalhe-contrato');
        const detalheStatus = document.getElementById('detalhe-status');
        const detalheProdutos = document.getElementById('detalhe-produtos');
        const detalheQtdItens = document.getElementById('detalhe-qtd-itens');
        const btnVerLocacao = document.getElementById('btn-ver-locacao');

        function normalizarTexto(valor) {
            return String(valor || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
        }

        function mostrarFeedback(msg, tipo = 'warning') {
            if (!calendarFeedbackEl) {
                return;
            }

            calendarFeedbackEl.className = 'alert alert-' + tipo;
            calendarFeedbackEl.textContent = msg;
            calendarFeedbackEl.classList.remove('d-none');
        }

        function esconderFeedback() {
            if (!calendarFeedbackEl) {
                return;
            }

            calendarFeedbackEl.classList.add('d-none');
            calendarFeedbackEl.textContent = '';
        }

        if (!calendarEl) {
            return;
        }

        const CalendarCtor = window.Calendar;

        if (typeof CalendarCtor === 'undefined') {
            calendarEl.innerHTML = '<div class="alert alert-danger mb-0">Não foi possível carregar o componente de calendário.</div>';
            return;
        }

        function obterStatusSelecionados() {
            const status = new Set();

            statusFilterEls.forEach(function(input) {
                if (!input.checked) {
                    return;
                }

                const values = String(input.dataset.status || '')
                    .split(',')
                    .map(function(item) {
                        return item.trim();
                    })
                    .filter(Boolean);

                values.forEach(function(value) {
                    status.add(normalizarTexto(value));
                });
            });

            return status;
        }

        function obterStatusConhecidos() {
            const status = new Set();

            statusFilterEls.forEach(function(input) {
                const values = String(input.dataset.status || '')
                    .split(',')
                    .map(function(item) {
                        return normalizarTexto(item);
                    })
                    .filter(Boolean);

                values.forEach(function(value) {
                    status.add(value);
                });
            });

            return status;
        }

        function aplicarFiltrosCalendario() {
            if (!calendar) {
                return;
            }
            calendar.refetchEvents();
        }

        function preencherSelect(selectEl, placeholder, opcoes, valorAnterior) {
            if (!selectEl) {
                return;
            }

            selectEl.innerHTML = '';

            var opcaoPadrao = document.createElement('option');
            opcaoPadrao.value = '';
            opcaoPadrao.textContent = placeholder;
            selectEl.appendChild(opcaoPadrao);

            opcoes.forEach(function(nome) {
                var option = document.createElement('option');
                option.value = nome;
                option.textContent = nome;
                selectEl.appendChild(option);
            });

            var valorExiste = opcoes.some(function(nome) {
                return nome === valorAnterior;
            });

            selectEl.value = valorExiste ? valorAnterior : '';
        }

        function renderizarStatusDetalhe(label, backgroundColor, textColor) {
            if (!detalheStatus) {
                return;
            }

            detalheStatus.innerHTML = '';

            if (!label) {
                detalheStatus.textContent = '-';
                return;
            }

            var badge = document.createElement('span');
            badge.className = 'badge';
            badge.textContent = label;
            badge.style.backgroundColor = backgroundColor || '#8592a3';
            badge.style.color = textColor || '#ffffff';

            detalheStatus.appendChild(badge);
        }

        function renderizarProdutosDetalhe(produtos) {
            if (!detalheProdutos) {
                return;
            }

            detalheProdutos.innerHTML = '';

            if (!Array.isArray(produtos) || produtos.length === 0) {
                detalheProdutos.textContent = 'Sem produtos vinculados';
                return;
            }

            produtos.forEach(function(produto) {
                var badge = document.createElement('span');
                badge.className = 'badge bg-label-primary';
                badge.textContent = produto;
                detalheProdutos.appendChild(badge);
            });
        }

        function popularFiltrosComDados(rawEvents) {
            if (!filtroClienteEl || !filtroProdutoEl) {
                return;
            }

            var clienteAnterior = filtroClienteEl.value;
            var produtoAnterior = filtroProdutoEl.value;
            var clientes = new Map();
            var produtos = new Map();

            rawEvents.forEach(function(evt) {
                var props = evt.extendedProps || {};
                var cliente = String(props.cliente || '').trim();
                if (cliente) {
                    clientes.set(normalizarTexto(cliente), cliente);
                }

                var listaProdutos = Array.isArray(props.produtos) ? props.produtos : [];
                listaProdutos.forEach(function(produto) {
                    var nome = String(produto || '').trim();
                    if (nome) {
                        produtos.set(normalizarTexto(nome), nome);
                    }
                });
            });

            var clientesOrdenados = Array.from(clientes.values()).sort(function(a, b) {
                return a.localeCompare(b, 'pt-BR', { sensitivity: 'base' });
            });

            var produtosOrdenados = Array.from(produtos.values()).sort(function(a, b) {
                return a.localeCompare(b, 'pt-BR', { sensitivity: 'base' });
            });

            preencherSelect(filtroClienteEl, 'Todos os clientes', clientesOrdenados, clienteAnterior);
            preencherSelect(filtroProdutoEl, 'Todos os produtos', produtosOrdenados, produtoAnterior);

            if (rawEvents.length === 0) {
                mostrarFeedback('Nenhuma locação encontrada para o período selecionado.', 'info');
            } else {
                esconderFeedback();
            }
        }

        if (window.flatpickr) {
            const localePtBrFlatpickr = {
                weekdays: {
                    shorthand: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'],
                    longhand: ['Domingo', 'Segunda-feira', 'Terca-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sabado']
                },
                months: {
                    shorthand: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                    longhand: ['Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']
                },
                firstDayOfWeek: 0,
                rangeSeparator: ' ate ',
                weekAbbreviation: 'Sem',
                scrollTitle: 'Role para aumentar',
                toggleTitle: 'Clique para alternar',
                amPM: ['AM', 'PM'],
                yearAriaLabel: 'Ano',
                monthAriaLabel: 'Mes',
                hourAriaLabel: 'Hora',
                minuteAriaLabel: 'Minuto',
                time_24hr: true
            };

            window.flatpickr('.inline-calendar', {
                inline: true,
                monthSelectorType: 'static',
                locale: localePtBrFlatpickr,
                onChange: function(selectedDates) {
                    if (calendar && selectedDates && selectedDates[0]) {
                        calendar.gotoDate(selectedDates[0]);
                    }
                }
            });
        }

        try {
            const plugins = [];
            if (window.dayGridPlugin) plugins.push(window.dayGridPlugin);
            if (window.timegridPlugin) plugins.push(window.timegridPlugin);
            if (window.timeGridPlugin) plugins.push(window.timeGridPlugin);
            if (window.interactionPlugin) plugins.push(window.interactionPlugin);
            if (window.listPlugin) plugins.push(window.listPlugin);

            const localePtBr = {
                code: 'pt-br',
                week: { dow: 0, doy: 4 },
                buttonText: {
                    prev: 'Anterior',
                    next: 'Próximo',
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia',
                    list: 'Lista'
                },
                weekText: 'Sm',
                allDayText: 'Dia inteiro',
                moreLinkText: function(n) { return '+mais ' + n; },
                noEventsText: 'Nenhuma locação para o período'
            };

            calendar = new CalendarCtor(calendarEl, {
                initialView: 'dayGridMonth',
                locale: localePtBr,
                height: 'auto',
                plugins: plugins,
                noEventsContent: 'Nenhuma locação para o período',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: {
                    url: '{{ route("calendario.eventos") }}',
                    failure: function (error) {
                        console.error('FullCalendar failure:', error);
                        let mensagem = 'Falha ao carregar locações.';

                        try {
                            if (error && error.message && error.message !== 'Request failed') {
                                mensagem = error.message;
                            }
                            const xhr = error && error.xhr ? error.xhr : null;
                            if (xhr && xhr.responseText) {
                                const payload = JSON.parse(xhr.responseText);
                                if (payload && payload.message) {
                                    mensagem = payload.message;
                                }
                            }
                        } catch (e) {}

                        mostrarFeedback(mensagem, 'danger');
                    },
                    success: function (rawEvents) {
                        // Popular filtros a partir dos dados brutos (sem tocar no DOM do calendar)
                        popularFiltrosComDados(rawEvents);
                        return rawEvents;
                    }
                },
                eventDidMount: function (info) {
                    var props = info.event.extendedProps || {};
                    var statusSelecionados = obterStatusSelecionados();
                    var statusConhecidos = obterStatusConhecidos();
                    var statusEvento = normalizarTexto(props.status || '');
                    var statusDesconhecido = statusEvento && !statusConhecidos.has(statusEvento);
                    var possuiStatus = !statusEvento || statusSelecionados.has(statusEvento) || statusDesconhecido;

                    var clienteSelecionado = normalizarTexto(filtroClienteEl ? filtroClienteEl.value : '');
                    var clienteEvento = normalizarTexto(props.cliente || '');
                    var possuiCliente = !clienteSelecionado || clienteEvento === clienteSelecionado;

                    var produtoSelecionado = normalizarTexto(filtroProdutoEl ? filtroProdutoEl.value : '');
                    var produtosEvento = Array.isArray(props.produtos) ? props.produtos : [];
                    var possuiProduto = !produtoSelecionado || produtosEvento.some(function(n) {
                        return normalizarTexto(n) === produtoSelecionado;
                    });

                    if (!possuiStatus || !possuiCliente || !possuiProduto) {
                        info.el.style.display = 'none';
                    }

                    var tooltip = [info.event.title];
                    if (props.produtos_resumo) {
                        tooltip.push('Produtos: ' + props.produtos_resumo);
                    }
                    info.el.setAttribute('title', tooltip.join('\n'));
                },
                eventClick: function (info) {
                    info.jsEvent.preventDefault();
                    const props = info.event.extendedProps || {};

                    detalheCliente.textContent = props.cliente || '-';
                    detalheContrato.textContent = props.numero_contrato || '-';
                    renderizarStatusDetalhe(props.status_label || '-', props.status_color, props.status_text_color);
                    renderizarProdutosDetalhe(Array.isArray(props.produtos_detalhados) ? props.produtos_detalhados : []);
                    detalheQtdItens.textContent = props.quantidade_itens || 0;
                    btnVerLocacao.href = props.url_detalhe || '#';

                    if (!modal && window.bootstrap && modalEl) {
                        modal = new window.bootstrap.Modal(modalEl);
                    }

                    if (modal) {
                        modal.show();
                    } else if (props.url_detalhe) {
                        window.open(props.url_detalhe, '_blank');
                    }
                }
            });

            calendar.render();

            if (selectAllStatusEl) {
                selectAllStatusEl.addEventListener('change', function() {
                    statusFilterEls.forEach(function(input) {
                        input.checked = selectAllStatusEl.checked;
                    });

                    aplicarFiltrosCalendario();
                });
            }

            statusFilterEls.forEach(function(input) {
                input.addEventListener('change', function() {
                    const total = statusFilterEls.length;
                    const marcados = Array.from(statusFilterEls).filter(function(item) {
                        return item.checked;
                    }).length;

                    if (selectAllStatusEl) {
                        selectAllStatusEl.checked = marcados === total;
                    }

                    aplicarFiltrosCalendario();
                });
            });

            if (filtroClienteEl) {
                filtroClienteEl.addEventListener('change', aplicarFiltrosCalendario);
            }

            if (filtroProdutoEl) {
                filtroProdutoEl.addEventListener('change', aplicarFiltrosCalendario);
            }
        } catch (error) {
            calendarEl.innerHTML = '<div class="alert alert-danger mb-0">Erro ao inicializar o calendário.</div>';
            console.error(error);
        }
    });
</script>
@endsection
