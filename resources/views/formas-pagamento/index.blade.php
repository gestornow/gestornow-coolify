@extends('layouts.layoutMaster')

@section('title', 'Formas de Pagamento')

@section('page-style')
<style>
    @media (max-width: 767.98px) {
        .filtros-header {
            flex-direction: column;
            gap: .6rem;
        }

        .filtros-header > div {
            width: 100%;
        }

        .filtros-header .btn {
            width: 100%;
        }
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <!-- Filtros e Ações -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center filtros-header flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="ti ti-credit-card me-2"></i>
                        Formas de Pagamento
                    </h5>
                    <a href="{{ route('formas-pagamento.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>
                        Nova Forma de Pagamento
                    </a>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-12 col-md-10">
                            <label class="form-label small mb-1">Buscar</label>
                            <input type="text" name="busca" class="form-control" placeholder="Nome da forma de pagamento" value="{{ request('busca') }}">
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

            <!-- Tabela -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Descrição</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($formasPagamento as $forma)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm me-3">
                                                    <span class="avatar-initial rounded-circle bg-label-primary">
                                                        <i class="ti ti-credit-card"></i>
                                                    </span>
                                                </div>
                                                <strong>{{ $forma->nome }}</strong>
                                            </div>
                                        </td>
                                        <td>{{ $forma->descricao ?? '-' }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                    <i class="ti ti-dots-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="{{ route('formas-pagamento.edit', $forma->id_forma_pagamento) }}">
                                                        <i class="ti ti-pencil me-2"></i>Editar
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a href="javascript:void(0)" class="dropdown-item text-danger btn-excluir" 
                                                       data-id="{{ $forma->id_forma_pagamento }}" 
                                                       data-nome="{{ $forma->nome }}">
                                                        <i class="ti ti-trash me-2"></i>Excluir
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="ti ti-credit-card-off ti-lg mb-2"></i>
                                                <p class="mb-0">Nenhuma forma de pagamento encontrada</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(method_exists($formasPagamento, 'links') && $formasPagamento->total() > 0)
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted">
                                Mostrando {{ $formasPagamento->firstItem() }} até {{ $formasPagamento->lastItem() }} de {{ $formasPagamento->total() }} registros
                            </div>
                            <nav aria-label="Page navigation">
                                {{ $formasPagamento->appends(request()->query())->links() }}
                            </nav>
                        </div>
                    @endif
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
    // SweetAlert de sucesso
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

    // Excluir
    $('.btn-excluir').on('click', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');

        Swal.fire({
            title: 'Confirmar exclusão?',
            text: `Deseja excluir a forma de pagamento "${nome}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("formas-pagamento") }}/' + id,
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
});
</script>
@endsection
