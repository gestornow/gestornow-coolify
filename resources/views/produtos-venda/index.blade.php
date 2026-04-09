@extends('layouts.layoutMaster')

@section('title', 'Produtos para Venda')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
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

    .estoque-baixo {
        color: #ff9800 !important;
        font-weight: 600;
    }

    .sem-estoque {
        color: #f44336 !important;
        font-weight: 600;
    }

    html.dark-style .produtos-table-wrapper {
        scrollbar-color: #5a6288 #2f3349;
    }

    /* Fix dropdown z-index */
    .dropdown-menu {
        z-index: 1050 !important;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .produtos-table-wrapper {
        overflow: visible !important;
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
                        <div class="col-sm-6 col-xl-3">
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

                        <div class="col-sm-6 col-xl-3">
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

                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <span>Estoque Baixo</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['estoque_baixo'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-warning rounded p-2">
                                            <i class="ti ti-alert-triangle ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <span>Sem Estoque</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['sem_estoque'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-danger rounded p-2">
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
                        <div class="card-header d-flex justify-content-between align-items-center produtos-filtros-header flex-wrap gap-2">
                            <h5 class="mb-0">Filtros de Busca</h5>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('formas-pagamento.index') }}" class="btn btn-outline-primary">
                                    <i class="ti ti-credit-card me-1"></i>
                                    Formas Pagamento
                                </a>
                                <a href="{{ route('pdv.index') }}" class="btn btn-success">
                                    <i class="ti ti-shopping-cart me-1"></i>
                                    Abrir PDV
                                </a>
                                <a href="{{ route('produtos-venda.create') }}" class="btn btn-primary">
                                    <i class="ti ti-plus me-1"></i>
                                    Novo Produto
                                </a>
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
                            <h5 class="mb-0">Produtos para Venda</h5>
                            <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSelecionados" data-url="{{ route('produtos-venda.excluir-multiplos') }}" style="display: none;">
                                <i class="ti ti-trash me-1"></i>
                                Excluir Selecionados (<span id="countSelecionados">0</span>)
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive produtos-table-wrapper">
                                <table class="table table-hover produtos-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="checkAll">
                                            </th>
                                            <th>Produto</th>
                                            <th>Código</th>
                                            <th>Preço Custo</th>
                                            <th>Preço Venda</th>
                                            <th>Estoque</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($produtos as $produto)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input produto-checkbox" value="{{ $produto->id_produto_venda }}">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-3">
                                                            <span class="avatar-initial rounded-circle bg-label-primary">{{ $produto->inicial }}</span>
                                                        </div>
                                                        <div class="d-flex flex-column">
                                                            <strong>{{ $produto->nome }}</strong>
                                                            @if($produto->numero_serie)
                                                            <small class="text-muted">S/N: {{ $produto->numero_serie }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $produto->codigo ?? '-' }}</td>
                                                <td>{{ $produto->preco_custo_formatado }}</td>
                                                <td>{{ $produto->preco_formatado }}</td>
                                                <td>
                                                    @php $qtd = $produto->quantidade ?? 0; @endphp
                                                    @if($qtd <= 0)
                                                        <span class="sem-estoque">{{ $qtd }}</span>
                                                    @elseif($qtd <= 5)
                                                        <span class="estoque-baixo">{{ $qtd }}</span>
                                                    @else
                                                        {{ $qtd }}
                                                    @endif
                                                </td>
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
                                                            <a class="dropdown-item" href="{{ route('produtos-venda.edit', $produto->id_produto_venda) }}">
                                                                <i class="ti ti-pencil me-2"></i>Editar
                                                            </a>
                                                            <a class="dropdown-item btn-ajustar-estoque" href="javascript:void(0);" data-id="{{ $produto->id_produto_venda }}" data-nome="{{ $produto->nome }}" data-quantidade="{{ $produto->quantidade ?? 0 }}">
                                                                <i class="ti ti-packages me-2"></i>Ajustar Estoque
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a href="javascript:void(0)" class="dropdown-item text-danger produto-action" data-action="delete" data-id="{{ $produto->id_produto_venda }}" data-base-url="{{ url('produtos-venda') }}">
                                                                <i class="ti ti-trash me-2"></i>Deletar
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
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
                                        {{ $produtos->appends(request()->query())->links() }}
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

<!-- Modal Ajustar Estoque -->
<div class="modal fade" id="modalAjustarEstoque" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajustar Estoque</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Produto:</strong> <span id="nomeProdutoEstoque"></span></p>
                <p><strong>Estoque Atual:</strong> <span id="estoqueAtual"></span></p>
                <div class="mb-3">
                    <label for="novaQuantidade" class="form-label">Nova Quantidade</label>
                    <input type="number" class="form-control" id="novaQuantidade" min="0" required>
                </div>
                <div class="mb-3">
                    <label for="motivoAjuste" class="form-label">Motivo (opcional)</label>
                    <textarea class="form-control" id="motivoAjuste" rows="2"></textarea>
                </div>
                <input type="hidden" id="idProdutoEstoque">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarAjuste">Confirmar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // SweetAlert de sucesso (se tiver na sessão)
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: '{{ session('error') }}'
        });
    @endif

    // Checkbox select all
    $('#checkAll').on('change', function() {
        $('.produto-checkbox').prop('checked', $(this).prop('checked'));
        atualizarBotaoExcluir();
    });

    $('.produto-checkbox').on('change', function() {
        atualizarBotaoExcluir();
    });

    function atualizarBotaoExcluir() {
        const selecionados = $('.produto-checkbox:checked').length;
        if (selecionados > 0) {
            $('#btnExcluirSelecionados').show();
            $('#countSelecionados').text(selecionados);
        } else {
            $('#btnExcluirSelecionados').hide();
        }
    }

    // Excluir múltiplos
    $('#btnExcluirSelecionados').on('click', function() {
        const ids = $('.produto-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (ids.length === 0) return;

        Swal.fire({
            title: 'Confirmar exclusão?',
            text: `Deseja excluir ${ids.length} produto(s)?`,
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
                            Swal.fire('Excluído!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir', 'error');
                    }
                });
            }
        });
    });

    // Excluir individual
    $('.produto-action[data-action="delete"]').on('click', function() {
        const id = $(this).data('id');
        const url = $(this).data('base-url') + '/' + id;

        Swal.fire({
            title: 'Confirmar exclusão?',
            text: 'Esta ação não pode ser desfeita.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: url,
                    method: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Excluído!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir', 'error');
                    }
                });
            }
        });
    });

    // Modal ajustar estoque
    $('.btn-ajustar-estoque').on('click', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        const quantidade = $(this).data('quantidade');

        $('#idProdutoEstoque').val(id);
        $('#nomeProdutoEstoque').text(nome);
        $('#estoqueAtual').text(quantidade);
        $('#novaQuantidade').val(quantidade);
        $('#motivoAjuste').val('');

        $('#modalAjustarEstoque').modal('show');
    });

    // Confirmar ajuste de estoque
    $('#btnConfirmarAjuste').on('click', function() {
        const id = $('#idProdutoEstoque').val();
        const quantidade = $('#novaQuantidade').val();
        const motivo = $('#motivoAjuste').val();

        if (quantidade < 0) {
            Swal.fire('Erro!', 'A quantidade não pode ser negativa.', 'error');
            return;
        }

        $.ajax({
            url: '{{ url("produtos-venda") }}/' + id + '/ajustar-estoque',
            method: 'POST',
            data: {
                quantidade: quantidade,
                motivo: motivo,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    $('#modalAjustarEstoque').modal('hide');
                    Swal.fire('Sucesso!', response.message, 'success').then(() => {
                        location.reload();
                    });
                }
            },
            error: function(xhr) {
                Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao ajustar estoque', 'error');
            }
        });
    });
});
</script>
@endsection
