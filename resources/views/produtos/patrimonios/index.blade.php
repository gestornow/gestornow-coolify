@extends('layouts.layoutMaster')

@section('title', 'Gerenciamento de Patrimônios')

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
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['total'] ?? 0 }}</h4>
                                            </div>
                                            <span>Total</span>
                                        </div>
                                        <span class="badge bg-label-primary rounded p-2">
                                            <i class="ti ti-building ti-sm"></i>
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
                                            <span>Disponíveis</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['disponiveis'] ?? 0 }}</h4>
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
                                            <span>Locados</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['locados'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-info rounded p-2">
                                            <i class="ti ti-truck ti-sm"></i>
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
                                            <span>Em Manutenção</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['manutencao'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-warning rounded p-2">
                                            <i class="ti ti-tool ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Filtros de Busca</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoPatrimonio">
                                <i class="ti ti-plus me-1"></i>
                                Novo Patrimônio
                            </button>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label small mb-1">Produto</label>
                                    <select name="id_produto" class="form-select">
                                        <option value="">Todos</option>
                                        @foreach($produtos as $produto)
                                            <option value="{{ $produto->id_produto }}" {{ (($filters['id_produto'] ?? '') == $produto->id_produto) ? 'selected' : '' }}>{{ $produto->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label small mb-1">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="disponivel" {{ (($filters['status'] ?? '') == 'disponivel') ? 'selected' : '' }}>Disponível</option>
                                        <option value="em_uso" {{ (($filters['status'] ?? '') == 'em_uso') ? 'selected' : '' }}>Em Uso</option>
                                        <option value="locado" {{ (($filters['status'] ?? '') == 'locado') ? 'selected' : '' }}>Locado</option>
                                        <option value="manutencao" {{ (($filters['status'] ?? '') == 'manutencao') ? 'selected' : '' }}>Em Manutenção</option>
                                        <option value="baixado" {{ (($filters['status'] ?? '') == 'baixado') ? 'selected' : '' }}>Baixado</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small mb-1">Buscar</label>
                                    <input type="text" name="busca" class="form-control" placeholder="Código, série ou descrição" value="{{ $filters['busca'] ?? '' }}">
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

                <!-- Tabela de Patrimônios -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Patrimônios</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Produto</th>
                                            <th>Nº Série</th>
                                            <th>Localização</th>
                                            <th>Valor Atual</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($patrimonios as $patrimonio)
                                            <tr>
                                                <td>
                                                    <strong>{{ $patrimonio->codigo_patrimonio }}</strong>
                                                </td>
                                                <td>{{ $patrimonio->produto->nome ?? 'N/A' }}</td>
                                                <td>{{ $patrimonio->numero_serie ?? '-' }}</td>
                                                <td>{{ $patrimonio->localizacao ?? '-' }}</td>
                                                <td>{{ $patrimonio->valor_atual_formatado }}</td>
                                                <td>
                                                    @php
                                                        $statusColors = [
                                                            'disponivel' => 'success',
                                                            'em_uso' => 'primary',
                                                            'locado' => 'info',
                                                            'manutencao' => 'warning',
                                                            'baixado' => 'secondary',
                                                            'perdido' => 'danger'
                                                        ];
                                                        $statusLabels = [
                                                            'disponivel' => 'Disponível',
                                                            'em_uso' => 'Em Uso',
                                                            'locado' => 'Locado',
                                                            'manutencao' => 'Manutenção',
                                                            'baixado' => 'Baixado',
                                                            'perdido' => 'Perdido'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $statusColors[$patrimonio->status] ?? 'secondary' }}">
                                                        {{ $statusLabels[$patrimonio->status] ?? $patrimonio->status }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                            <i class="ti ti-dots-vertical"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <a class="dropdown-item" href="{{ route('patrimonios.show', $patrimonio->id_patrimonio) }}">
                                                                <i class="ti ti-eye me-2"></i>Visualizar
                                                            </a>
                                                            <a href="javascript:void(0)" class="dropdown-item btn-editar-patrimonio" data-id="{{ $patrimonio->id_patrimonio }}" data-patrimonio="{{ json_encode($patrimonio) }}">
                                                                <i class="ti ti-pencil me-2"></i>Editar
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a href="javascript:void(0)" class="dropdown-item text-danger btn-excluir-patrimonio" data-id="{{ $patrimonio->id_patrimonio }}">
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
                                                        <i class="ti ti-building-off ti-lg mb-2"></i>
                                                        <p class="mb-0">Nenhum patrimônio encontrado</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($patrimonios, 'links') && $patrimonios->total() > 0)
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                        Mostrando {{ $patrimonios->firstItem() }} até {{ $patrimonios->lastItem() }} de {{ $patrimonios->total() }} registros
                                    </div>
                                    {{ $patrimonios->appends(request()->query())->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo/Editar Patrimônio -->
<div class="modal fade" id="modalNovoPatrimonio" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPatrimonioTitle">Novo Patrimônio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPatrimonio" method="POST" action="{{ route('patrimonios.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formPatrimonioMethod" value="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Produto <span class="text-danger">*</span></label>
                            <select name="id_produto" id="patrimonio_id_produto" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach($produtos as $produto)
                                    <option value="{{ $produto->id_produto }}">{{ $produto->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código do Patrimônio <span class="text-danger">*</span></label>
                            <input type="text" name="codigo_patrimonio" id="patrimonio_codigo" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Número de Série</label>
                            <input type="text" name="numero_serie" id="patrimonio_serie" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="patrimonio_status" class="form-select">
                                <option value="disponivel">Disponível</option>
                                <option value="em_uso">Em Uso</option>
                                <option value="locado">Locado</option>
                                <option value="manutencao">Em Manutenção</option>
                                <option value="baixado">Baixado</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" id="patrimonio_descricao" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data de Aquisição</label>
                            <input type="date" name="data_aquisicao" id="patrimonio_data_aquisicao" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Valor de Aquisição</label>
                            <input type="text" name="valor_aquisicao" id="patrimonio_valor_aquisicao" class="form-control money">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Valor Atual</label>
                            <input type="text" name="valor_atual" id="patrimonio_valor_atual" class="form-control money">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Localização</label>
                            <input type="text" name="localizacao" id="patrimonio_localizacao" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Responsável</label>
                            <input type="text" name="responsavel" id="patrimonio_responsavel" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" id="patrimonio_observacoes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i>
                        <span id="btnPatrimonioText">Salvar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    $('.money').mask('#.##0,00', {reverse: true});

    // Editar patrimônio
    $('.btn-editar-patrimonio').on('click', function() {
        var patrimonio = $(this).data('patrimonio');
        var id = $(this).data('id');
        
        $('#modalPatrimonioTitle').text('Editar Patrimônio');
        $('#btnPatrimonioText').text('Atualizar');
        $('#formPatrimonio').attr('action', `{{ url('patrimonios') }}/${id}`);
        $('#formPatrimonioMethod').val('PUT');
        
        $('#patrimonio_id_produto').val(patrimonio.id_produto);
        $('#patrimonio_codigo').val(patrimonio.codigo_patrimonio);
        $('#patrimonio_serie').val(patrimonio.numero_serie);
        $('#patrimonio_status').val(patrimonio.status);
        $('#patrimonio_descricao').val(patrimonio.descricao);
        $('#patrimonio_data_aquisicao').val(patrimonio.data_aquisicao ? patrimonio.data_aquisicao.split('T')[0] : '');
        $('#patrimonio_valor_aquisicao').val(patrimonio.valor_aquisicao ? parseFloat(patrimonio.valor_aquisicao).toFixed(2).replace('.', ',') : '');
        $('#patrimonio_valor_atual').val(patrimonio.valor_atual ? parseFloat(patrimonio.valor_atual).toFixed(2).replace('.', ',') : '');
        $('#patrimonio_localizacao').val(patrimonio.localizacao);
        $('#patrimonio_responsavel').val(patrimonio.responsavel);
        $('#patrimonio_observacoes').val(patrimonio.observacoes);
        
        $('#modalNovoPatrimonio').modal('show');
    });

    // Reset modal
    $('#modalNovoPatrimonio').on('show.bs.modal', function(e) {
        if (!$(e.relatedTarget).hasClass('btn-editar-patrimonio')) {
            $('#modalPatrimonioTitle').text('Novo Patrimônio');
            $('#btnPatrimonioText').text('Salvar');
            $('#formPatrimonio').attr('action', '{{ route("patrimonios.store") }}');
            $('#formPatrimonioMethod').val('POST');
            $('#formPatrimonio')[0].reset();
        }
    });

    // Excluir patrimônio
    $('.btn-excluir-patrimonio').on('click', function() {
        var id = $(this).data('id');
        
        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir este patrimônio?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('patrimonios') }}/${id}`,
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
