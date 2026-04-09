@extends('layouts.layoutMaster')

@section('title', 'Gerenciamento de Produtos')

@php
    $podeCriarProdutos = \Perm::pode(auth()->user(), 'produtos.criar');
    $podeEditarProdutos = \Perm::pode(auth()->user(), 'produtos.editar');
    $podeExcluirProdutos = \Perm::pode(auth()->user(), 'produtos.excluir');
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('page-style')
<style>
    .produtos-table-wrapper {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .produtos-table {
        min-width: 860px;
    }

    @media (max-width: 767.98px) {
        .produtos-filtros-header,
        .produtos-list-header {
            gap: .6rem;
            align-items: flex-start !important;
        }

        .produtos-filtros-header > div,
        .produtos-list-header > div {
            width: 100%;
        }

        .produtos-filtros-header .btn,
        .produtos-list-header .btn {
            width: 100%;
        }

        .produtos-paginacao {
            flex-direction: column;
            align-items: flex-start !important;
            gap: .75rem;
        }
    }

    html.dark-style .produtos-table-wrapper {
        scrollbar-color: #5a6288 #2f3349;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="row">
                <!-- Cards de Estatísticas -->
                <div class="col-12">
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['total'] ?? 0 }}</h4>
                                            </div>
                                            <span>Total de Produtos</span>
                                        </div>
                                        <span class="badge bg-label-primary rounded p-2">
                                            <i class="ti ti-package ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <span>Produtos Ativos</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['ativos'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-success rounded p-2">
                                            <i class="ti ti-check ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <span>Produtos Inativos</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['inativos'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-warning rounded p-2">
                                            <i class="ti ti-x ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros e Ações -->
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center produtos-filtros-header">
                            <h5 class="mb-0">Filtros de Busca</h5>
                            <div>
                                @if($podeCriarProdutos)
                                    <a href="{{ route('produtos.criar') }}" class="btn btn-primary">
                                        <i class="ti ti-plus me-1"></i>
                                        Novo Produto
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-12 col-md-5">
                                    <label class="form-label small mb-1">Buscar</label>
                                    <input type="text" name="busca" class="form-control" placeholder="Nome, código ou nº série" value="{{ $filters['busca'] ?? '' }}">
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label small mb-1">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ativo" {{ (($filters['status'] ?? '') == 'ativo') ? 'selected' : '' }}>Ativo</option>
                                        <option value="inativo" {{ (($filters['status'] ?? '') == 'inativo') ? 'selected' : '' }}>Inativo</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="ti ti-search me-1"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Produtos -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center produtos-list-header">
                            <h5 class="mb-0">Produtos</h5>
                            @if($podeExcluirProdutos)
                                <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSelecionados" data-url="{{ route('produtos.excluir-multiplos') }}" style="display: none;">
                                    <i class="ti ti-trash me-1"></i>
                                    Excluir Selecionados (<span id="countSelecionados">0</span>)
                                </button>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="table-responsive produtos-table-wrapper">
                                <table class="table table-hover produtos-table">
                                    <thead>
                                        <tr>
                                            @if($podeExcluirProdutos)
                                                <th style="width: 40px;">
                                                    <input type="checkbox" class="form-check-input" id="checkAll">
                                                </th>
                                            @endif
                                            <th>Produto</th>
                                            <th>Código</th>
                                            <th>Preço Venda</th>
                                            <th>Preço Locação</th>
                                            <th>Estoque</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($produtos as $produto)
                                            <tr>
                                                @if($podeExcluirProdutos)
                                                    <td>
                                                        <input type="checkbox" class="form-check-input produto-checkbox" value="{{ $produto->id_produto }}">
                                                    </td>
                                                @endif
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        @if(!empty($produto->foto_url))
                                                            <div class="avatar avatar-sm me-3">
                                                                <img src="{{ $produto->foto_url }}" alt="{{ $produto->nome }}" class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                                                <span class="avatar-initial rounded-circle bg-label-primary d-none">{{ $produto->inicial }}</span>
                                                            </div>
                                                        @else
                                                            <div class="avatar avatar-sm me-3">
                                                                <span class="avatar-initial rounded-circle bg-label-primary">{{ $produto->inicial }}</span>
                                                            </div>
                                                        @endif
                                                        <div class="d-flex flex-column">
                                                            <strong>{{ $produto->nome }}</strong>
                                                            @if($produto->numero_serie)
                                                            <small class="text-muted">S/N: {{ $produto->numero_serie }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $produto->codigo ?? '-' }}</td>
                                                <td>{{ $produto->preco_formatado }}</td>
                                                <td>R$ {{ number_format($produto->preco_locacao ?? 0, 2, ',', '.') }}</td>
                                                <td>{{ ($produto->patrimonios_count ?? 0) > 0 ? ($produto->patrimonios_disponiveis_count ?? 0) : ($produto->quantidade ?? 0) }}</td>
                                                <td>
                                                    @if($produto->status === 'ativo') 
                                                        <span class="badge bg-label-success">Ativo</span>
                                                    @else 
                                                        <span class="badge bg-label-warning">Inativo</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                            <i class="ti ti-dots-vertical"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="verLogAtividadesProduto({{ $produto->id_produto }}, '{{ addslashes($produto->nome) }}')">
                                                                <i class="ti ti-activity me-2"></i>Log de Atividades
                                                            </a>
                                                            @if($podeEditarProdutos)
                                                                <a class="dropdown-item" href="{{ route('produtos.edit', $produto->id_produto) }}">
                                                                    <i class="ti ti-pencil me-2"></i>Editar
                                                                </a>
                                                            @endif
                                                            <a class="dropdown-item btn-abrir-info-produto" href="javascript:void(0);" data-id="{{ $produto->id_produto }}" data-nome="{{ $produto->nome }}">
                                                                <i class="ti ti-report-analytics me-2"></i>Informações do Produto
                                                            </a>
                                                            @if($podeExcluirProdutos)
                                                                <div class="dropdown-divider"></div>
                                                                <a href="javascript:void(0)" class="dropdown-item text-danger produto-action" data-action="delete" data-id="{{ $produto->id_produto }}" data-base-url="{{ url('produtos') }}">
                                                                    <i class="ti ti-trash me-2"></i>Deletar
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $podeExcluirProdutos ? 8 : 7 }}" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ti ti-package-off ti-lg mb-2"></i>
                                                        <p class="mb-0">Nenhum produto encontrado</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($produtos, 'links') && $produtos->total() > 0)
                                <div class="d-flex justify-content-between align-items-center mt-4 produtos-paginacao">
                                    <div class="text-muted">
                                        Mostrando {{ $produtos->firstItem() }} até {{ $produtos->lastItem() }} de {{ $produtos->total() }} registros
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination mb-0">
                                            {{-- Primeira página --}}
                                            @if ($produtos->onFirstPage())
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevrons-left ti-xs"></i></span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $produtos->appends(request()->query())->url(1) }}"><i class="ti ti-chevrons-left ti-xs"></i></a>
                                                </li>
                                            @endif

                                            {{-- Página anterior --}}
                                            @if ($produtos->onFirstPage())
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevron-left ti-xs"></i></span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $produtos->appends(request()->query())->previousPageUrl() }}"><i class="ti ti-chevron-left ti-xs"></i></a>
                                                </li>
                                            @endif

                                            {{-- Páginas numeradas --}}
                                            @php
                                                $start = max($produtos->currentPage() - 2, 1);
                                                $end = min($start + 4, $produtos->lastPage());
                                                $start = max($end - 4, 1);
                                            @endphp

                                            @for ($i = $start; $i <= $end; $i++)
                                                @if ($i == $produtos->currentPage())
                                                    <li class="page-item active">
                                                        <span class="page-link">{{ $i }}</span>
                                                    </li>
                                                @else
                                                    <li class="page-item">
                                                        <a class="page-link" href="{{ $produtos->appends(request()->query())->url($i) }}">{{ $i }}</a>
                                                    </li>
                                                @endif
                                            @endfor

                                            {{-- Próxima página --}}
                                            @if ($produtos->hasMorePages())
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $produtos->appends(request()->query())->nextPageUrl() }}"><i class="ti ti-chevron-right ti-xs"></i></a>
                                                </li>
                                            @else
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevron-right ti-xs"></i></span>
                                                </li>
                                            @endif

                                            {{-- Última página --}}
                                            @if ($produtos->hasMorePages())
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $produtos->appends(request()->query())->url($produtos->lastPage()) }}"><i class="ti ti-chevrons-right ti-xs"></i></a>
                                                </li>
                                            @else
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevrons-right ti-xs"></i></span>
                                                </li>
                                            @endif
                                        </ul>
                                    </nav>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalInfoProduto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalInfoProdutoTitle">Informações do Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalInfoProdutoBody">
                <div class="text-center py-4 text-muted">
                    <i class="spinner-border spinner-border-sm me-1"></i> Carregando informações...
                </div>
            </div>
            <div class="modal-footer">
                <a href="javascript:void(0);" class="btn btn-outline-danger" id="btnExportarInfoPdf" target="_blank">
                    <i class="ti ti-file-type-pdf me-1"></i> PDF
                </a>
                <a href="javascript:void(0);" class="btn btn-outline-success" id="btnExportarInfoExcel" target="_blank">
                    <i class="ti ti-file-spreadsheet me-1"></i> Excel
                </a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    const rotaInfoBase = @json(route('produtos.informacoes', ['produto' => '__ID__']));
    const rotaInfoPdfBase = @json(route('produtos.informacoes.pdf', ['produto' => '__ID__']));
    const rotaInfoExcelBase = @json(route('produtos.informacoes.excel', ['produto' => '__ID__']));

    document.querySelectorAll('.produtos-table [data-bs-toggle="dropdown"]').forEach(function(toggle) {
        bootstrap.Dropdown.getOrCreateInstance(toggle, {
            boundary: 'viewport',
            popperConfig: function(defaultBsPopperConfig) {
                return {
                    ...defaultBsPopperConfig,
                    strategy: 'fixed'
                };
            }
        });
    });

    function moeda(valor) {
        const numero = Number(valor || 0);
        return 'R$ ' + numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escaparHtml(texto) {
        return String(texto ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderizarInfoProduto(payload) {
        const infoFinanceira = payload.info_financeira || {};
        const patrimonios = payload.info_patrimonios || [];

        let linhasPatrimonio = '';
        if (patrimonios.length > 0) {
            linhasPatrimonio = patrimonios.map((item) => {
                const lucro = Number(item.lucro || 0);
                const classeLucro = lucro >= 0 ? 'text-success' : 'text-danger';

                return `
                    <tr>
                        <td><strong>${escaparHtml(item.numero_serie || '-')}</strong></td>
                        <td>${escaparHtml(item.status_locacao || '-')}</td>
                        <td>${moeda(item.receita)}</td>
                        <td>${moeda(item.gasto_manutencao)}</td>
                        <td class="${classeLucro}"><strong>${moeda(item.lucro)}</strong></td>
                    </tr>
                `;
            }).join('');
        } else {
            linhasPatrimonio = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-3">Nenhum patrimônio para calcular rentabilidade</td>
                </tr>
            `;
        }

        return `
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card bg-label-success mb-0">
                        <div class="card-body text-center py-3">
                            <h4 class="mb-1">${moeda(infoFinanceira.receita)}</h4>
                            <small>Receita em Locações (Aprovadas/Encerradas)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-label-danger mb-0">
                        <div class="card-body text-center py-3">
                            <h4 class="mb-1">${moeda(infoFinanceira.gasto_manutencao)}</h4>
                            <small>Gasto com Manutenções</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card ${Number(infoFinanceira.lucro || 0) >= 0 ? 'bg-label-primary' : 'bg-label-warning'} mb-0">
                        <div class="card-body text-center py-3">
                            <h4 class="mb-1">${moeda(infoFinanceira.lucro)}</h4>
                            <small>Lucratividade do Produto</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card mb-0">
                        <div class="card-body py-3 d-flex justify-content-between align-items-center">
                            <span>Itens de locação contabilizados</span>
                            <strong>${Number(infoFinanceira.qtd_locacoes_rentaveis || 0)}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-0">
                        <div class="card-body py-3 d-flex justify-content-between align-items-center">
                            <span>Manutenções contabilizadas</span>
                            <strong>${Number(infoFinanceira.qtd_manutencoes || 0)}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <h6 class="mb-3">Rentabilidade por Patrimônio</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Patrimônio</th>
                            <th>Status</th>
                            <th>Receita</th>
                            <th>Gasto Manutenção</th>
                            <th>Lucro</th>
                        </tr>
                    </thead>
                    <tbody>${linhasPatrimonio}</tbody>
                </table>
            </div>
        `;
    }

    // Check All / Uncheck All
    $('#checkAll').on('change', function() {
        $('.produto-checkbox').prop('checked', $(this).prop('checked'));
        atualizarBotaoExcluir();
    });

    // Atualizar contador quando checkboxes individuais mudam
    $('.produto-checkbox').on('change', function() {
        atualizarBotaoExcluir();
    });

    function atualizarBotaoExcluir() {
        const selecionados = $('.produto-checkbox:checked').length;
        $('#countSelecionados').text(selecionados);
        if (selecionados > 0) {
            $('#btnExcluirSelecionados').show();
        } else {
            $('#btnExcluirSelecionados').hide();
        }
    }

    // Excluir múltiplos
    $('#btnExcluirSelecionados').on('click', function() {
        const ids = [];
        $('.produto-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: 'Selecione pelo menos um produto.'
            });
            return;
        }

        Swal.fire({
            title: 'Confirmar exclusão?',
            text: `Deseja realmente excluir ${ids.length} produto(s)?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: $(this).data('url'),
                    method: 'POST',
                    data: {
                        ids: ids,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: response.message
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: response.message
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro ao excluir produtos.'
                        });
                    }
                });
            }
        });
    });

    // Ações individuais (deletar)
    $(document).on('click', '.produto-action', function() {
        const action = $(this).data('action');
        const id = $(this).data('id');
        const baseUrl = $(this).data('base-url');

        if (action === 'delete') {
            Swal.fire({
                title: 'Confirmar exclusão?',
                text: 'Deseja realmente excluir este produto?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `${baseUrl}/${id}`,
                        method: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sucesso!',
                                    text: response.message
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Erro ao excluir produto.'
                            });
                        }
                    });
                }
            });
        }
    });

    $(document).on('click', '.btn-abrir-info-produto', function() {
        const idProduto = $(this).data('id');
        const nomeProduto = $(this).data('nome') || 'Produto';
        const urlInfo = rotaInfoBase.replace('__ID__', idProduto);
        const urlPdf = rotaInfoPdfBase.replace('__ID__', idProduto);
        const urlExcel = rotaInfoExcelBase.replace('__ID__', idProduto);

        $('#modalInfoProdutoTitle').text(`Informações do Produto - ${nomeProduto}`);
        $('#btnExportarInfoPdf').attr('href', urlPdf);
        $('#btnExportarInfoExcel').attr('href', urlExcel);
        $('#modalInfoProdutoBody').html(`
            <div class="text-center py-4 text-muted">
                <i class="spinner-border spinner-border-sm me-1"></i> Carregando informações...
            </div>
        `);

        const modal = new bootstrap.Modal(document.getElementById('modalInfoProduto'));
        modal.show();

        $.ajax({
            url: urlInfo,
            method: 'GET',
            success: function(response) {
                if (!response.success) {
                    $('#modalInfoProdutoBody').html('<div class="alert alert-danger mb-0">Não foi possível carregar as informações do produto.</div>');
                    return;
                }

                $('#modalInfoProdutoBody').html(renderizarInfoProduto(response));
            },
            error: function() {
                $('#modalInfoProdutoBody').html('<div class="alert alert-danger mb-0">Erro ao carregar as informações do produto.</div>');
            }
        });
    });
});
</script>

<script>
let estadoLogAtividadesProduto = {
    idProduto: null,
    nomeProduto: '',
    escopo: 'todos',
    contagens: {
        todos: 0,
        produto: 0,
        patrimonios: 0,
        tabela_precos: 0,
    },
};

function verLogAtividadesProduto(idProduto, nomeProduto) {
    estadoLogAtividadesProduto = {
        idProduto: idProduto,
        nomeProduto: nomeProduto || '',
        escopo: 'todos',
        contagens: {
            todos: 0,
            produto: 0,
            patrimonios: 0,
            tabela_precos: 0,
        },
    };

    mostrarModalLogAtividadesProduto(estadoLogAtividadesProduto.nomeProduto, [], {
        escopo: 'todos',
        contagens: estadoLogAtividadesProduto.contagens,
        carregando: true,
    });

    carregarLogAtividadesProdutoPorEscopo('todos');
}

function carregarLogAtividadesProdutoPorEscopo(escopo) {
    if (!estadoLogAtividadesProduto.idProduto) {
        return;
    }

    estadoLogAtividadesProduto.escopo = escopo;

    $.ajax({
        url: `/produtos/${estadoLogAtividadesProduto.idProduto}/logs-atividades`,
        data: { escopo: escopo },
        method: 'GET',
        success: function(response) {
            if (response.success) {
                estadoLogAtividadesProduto.contagens = {
                    todos: Number(response?.contagens?.todos || 0),
                    produto: Number(response?.contagens?.produto || 0),
                    patrimonios: Number(response?.contagens?.patrimonios || 0),
                    tabela_precos: Number(response?.contagens?.tabela_precos || 0),
                };

                estadoLogAtividadesProduto.escopo = response.escopo || escopo;

                mostrarModalLogAtividadesProduto(
                    estadoLogAtividadesProduto.nomeProduto,
                    response.logs || [],
                    {
                        escopo: estadoLogAtividadesProduto.escopo,
                        contagens: estadoLogAtividadesProduto.contagens,
                        carregando: false,
                        atualizarConteudo: true,
                    }
                );
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.message || 'Não foi possível carregar o log de atividades.'
                });
            }
        },
        error: function(xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: xhr.responseJSON?.message || 'Erro ao carregar o log de atividades.'
            });
        }
    });
}

function mostrarModalLogAtividadesProduto(nomeProduto, logs, opcoes = {}) {
    const escopo = opcoes.escopo || 'todos';
    const contagens = opcoes.contagens || {
        todos: Array.isArray(logs) ? logs.length : 0,
        produto: 0,
        patrimonios: 0,
        tabela_precos: 0,
    };
    const carregando = opcoes.carregando === true;
    const html = gerarHtmlLogAtividadesProduto(nomeProduto, logs, escopo, contagens, carregando);

    if (opcoes.atualizarConteudo) {
        const container = Swal.getHtmlContainer();
        if (container) {
            container.innerHTML = html;
            return;
        }
    }

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

function gerarHtmlLogAtividadesProduto(nomeProduto, logs, escopo, contagens, carregando) {
    const totalLogs = Array.isArray(logs) ? logs.length : 0;
    const rotuloEscopo = {
        todos: 'Todos',
        produto: 'Produto',
        patrimonios: 'Patrimonios',
        tabela_precos: 'Tabelas de Precos',
    };

    const totalEscopo = Number(contagens?.[escopo] ?? totalLogs);

    const botaoEscopo = function (valor, titulo, icone) {
        const ativo = escopo === valor;
        const classe = ativo ? 'btn-primary' : 'btn-outline-primary';
        const contador = Number(contagens?.[valor] ?? 0);

        return `<button type="button" class="btn btn-sm ${classe}" onclick="filtrarLogsAtividadesProduto('${valor}')"><i class="ti ti-${icone} me-1"></i>${titulo} (${contador})</button>`;
    };

    let html = `
        <div class="text-start p-4 border-bottom bg-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Produto</div>
                    <div class="fw-bold mb-0" style="font-size: 1.05rem; line-height: 1.4;">${escapeHtmlLogProduto(nomeProduto || '-')}</div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                    <span class="badge bg-label-primary fw-semibold px-3 py-2" style="font-size: 0.85rem;">
                        <i class="ti ti-list-check me-1"></i>${totalEscopo} registro${totalEscopo !== 1 ? 's' : ''}
                    </span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-3 justify-content-end">
                ${botaoEscopo('todos', 'Todos', 'list')}
                ${botaoEscopo('produto', 'Produto', 'package')}
                ${botaoEscopo('patrimonios', 'Patrimonios', 'box')}
                ${botaoEscopo('tabela_precos', 'Tabela de Precos', 'grid-dots')}
            </div>
        </div>
    `;

    if (carregando) {
        html += `
            <div class="p-4">
                <div class="alert alert-info mb-0 text-center rounded-3" style="padding: 2rem;">
                    <i class="spinner-border spinner-border-sm me-2"></i>
                    Carregando log de atividades...
                </div>
            </div>
        `;
        return html;
    }

    if (!logs || logs.length === 0) {
        html += `
            <div class="p-4">
                <div class="alert alert-info mb-0 text-center rounded-3" style="padding: 2rem;">
                    <i class="ti ti-info-circle mb-2" style="font-size: 2rem;"></i>
                    <div class="fw-semibold">Nenhum log de atividade encontrado</div>
                    <div class="text-muted small mt-1">Nenhum registro para o filtro: ${rotuloEscopo[escopo] || 'Todos'}.</div>
                </div>
            </div>
        `;
        return html;
    }

    html += '<div class="bg-body-secondary" style="max-height: 600px; overflow-y: auto; padding: 1.5rem;">';

    logs.forEach((item, index) => {
        const cor = normalizarCorLogProduto(item.cor);
        const icone = item.icone || 'activity';
        const responsavel = escapeHtmlLogProduto(item.nome_responsavel || item.email_responsavel || 'Sistema');
        const dataHora = formatDateTimeLogProduto(item.ocorrido_em);
        const acao = formatarAcaoLogProduto(item.acao || '-');
        const descricaoItem = escapeHtmlLogProduto(item.descricao || 'Atividade registrada');
        const origem = formatarOrigemEntidadeLogProduto(item.entidade_tipo);

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
                                <span class="badge bg-${cor} fw-semibold" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">${acao}</span>
                                <span class="badge bg-label-secondary fw-semibold ms-1" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">${origem}</span>
                            </div>
                        </div>
                    </div>
                    ${(temAntes || temDepois) ? `
                        <div class="border-top pt-3 mt-3">
                            <div class="row g-3">
                                ${temAntes ? `<div class="col-md-${temDepois ? '6' : '12'}"><div class="p-3 rounded-3 h-100 bg-label-danger border border-danger border-opacity-25"><div class="d-flex align-items-center mb-3"><span class="avatar avatar-xs bg-label-danger me-2"><i class="ti ti-arrow-left" style="font-size: 0.8rem;"></i></span><strong class="text-danger" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Antes</strong></div><div class="small" style="line-height: 1.8;">${formatarObjetoDetalhadoLogProduto(item.antes)}</div></div></div>` : ''}
                                ${temDepois ? `<div class="col-md-${temAntes ? '6' : '12'}"><div class="p-3 rounded-3 h-100 bg-label-success border border-success border-opacity-25"><div class="d-flex align-items-center mb-3"><span class="avatar avatar-xs bg-label-success me-2"><i class="ti ti-arrow-right" style="font-size: 0.8rem;"></i></span><strong class="text-success" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Depois</strong></div><div class="small" style="line-height: 1.8;">${formatarObjetoDetalhadoLogProduto(item.depois)}</div></div></div>` : ''}
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

function formatarObjetoDetalhadoLogProduto(obj) {
    if (!obj || typeof obj !== 'object') {
        return '<span class="text-muted fst-italic">Sem dados</span>';
    }

    const entries = Object.entries(obj);
    if (entries.length === 0) {
        return '<span class="text-muted fst-italic">Sem alterações</span>';
    }

    let html = '<div class="d-flex flex-column gap-2">';
    entries.forEach(([chave, valor]) => {
        const chaveFormatada = String(chave).replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
        const valorTexto = valor === null || valor === undefined || valor === '' ? '(vazio)' : String(valor);

        html += `
            <div class="d-flex gap-2">
                <span class="fw-semibold text-nowrap text-body-secondary" style="min-width: 120px;">${escapeHtmlLogProduto(chaveFormatada)}:</span>
                <span class="flex-grow-1 text-break">${escapeHtmlLogProduto(valorTexto)}</span>
            </div>
        `;
    });

    html += '</div>';
    return html;
}

function normalizarCorLogProduto(cor) {
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

function formatDateTimeLogProduto(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('pt-BR');
}

function formatarAcaoLogProduto(acao) {
    if (!acao || acao === '-') return '-';
    return String(acao).replace(/[_\.]+/g, ' ').trim();
}

function formatarOrigemEntidadeLogProduto(entidadeTipo) {
    const valor = String(entidadeTipo || '').toLowerCase();

    if (valor === 'produto') {
        return 'Produto';
    }

    if (valor === 'patrimonio' || valor === 'patrimonios') {
        return 'Patrimonio';
    }

    if (valor === 'tabelapreco' || valor === 'tabela_preco' || valor === 'tabela_precos') {
        return 'Tabela de Precos';
    }

    return entidadeTipo || 'Registro';
}

function filtrarLogsAtividadesProduto(escopo) {
    carregarLogAtividadesProdutoPorEscopo(escopo);
}

function escapeHtmlLogProduto(texto) {
    return String(texto)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

window.verLogAtividadesProduto = verLogAtividadesProduto;
window.filtrarLogsAtividadesProduto = filtrarLogsAtividadesProduto;
</script>

@if(session('success'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: '{{ session('success') }}',
        confirmButtonText: 'OK',
        timer: 2000,
        timerProgressBar: true
    });
});
</script>
@endif

@if(session('error'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'error',
        title: 'Erro!',
        text: '{{ session('error') }}',
        confirmButtonText: 'OK'
    });
});
</script>
@endif
@endsection
