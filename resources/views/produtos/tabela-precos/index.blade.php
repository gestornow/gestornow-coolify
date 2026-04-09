@extends('layouts.layoutMaster')

@section('title', 'Tabela de Preços')

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
                <!-- Filtros -->
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Tabelas de Preços</h5>
                            <a href="{{ route('tabela-precos.criar') }}" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>
                                Nova Tabela
                            </a>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-12 col-md-5">
                                    <label class="form-label small mb-1">Produto</label>
                                    <select name="id_produto" class="form-select">
                                        <option value="">Todos</option>
                                        @foreach($produtos as $produto)
                                            <option value="{{ $produto->id_produto }}" {{ (($filters['id_produto'] ?? '') == $produto->id_produto) ? 'selected' : '' }}>{{ $produto->nome }}</option>
                                        @endforeach
                                    </select>
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

                <!-- Tabelas de Preços -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Produto</th>
                                            <th>Preço Diário</th>
                                            <th>Preço Semanal</th>
                                            <th>Preço Mensal</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($tabelas as $tabela)
                                            <tr>
                                                <td>
                                                    <strong>{{ $tabela->nome }}</strong>
                                                    @if($tabela->descricao)
                                                        <br><small class="text-muted">{{ Str::limit($tabela->descricao, 30) }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ $tabela->produto->nome ?? 'N/A' }}</td>
                                                <td>R$ {{ number_format($tabela->d1 ?? 0, 2, ',', '.') }}</td>
                                                <td>R$ {{ number_format($tabela->preco_semanal ?? $tabela->d7 ?? 0, 2, ',', '.') }}</td>
                                                <td>R$ {{ number_format($tabela->preco_mensal ?? $tabela->d30 ?? 0, 2, ',', '.') }}</td>
                                                <td>
                                                    @if($tabela->status === 'ativo')
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
                                                            <a class="dropdown-item" href="{{ route('tabela-precos.edit', $tabela->id_tabela) }}">
                                                                <i class="ti ti-pencil me-2"></i>Editar
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a href="javascript:void(0)" class="dropdown-item text-danger btn-excluir-tabela" data-id="{{ $tabela->id_tabela }}">
                                                                <i class="ti ti-trash me-2"></i>Excluir
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ti ti-table-off ti-lg mb-2"></i>
                                                        <p class="mb-0">Nenhuma tabela de preços encontrada</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($tabelas, 'links') && $tabelas->total() > 0)
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                        Mostrando {{ $tabelas->firstItem() }} até {{ $tabelas->lastItem() }} de {{ $tabelas->total() }} registros
                                    </div>
                                    {{ $tabelas->appends(request()->query())->links() }}
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
    $('.btn-excluir-tabela').on('click', function() {
        var id = $(this).data('id');
        
        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir esta tabela de preços?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('tabela-precos') }}/${id}`,
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
