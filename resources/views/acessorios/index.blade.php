@extends('layouts.layoutMaster')

@section('title', 'Gerenciamento de Acessórios')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            
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
                                            <span>Total de Acessórios</span>
                                        </div>
                                        <span class="badge bg-label-primary rounded p-2">
                                            <i class="ti ti-plug ti-sm"></i>
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
                                            <span>Acessórios Ativos</span>
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
                                            <span>Acessórios Inativos</span>
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Filtros de Busca</h5>
                            <div>
                                <a href="{{ route('acessorios.create') }}" class="btn btn-primary">
                                    <i class="ti ti-plus me-1"></i>
                                    Novo Acessório
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

                <!-- Tabela de Acessórios -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Acessórios</h5>
                            <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSelecionados" data-url="{{ route('acessorios.excluir-multiplos') }}" style="display: none;">
                                <i class="ti ti-trash me-1"></i>
                                Excluir Selecionados (<span id="countSelecionados">0</span>)
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="overflow: visible;">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="checkAll">
                                            </th>
                                            <th>Acessório</th>
                                            <th>Código</th>
                                            <th>Quantidade</th>
                                            <th>Preço Locação</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($acessorios as $acessorio)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input acessorio-checkbox" value="{{ $acessorio->id_acessorio }}">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-3">
                                                            <span class="avatar-initial rounded-circle bg-label-info">{{ $acessorio->inicial }}</span>
                                                        </div>
                                                        <div class="d-flex flex-column">
                                                            <strong>{{ $acessorio->nome }}</strong>
                                                            @if($acessorio->numero_serie)
                                                            <small class="text-muted">S/N: {{ $acessorio->numero_serie }}</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $acessorio->codigo ?? '-' }}</td>
                                                <td>{{ $acessorio->quantidade ?? 0 }}</td>
                                                <td>R$ {{ number_format($acessorio->preco_locacao ?? 0, 2, ',', '.') }}</td>
                                                <td>
                                                    @if($acessorio->status === 'ativo') 
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
                                                            <a class="dropdown-item" href="{{ route('acessorios.edit', $acessorio->id_acessorio) }}">
                                                                <i class="ti ti-pencil me-2"></i>Editar
                                                            </a>
                                                            <a class="dropdown-item" href="{{ route('acessorios.show', $acessorio->id_acessorio) }}">
                                                                <i class="ti ti-eye me-2"></i>Visualizar
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a href="javascript:void(0)" class="dropdown-item text-danger acessorio-action" data-action="delete" data-id="{{ $acessorio->id_acessorio }}" data-base-url="{{ url('acessorios') }}">
                                                                <i class="ti ti-trash me-2"></i>Deletar
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ti ti-plug-off ti-lg mb-2"></i>
                                                        <p class="mb-0">Nenhum acessório encontrado</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($acessorios, 'links') && $acessorios->total() > 0)
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                        Mostrando {{ $acessorios->firstItem() }} até {{ $acessorios->lastItem() }} de {{ $acessorios->total() }} registros
                                    </div>
                                    {{ $acessorios->appends(request()->query())->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
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
    $('#checkAll').on('change', function() {
        $('.acessorio-checkbox').prop('checked', $(this).prop('checked'));
        atualizarBotaoExcluir();
    });

    $('.acessorio-checkbox').on('change', function() {
        atualizarBotaoExcluir();
    });

    function atualizarBotaoExcluir() {
        var selecionados = $('.acessorio-checkbox:checked').length;
        $('#countSelecionados').text(selecionados);
        if (selecionados > 0) {
            $('#btnExcluirSelecionados').show();
        } else {
            $('#btnExcluirSelecionados').hide();
        }
    }

    $('#btnExcluirSelecionados').on('click', function() {
        var ids = [];
        $('.acessorio-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;

        Swal.fire({
            title: 'Confirmar exclusão',
            text: `Deseja excluir ${ids.length} acessório(s)?`,
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
                    type: 'POST',
                    data: {
                        ids: ids,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Sucesso!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });

    $('.acessorio-action[data-action="delete"]').on('click', function() {
        var id = $(this).data('id');
        var baseUrl = $(this).data('base-url');

        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir este acessório?',
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
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Sucesso!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });
});
</script>
@endsection
