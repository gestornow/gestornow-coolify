@extends('layouts.layoutMaster')

@section('title', 'Gerenciamento de Clientes')

@php
    $podeCriarClientes = \Perm::pode(auth()->user(), 'clientes.criar');
    $podeEditarClientes = \Perm::pode(auth()->user(), 'clientes.editar');
    $podeExcluirClientes = \Perm::pode(auth()->user(), 'clientes.excluir');
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
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
                                            <span>Total de Clientes</span>
                                        </div>
                                        <span class="badge bg-label-primary rounded p-2">
                                            <i class="ti ti-users ti-sm"></i>
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
                                            <span>Clientes Ativos</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['ativos'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-success rounded p-2">
                                            <i class="ti ti-user-check ti-sm"></i>
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
                                            <span>Pessoa Física</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['pessoa_fisica'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-info rounded p-2">
                                            <i class="ti ti-user ti-sm"></i>
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
                                            <span>Pessoa Jurídica</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['pessoa_juridica'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-warning rounded p-2">
                                            <i class="ti ti-building ti-sm"></i>
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
                                @if($podeCriarClientes)
                                    <a href="{{ route('clientes.criar') }}" class="btn btn-primary">
                                        <i class="ti ti-plus me-1"></i>
                                        Novo Cliente
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label class="form-label small mb-1">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ativo" {{ (($filters['status'] ?? request('status')) == 'ativo') ? 'selected' : '' }}>Ativo</option>
                                        <option value="inativo" {{ (($filters['status'] ?? request('status')) == 'inativo') ? 'selected' : '' }}>Inativo</option>
                                        <option value="bloqueado" {{ (($filters['status'] ?? request('status')) == 'bloqueado') ? 'selected' : '' }}>Bloqueado</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small mb-1">Tipo de Pessoa</label>
                                    <select name="id_tipo_pessoa" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="1" {{ (($filters['id_tipo_pessoa'] ?? request('id_tipo_pessoa')) == '1') ? 'selected' : '' }}>Pessoa Física</option>
                                        <option value="2" {{ (($filters['id_tipo_pessoa'] ?? request('id_tipo_pessoa')) == '2') ? 'selected' : '' }}>Pessoa Jurídica</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small mb-1">Buscar</label>
                                    <input type="text" name="search" class="form-control" placeholder="Nome, CPF/CNPJ, Email..." value="{{ $filters['search'] ?? request('search') }}">
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

                <!-- Tabela de Clientes -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Clientes</h5>
                            @if($podeExcluirClientes)
                                <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSelecionados" data-url="{{ route('clientes.excluir-multiplos') }}" style="display: none;">
                                    <i class="ti ti-trash me-1"></i>
                                    Excluir Selecionados (<span id="countSelecionados">0</span>)
                                </button>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="overflow: visible;">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            @if($podeExcluirClientes)
                                                <th style="width: 40px;">
                                                    <input type="checkbox" class="form-check-input" id="checkAll">
                                                </th>
                                            @endif
                                            <th>Nome</th>
                                            <th>CPF/CNPJ</th>
                                            <th>Email</th>
                                            <th>Telefone</th>
                                            <th>Tipo</th>
                                            <th>Status</th>
                                            <th style="width: 120px;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($clientes as $cliente)
                                        <tr>
                                            @if($podeExcluirClientes)
                                                <td>
                                                    <input type="checkbox" class="form-check-input check-item" value="{{ $cliente->id_clientes }}">
                                                </td>
                                            @endif
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-4 flex-shrink-0" style="width: 38px; height: 38px;">
                                                        @if(!empty($cliente->foto_url))
                                                            <img 
                                                                src="{{ $cliente->foto_url }}" 
                                                                alt="{{ $cliente->nome }}" 
                                                                class="rounded-circle cliente-avatar-img" 
                                                                style="width: 38px; height: 38px; object-fit: cover;"
                                                                onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                            <span class="avatar-initial rounded-circle bg-label-primary" style="display: none; width: 38px; height: 38px; align-items: center; justify-content: center;">
                                                                {{ strtoupper(substr($cliente->nome, 0, 2)) }}
                                                            </span>
                                                        @else
                                                            <span class="avatar-initial rounded-circle bg-label-primary" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                                                {{ strtoupper(substr($cliente->nome, 0, 2)) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <strong>{{ $cliente->nome }}</strong>
                                                        @if($cliente->razao_social)
                                                            <small class="text-muted">{{ $cliente->razao_social }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $cliente->cpf_cnpj_formatado }}</td>
                                            <td>{{ $cliente->email ?? '-' }}</td>
                                            <td>{{ $cliente->telefone_formatado }}</td>
                                            <td>
                                                @if($cliente->id_tipo_pessoa == 1)
                                                    <span class="badge bg-label-info">Física</span>
                                                @else
                                                    <span class="badge bg-label-warning">Jurídica</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($cliente->status === 'ativo')
                                                    <span class="badge bg-label-success">Ativo</span>
                                                @elseif($cliente->status === 'inativo')
                                                    <span class="badge bg-label-warning">Inativo</span>
                                                @elseif($cliente->status === 'bloqueado')
                                                    <span class="badge bg-label-danger">Bloqueado</span>
                                                @else
                                                    <span class="badge bg-label-secondary">Indefinido</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                        <i class="ti ti-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('clientes.show', $cliente->id_clientes) }}">
                                                                <i class="ti ti-eye me-2"></i> Visualizar
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="verLogAtividadesCliente({{ $cliente->id_clientes }}, '{{ addslashes($cliente->nome) }}')">
                                                                <i class="ti ti-activity me-2"></i> Log de Atividades
                                                            </a>
                                                        </li>
                                                        <li>
                                                            @if($podeEditarClientes)
                                                                <a class="dropdown-item" href="{{ route('clientes.editar', $cliente->id_clientes) }}">
                                                                    <i class="ti ti-edit me-2"></i> Editar
                                                                </a>
                                                            @endif
                                                        </li>
                                                        @if($podeExcluirClientes)
                                                            <li>
                                                                <hr class="dropdown-divider">
                                                            </li>
                                                            <li>
                                                                <form action="{{ route('clientes.deletar', $cliente->id_clientes) }}" method="POST" class="form-delete-cliente">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="dropdown-item text-danger">
                                                                        <i class="ti ti-trash me-2"></i> Excluir
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="{{ $podeExcluirClientes ? 8 : 7 }}" class="text-center text-muted py-4">
                                                <i class="ti ti-users ti-lg mb-2"></i>
                                                <p>Nenhum cliente encontrado</p>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($clientes, 'links'))
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                        Mostrando {{ $clientes->firstItem() ?? 0 }} até {{ $clientes->lastItem() ?? 0 }} de {{ $clientes->total() }} registros
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination mb-0">
                                            {{-- Primeira página --}}
                                            @if ($clientes->onFirstPage())
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevrons-left ti-xs"></i></span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $clientes->appends(request()->query())->url(1) }}"><i class="ti ti-chevrons-left ti-xs"></i></a>
                                                </li>
                                            @endif

                                            {{-- Página anterior --}}
                                            @if ($clientes->onFirstPage())
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevron-left ti-xs"></i></span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $clientes->appends(request()->query())->previousPageUrl() }}"><i class="ti ti-chevron-left ti-xs"></i></a>
                                                </li>
                                            @endif

                                            {{-- Páginas numeradas --}}
                                            @php
                                                $start = max($clientes->currentPage() - 2, 1);
                                                $end = min($start + 4, $clientes->lastPage());
                                                $start = max($end - 4, 1);
                                            @endphp

                                            @for ($i = $start; $i <= $end; $i++)
                                                @if ($i == $clientes->currentPage())
                                                    <li class="page-item active">
                                                        <span class="page-link">{{ $i }}</span>
                                                    </li>
                                                @else
                                                    <li class="page-item">
                                                        <a class="page-link" href="{{ $clientes->appends(request()->query())->url($i) }}">{{ $i }}</a>
                                                    </li>
                                                @endif
                                            @endfor

                                            {{-- Próxima página --}}
                                            @if ($clientes->hasMorePages())
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $clientes->appends(request()->query())->nextPageUrl() }}"><i class="ti ti-chevron-right ti-xs"></i></a>
                                                </li>
                                            @else
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevron-right ti-xs"></i></span>
                                                </li>
                                            @endif

                                            {{-- Última página --}}
                                            @if ($clientes->hasMorePages())
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $clientes->appends(request()->query())->url($clientes->lastPage()) }}"><i class="ti ti-chevrons-right ti-xs"></i></a>
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
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/clientes-index.js') }}?v=20260305001"></script>

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
