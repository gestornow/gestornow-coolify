@extends('layouts.layoutMaster')

@section('title', 'Gerenciamento de Manutenções')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
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
                                            <i class="ti ti-tool ti-sm"></i>
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
                                                <h4 class="mb-0 me-2">{{ $stats['em_andamento'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-info rounded p-2">
                                            <i class="ti ti-tool ti-sm"></i>
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
                                            <span>Concluídas</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['concluidas'] ?? 0 }}</h4>
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
                                            <span>Total</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['total'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-primary rounded p-2">
                                            <i class="ti ti-list ti-sm"></i>
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
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaManutencao">
                                <i class="ti ti-plus me-1"></i>
                                Nova Manutenção
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
                                <div class="col-12 col-md-2">
                                    <label class="form-label small mb-1">Tipo</label>
                                    <select name="tipo" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="preventiva" {{ (($filters['tipo'] ?? '') == 'preventiva') ? 'selected' : '' }}>Preventiva</option>
                                        <option value="corretiva" {{ (($filters['tipo'] ?? '') == 'corretiva') ? 'selected' : '' }}>Corretiva</option>
                                        <option value="preditiva" {{ (($filters['tipo'] ?? '') == 'preditiva') ? 'selected' : '' }}>Preditiva</option>
                                        <option value="emergencial" {{ (($filters['tipo'] ?? '') == 'emergencial') ? 'selected' : '' }}>Emergencial</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label small mb-1">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="em_andamento" {{ (($filters['status'] ?? '') == 'em_andamento') ? 'selected' : '' }}>Em Manutenção</option>
                                        <option value="concluida" {{ (($filters['status'] ?? '') == 'concluida') ? 'selected' : '' }}>Concluída</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label small mb-1">Buscar</label>
                                    <input type="text" name="busca" class="form-control" placeholder="Descrição ou responsável" value="{{ $filters['busca'] ?? '' }}">
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

                <!-- Tabela de Manutenções -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Manutenções</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Tipo</th>
                                            <th>Descrição</th>
                                            <th>Data Entrada</th>
                                            <th>Previsão</th>
                                            <th>Custo</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($manutencoes as $manutencao)
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <strong>{{ $manutencao->produto->nome ?? 'N/A' }}</strong>
                                                        @if($manutencao->patrimonio)
                                                            <small class="text-muted">Patrimônio: {{ $manutencao->patrimonio->numero_serie ?? ('PAT-' . $manutencao->id_patrimonio) }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    @php
                                                        $tipoColors = [
                                                            'preventiva' => 'info',
                                                            'corretiva' => 'warning',
                                                            'preditiva' => 'primary',
                                                            'emergencial' => 'danger'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $tipoColors[$manutencao->tipo] ?? 'secondary' }}">
                                                        {{ ucfirst($manutencao->tipo) }}
                                                    </span>
                                                </td>
                                                <td>{{ Str::limit($manutencao->descricao, 40) }}</td>
                                                <td>{{ optional($manutencao->data_entrada)->format('d/m/Y') ?? '-' }}</td>
                                                <td>{{ optional($manutencao->data_previsao)->format('d/m/Y') ?? '-' }}</td>
                                                <td>{{ $manutencao->custo_formatado }}</td>
                                                <td>
                                                    @php
                                                        $statusColors = [
                                                            'pendente' => 'info',
                                                            'em_andamento' => 'info',
                                                            'concluida' => 'success',
                                                            'cancelada' => 'danger'
                                                        ];
                                                        $statusLabels = [
                                                            'pendente' => 'Em Manutenção',
                                                            'em_andamento' => 'Em Manutenção',
                                                            'concluida' => 'Concluída',
                                                            'cancelada' => 'Cancelada'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $statusColors[$manutencao->status] ?? 'secondary' }}">
                                                        {{ $statusLabels[$manutencao->status] ?? $manutencao->status }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                            <i class="ti ti-dots-vertical"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <a href="javascript:void(0)" class="dropdown-item btn-editar-manutencao" data-id="{{ $manutencao->id_manutencao }}" data-manutencao="{{ json_encode($manutencao) }}">
                                                                <i class="ti ti-pencil me-2"></i>Editar
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a href="javascript:void(0)" class="dropdown-item text-danger btn-excluir-manutencao" data-id="{{ $manutencao->id_manutencao }}">
                                                                <i class="ti ti-trash me-2"></i>Excluir
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ti ti-tool-off ti-lg mb-2"></i>
                                                        <p class="mb-0">Nenhuma manutenção encontrada</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($manutencoes, 'links') && $manutencoes->total() > 0)
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                        Mostrando {{ $manutencoes->firstItem() }} até {{ $manutencoes->lastItem() }} de {{ $manutencoes->total() }} registros
                                    </div>
                                    {{ $manutencoes->appends(request()->query())->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova/Editar Manutenção -->
<div class="modal fade" id="modalNovaManutencao" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalManutencaoTitle">Nova Manutenção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formManutencao" method="POST" action="{{ route('manutencoes.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Produto <span class="text-danger">*</span></label>
                            <select name="id_produto" id="manutencao_id_produto" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach($produtos as $produto)
                                    <option value="{{ $produto->id_produto }}">{{ $produto->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="grupo_patrimonio_manutencao">
                            <label class="form-label">Patrimônio</label>
                            <select name="id_patrimonio" id="manutencao_id_patrimonio" class="form-select">
                                <option value="">Selecione primeiro o produto...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3 d-none" id="grupo_quantidade_manutencao">
                            <label class="form-label">Quantidade em Manutenção <span class="text-danger">*</span></label>
                            <input type="number" name="quantidade" id="manutencao_quantidade" class="form-control" min="1" value="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" id="manutencao_tipo" class="form-select" required>
                                <option value="preventiva">Preventiva</option>
                                <option value="corretiva">Corretiva</option>
                                <option value="preditiva">Preditiva</option>
                                <option value="emergencial">Emergencial</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="manutencao_status" class="form-select">
                                <option value="em_andamento">Em Manutenção</option>
                                <option value="concluida">Concluída</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Descrição <span class="text-danger">*</span></label>
                            <textarea name="descricao" id="manutencao_descricao" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data de Manutenção <span class="text-danger">*</span></label>
                            <input type="date" name="data_manutencao" id="manutencao_data_entrada" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Hora de Manutenção</label>
                            <input type="time" name="hora_manutencao" id="manutencao_hora_manutencao" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Previsão de Conclusão</label>
                            <input type="date" name="data_previsao" id="manutencao_data_previsao" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Hora da Previsão</label>
                            <input type="time" name="hora_previsao" id="manutencao_hora_previsao" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Custo</label>
                            <input type="text" name="valor" id="manutencao_custo" class="form-control money">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Responsável</label>
                            <input type="text" name="responsavel" id="manutencao_responsavel" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fornecedor</label>
                            <input type="text" name="fornecedor" id="manutencao_fornecedor" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" id="manutencao_observacoes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i>
                        <span id="btnManutencaoText">Salvar</span>
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

    let patrimonioSelecionadoEdicao = null;

    function normalizarStatusLocacao(status) {
        return String(status || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase();
    }

    function alternarModoManutencaoPorProduto(temPatrimonios) {
        if (temPatrimonios) {
            $('#grupo_patrimonio_manutencao').removeClass('d-none');
            $('#grupo_quantidade_manutencao').addClass('d-none');
            $('#manutencao_quantidade').prop('required', false).val('');
            $('#manutencao_id_patrimonio').prop('required', true);
        } else {
            $('#grupo_patrimonio_manutencao').addClass('d-none');
            $('#grupo_quantidade_manutencao').removeClass('d-none');
            $('#manutencao_quantidade').prop('required', true).val($('#manutencao_quantidade').val() || 1);
            $('#manutencao_id_patrimonio').prop('required', false).val('');
        }
    }

    // Carregar patrimônios ao selecionar produto
    $('#manutencao_id_produto').on('change', function() {
        var idProduto = $(this).val();
        var selectPatrimonio = $('#manutencao_id_patrimonio');
        
        selectPatrimonio.html('<option value="">Carregando...</option>');
        
        if (idProduto) {
            $.get(`{{ url('patrimonios/produto') }}/${idProduto}`, function(data) {
                const temPatrimonios = Array.isArray(data) && data.length > 0;
                var options = '<option value="">Nenhum (geral)</option>';
                data.forEach(function(p) {
                    const numeroSerie = p.numero_serie || p.codigo_patrimonio || `PAT-${p.id_patrimonio}`;
                    const statusLocacao = p.status_locacao || 'Disponivel';
                    const statusNormalizado = normalizarStatusLocacao(statusLocacao);
                    const bloqueadoPorStatus = statusNormalizado === 'locado' || statusNormalizado === 'em manutencao';
                    const isSelecionadoNaEdicao = parseInt(patrimonioSelecionadoEdicao || 0, 10) === parseInt(p.id_patrimonio, 10);
                    const disabled = bloqueadoPorStatus && !isSelecionadoNaEdicao ? 'disabled' : '';
                    const labelStatus = bloqueadoPorStatus ? ` (status: ${statusLocacao})` : '';
                    options += `<option value="${p.id_patrimonio}" ${disabled}>${numeroSerie}${labelStatus}</option>`;
                });
                selectPatrimonio.html(options);
                if (patrimonioSelecionadoEdicao) {
                    selectPatrimonio.val(String(patrimonioSelecionadoEdicao));
                }
                alternarModoManutencaoPorProduto(temPatrimonios);
            });
        } else {
            selectPatrimonio.html('<option value="">Selecione primeiro o produto...</option>');
            patrimonioSelecionadoEdicao = null;
            alternarModoManutencaoPorProduto(true);
        }
    });

    // Editar manutenção
    $('.btn-editar-manutencao').on('click', function() {
        var manutencao = $(this).data('manutencao');
        var id = $(this).data('id');
        
        $('#modalManutencaoTitle').text('Editar Manutenção');
        $('#btnManutencaoText').text('Atualizar');
        $('#formManutencao').attr('action', `{{ url('manutencoes') }}/${id}`);
        $('#formMethod').val('PUT');
        
        $('#manutencao_id_produto').val(manutencao.id_produto);
        $('#manutencao_tipo').val(manutencao.tipo);
        $('#manutencao_status').val(manutencao.status === 'concluida' ? 'concluida' : 'em_andamento');
        $('#manutencao_descricao').val(manutencao.descricao);
        $('#manutencao_data_entrada').val((manutencao.data_manutencao || manutencao.data_entrada) ? (manutencao.data_manutencao || manutencao.data_entrada).split('T')[0] : '');
        $('#manutencao_data_previsao').val(manutencao.data_previsao ? manutencao.data_previsao.split('T')[0] : '');
        $('#manutencao_hora_manutencao').val(manutencao.hora_manutencao ? manutencao.hora_manutencao.substring(0, 5) : '');
        $('#manutencao_hora_previsao').val(manutencao.hora_previsao ? manutencao.hora_previsao.substring(0, 5) : '');
        $('#manutencao_quantidade').val(manutencao.quantidade || 1);
        $('#manutencao_custo').val(manutencao.custo ? parseFloat(manutencao.custo).toFixed(2).replace('.', ',') : '');
        $('#manutencao_responsavel').val(manutencao.responsavel);
        $('#manutencao_fornecedor').val(manutencao.fornecedor);
        $('#manutencao_observacoes').val(manutencao.observacoes);

        patrimonioSelecionadoEdicao = manutencao.id_patrimonio || null;
        $('#manutencao_id_produto').trigger('change');

        alternarModoManutencaoPorProduto(!!manutencao.id_patrimonio);
        
        $('#modalNovaManutencao').modal('show');
    });

    // Reset modal ao abrir para nova manutenção
    $('#modalNovaManutencao').on('show.bs.modal', function(e) {
        if (!$(e.relatedTarget).hasClass('btn-editar-manutencao')) {
            $('#modalManutencaoTitle').text('Nova Manutenção');
            $('#btnManutencaoText').text('Salvar');
            $('#formManutencao').attr('action', '{{ route("manutencoes.store") }}');
            $('#formMethod').val('POST');
            $('#formManutencao')[0].reset();
            $('#manutencao_data_entrada').val(new Date().toISOString().split('T')[0]);
            $('#manutencao_hora_manutencao').val('');
            $('#manutencao_hora_previsao').val('');
            $('#manutencao_quantidade').val(1);
            patrimonioSelecionadoEdicao = null;
            alternarModoManutencaoPorProduto(true);
        }
    });

    // Excluir manutenção
    $('.btn-excluir-manutencao').on('click', function() {
        var id = $(this).data('id');
        
        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir esta manutenção?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('manutencoes') }}/${id}`,
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
