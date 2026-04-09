@extends('layouts.layoutMaster')

@section('title', 'Gerenciamento de Fornecedores')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@php
    $formatarDocumento = function ($valor) {
        $digits = preg_replace('/\D/', '', (string) ($valor ?? ''));
        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }
        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }
        return $valor ?: '-';
    };

    $formatarTelefone = function ($valor) {
        $digits = preg_replace('/\D/', '', (string) ($valor ?? ''));
        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
        }
        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
        }
        return $valor ?: '-';
    };
@endphp

@section('content')
<div class="container-xxl flex-grow-1">
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('fornecedores.index') }}">
                <i class="ti ti-list me-1"></i> Listar Fornecedores
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('fornecedores.criar') }}">
                <i class="ti ti-plus me-1"></i> Cadastrar Fornecedor
            </a>
        </li>
    </ul>

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2">{{ $stats['total'] ?? 0 }}</h4>
                            </div>
                            <span>Total de Fornecedores</span>
                        </div>
                        <span class="badge bg-label-primary rounded p-2">
                            <i class="ti ti-truck-loading ti-sm"></i>
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
                            <span>Fornecedores Ativos</span>
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
                            <span>Pessoa Fisica</span>
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
                            <span>Pessoa Juridica</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2">{{ $stats['pessoa_juridica'] ?? 0 }}</h4>
                            </div>
                        </div>
                        <span class="badge bg-label-warning rounded p-2">
                            <i class="ti ti-building-store ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Filtros de Busca</h5>
            <a href="{{ route('fornecedores.criar') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>
                Novo Fornecedor
            </a>
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
                        <option value="1" {{ (($filters['id_tipo_pessoa'] ?? request('id_tipo_pessoa')) == '1') ? 'selected' : '' }}>Pessoa Fisica</option>
                        <option value="2" {{ (($filters['id_tipo_pessoa'] ?? request('id_tipo_pessoa')) == '2') ? 'selected' : '' }}>Pessoa Juridica</option>
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

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Fornecedores</h5>
            <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSelecionados" data-url="{{ route('fornecedores.excluir-multiplos') }}" style="display: none;">
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
                            <th>Nome</th>
                            <th>CPF/CNPJ</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th style="width: 120px;">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fornecedores as $fornecedor)
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input check-item" value="{{ $fornecedor->id_fornecedores }}">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-3 flex-shrink-0" style="width: 38px; height: 38px;">
                                        <span class="avatar-initial rounded-circle bg-label-primary" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                            {{ strtoupper(substr($fornecedor->nome ?? 'F', 0, 2)) }}
                                        </span>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <strong>{{ $fornecedor->nome }}</strong>
                                        @if($fornecedor->razao_social)
                                            <small class="text-muted">{{ $fornecedor->razao_social }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $formatarDocumento($fornecedor->cpf_cnpj) }}</td>
                            <td>{{ $fornecedor->email ?? '-' }}</td>
                            <td>{{ $formatarTelefone($fornecedor->telefone) }}</td>
                            <td>
                                @if((int) $fornecedor->id_tipo_pessoa === 1)
                                    <span class="badge bg-label-info">Fisica</span>
                                @else
                                    <span class="badge bg-label-warning">Juridica</span>
                                @endif
                            </td>
                            <td>
                                @if($fornecedor->status === 'ativo')
                                    <span class="badge bg-label-success">Ativo</span>
                                @elseif($fornecedor->status === 'inativo')
                                    <span class="badge bg-label-warning">Inativo</span>
                                @elseif($fornecedor->status === 'bloqueado')
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
                                            <a class="dropdown-item" href="{{ route('fornecedores.editar', $fornecedor->id_fornecedores) }}">
                                                <i class="ti ti-edit me-2"></i> Editar
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('fornecedores.deletar', $fornecedor->id_fornecedores) }}" method="POST" class="form-delete-fornecedor">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="ti ti-trash me-2"></i> Excluir
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="ti ti-truck-loading ti-lg mb-2"></i>
                                <p>Nenhum fornecedor encontrado</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($fornecedores, 'links'))
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Mostrando {{ $fornecedores->firstItem() ?? 0 }} ate {{ $fornecedores->lastItem() ?? 0 }} de {{ $fornecedores->total() }} registros
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            @if ($fornecedores->onFirstPage())
                                <li class="page-item disabled">
                                    <span class="page-link"><i class="ti ti-chevrons-left ti-xs"></i></span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a class="page-link" href="{{ $fornecedores->appends(request()->query())->url(1) }}"><i class="ti ti-chevrons-left ti-xs"></i></a>
                                </li>
                            @endif

                            @if ($fornecedores->onFirstPage())
                                <li class="page-item disabled">
                                    <span class="page-link"><i class="ti ti-chevron-left ti-xs"></i></span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a class="page-link" href="{{ $fornecedores->appends(request()->query())->previousPageUrl() }}"><i class="ti ti-chevron-left ti-xs"></i></a>
                                </li>
                            @endif

                            @php
                                $start = max($fornecedores->currentPage() - 2, 1);
                                $end = min($start + 4, $fornecedores->lastPage());
                                $start = max($end - 4, 1);
                            @endphp

                            @for ($i = $start; $i <= $end; $i++)
                                @if ($i == $fornecedores->currentPage())
                                    <li class="page-item active">
                                        <span class="page-link">{{ $i }}</span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $fornecedores->appends(request()->query())->url($i) }}">{{ $i }}</a>
                                    </li>
                                @endif
                            @endfor

                            @if ($fornecedores->hasMorePages())
                                <li class="page-item">
                                    <a class="page-link" href="{{ $fornecedores->appends(request()->query())->nextPageUrl() }}"><i class="ti ti-chevron-right ti-xs"></i></a>
                                </li>
                            @else
                                <li class="page-item disabled">
                                    <span class="page-link"><i class="ti ti-chevron-right ti-xs"></i></span>
                                </li>
                            @endif

                            @if ($fornecedores->hasMorePages())
                                <li class="page-item">
                                    <a class="page-link" href="{{ $fornecedores->appends(request()->query())->url($fornecedores->lastPage()) }}"><i class="ti ti-chevrons-right ti-xs"></i></a>
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
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/fornecedores-index.js') }}?v=20260318001"></script>

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
