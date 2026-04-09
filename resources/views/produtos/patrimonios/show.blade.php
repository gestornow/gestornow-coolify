@extends('layouts.layoutMaster')

@section('title', 'Detalhes do Patrimônio')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
        </div>
    </div>

    <!-- Cabeçalho -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="{{ route('patrimonios.index') }}">Patrimônios</a></li>
                            <li class="breadcrumb-item active">{{ $patrimonio->numero_serie ?? 'Detalhes' }}</li>
                        </ol>
                    </nav>
                    <h4 class="mb-0">
                        <i class="ti ti-barcode me-2"></i>
                        {{ $patrimonio->numero_serie ?? 'Sem Nº Série' }}
                    </h4>
                    <small class="text-muted">{{ $patrimonio->produto->nome ?? 'Produto não encontrado' }}</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('patrimonios.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Informações -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Status</span>
                            <div class="d-flex align-items-center my-1">
                                @php
                                    $statusColors = [
                                        'Ativo' => 'success',
                                        'Inativo' => 'secondary',
                                        'Descarte' => 'danger',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$patrimonio->status] ?? 'secondary' }} fs-6">
                                    {{ $patrimonio->status ?? 'N/A' }}
                                </span>
                            </div>
                        </div>
                        <span class="badge bg-label-primary rounded p-2">
                            <i class="ti ti-info-circle ti-sm"></i>
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
                            <span>Status Locação</span>
                            <div class="d-flex align-items-center my-1">
                                @php
                                    $statusLocacaoColors = [
                                        'Disponivel' => 'success',
                                        'Locado' => 'info',
                                        'Em Manutencao' => 'warning',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusLocacaoColors[$patrimonio->status_locacao] ?? 'secondary' }} fs-6">
                                    {{ $patrimonio->status_locacao ?? 'Disponível' }}
                                </span>
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
                            <span>Valor Aquisição</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2">{{ $patrimonio->valor_aquisicao_formatado }}</h4>
                            </div>
                        </div>
                        <span class="badge bg-label-success rounded p-2">
                            <i class="ti ti-currency-real ti-sm"></i>
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
                            <span>Manutenções</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2">{{ $patrimonio->manutencoes->count() }}</h4>
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

    <!-- Abas de Conteúdo -->
    <div class="nav-align-top mb-4">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-detalhes">
                    <i class="ti ti-info-square me-1"></i> Detalhes
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-historico" id="btn-tab-historico">
                    <i class="ti ti-history me-1"></i> Histórico de Locações
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-manutencoes" id="btn-tab-manutencoes">
                    <i class="ti ti-tool me-1"></i> Manutenções
                </button>
            </li>
        </ul>
        <div class="tab-content">
            <!-- Tab Detalhes -->
            <div class="tab-pane fade show active" id="tab-detalhes" role="tabpanel">
                <div class="card mb-0">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Produto</label>
                                <p class="mb-0">
                                    <a href="{{ route('produtos.show', $patrimonio->id_produto) }}">
                                        {{ $patrimonio->produto->nome ?? 'N/A' }}
                                    </a>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Número de Série</label>
                                <p class="mb-0">{{ $patrimonio->numero_serie ?? '-' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Data de Aquisição</label>
                                <p class="mb-0">{{ $patrimonio->data_aquisicao ? $patrimonio->data_aquisicao->format('d/m/Y') : '-' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Valor de Aquisição</label>
                                <p class="mb-0">{{ $patrimonio->valor_aquisicao_formatado }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Última Manutenção</label>
                                <p class="mb-0">{{ $patrimonio->ultima_manutencao ? \Carbon\Carbon::parse($patrimonio->ultima_manutencao)->format('d/m/Y') : '-' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Próxima Manutenção</label>
                                <p class="mb-0">{{ $patrimonio->proxima_manutencao ? \Carbon\Carbon::parse($patrimonio->proxima_manutencao)->format('d/m/Y') : '-' }}</p>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Observações</label>
                                <p class="mb-0">{{ $patrimonio->observacoes ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Histórico de Locações -->
            <div class="tab-pane fade" id="tab-historico" role="tabpanel">
                <div class="card mb-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Histórico de Locações</h5>
                        <span class="badge bg-primary" id="total-locacoes">0 registros</span>
                    </div>
                    <div class="card-body">
                        <!-- Cards Resumo -->
                        <div class="row g-3 mb-4" id="cards-resumo-historico">
                            <div class="col-6 col-md-3">
                                <div class="card bg-label-primary">
                                    <div class="card-body text-center py-3">
                                        <h4 class="mb-0" id="stat-total">0</h4>
                                        <small>Total Locações</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card bg-label-success">
                                    <div class="card-body text-center py-3">
                                        <h4 class="mb-0" id="stat-finalizadas">0</h4>
                                        <small>Finalizadas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card bg-label-info">
                                    <div class="card-body text-center py-3">
                                        <h4 class="mb-0" id="stat-andamento">0</h4>
                                        <small>Em Andamento</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card bg-label-warning">
                                    <div class="card-body text-center py-3">
                                        <h4 class="mb-0" id="stat-reservas">0</h4>
                                        <small>Reservas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tabela de Histórico -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="tabela-historico">
                                <thead>
                                    <tr>
                                        <th>Contrato</th>
                                        <th>Cliente</th>
                                        <th>Período</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-historico">
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                            Carregando histórico...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Manutenções -->
            <div class="tab-pane fade" id="tab-manutencoes" role="tabpanel">
                <div class="card mb-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Histórico de Manutenções</h5>
                        <span class="badge bg-warning">{{ $patrimonio->manutencoes->count() }} registros</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data Entrada</th>
                                        <th>Data Saída</th>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th>Custo</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($patrimonio->manutencoes as $manutencao)
                                        <tr>
                                            <td>{{ $manutencao->data_entrada ? \Carbon\Carbon::parse($manutencao->data_entrada)->format('d/m/Y') : '-' }}</td>
                                            <td>{{ $manutencao->data_saida ? \Carbon\Carbon::parse($manutencao->data_saida)->format('d/m/Y') : '-' }}</td>
                                            <td>
                                                @php
                                                    $tipoColors = [
                                                        'preventiva' => 'info',
                                                        'corretiva' => 'warning',
                                                        'emergencial' => 'danger',
                                                    ];
                                                @endphp
                                                <span class="badge bg-{{ $tipoColors[$manutencao->tipo] ?? 'secondary' }}">
                                                    {{ ucfirst($manutencao->tipo ?? '-') }}
                                                </span>
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::limit($manutencao->descricao ?? '-', 50) }}</td>
                                            <td>R$ {{ number_format($manutencao->custo ?? 0, 2, ',', '.') }}</td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'pendente' => 'warning',
                                                        'em_andamento' => 'info',
                                                        'concluida' => 'success',
                                                        'cancelada' => 'secondary',
                                                    ];
                                                @endphp
                                                <span class="badge bg-{{ $statusColors[$manutencao->status] ?? 'secondary' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $manutencao->status ?? '-')) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="ti ti-tool ti-lg mb-2"></i>
                                                    <p class="mb-0">Nenhuma manutenção registrada</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
$(function() {
    let historicoCarregado = false;
    
    // Carregar histórico quando a aba for clicada
    $('#btn-tab-historico').on('click', function() {
        if (!historicoCarregado) {
            carregarHistorico();
            historicoCarregado = true;
        }
    });
    
    function carregarHistorico() {
        $.ajax({
            url: '{{ route("locacoes.historico-patrimonio", $patrimonio->id_patrimonio) }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderizarHistorico(response.historico);
                } else {
                    $('#tbody-historico').html('<tr><td colspan="5" class="text-center text-danger">Erro ao carregar histórico</td></tr>');
                }
            },
            error: function() {
                $('#tbody-historico').html('<tr><td colspan="5" class="text-center text-danger">Erro ao carregar histórico</td></tr>');
            }
        });
    }
    
    function renderizarHistorico(historico) {
        let html = '';
        let stats = {
            total: 0,
            finalizadas: 0,
            em_andamento: 0,
            reservas: 0
        };
        
        // Processar dados do histórico
        let locacoesUnicas = {};
        
        if (historico && historico.data) {
            historico.data.forEach(function(item) {
                if (item.locacao && !locacoesUnicas[item.locacao.id_locacao]) {
                    locacoesUnicas[item.locacao.id_locacao] = item.locacao;
                    stats.total++;
                    
                    switch(item.locacao.status) {
                        case 'finalizada': stats.finalizadas++; break;
                        case 'em_andamento': stats.em_andamento++; break;
                        case 'reserva': stats.reservas++; break;
                    }
                }
            });
        }
        
        // Atualizar cards de estatísticas
        $('#stat-total').text(stats.total);
        $('#stat-finalizadas').text(stats.finalizadas);
        $('#stat-andamento').text(stats.em_andamento);
        $('#stat-reservas').text(stats.reservas);
        $('#total-locacoes').text(stats.total + ' registros');
        
        // Renderizar tabela
        let locacoes = Object.values(locacoesUnicas);
        
        if (locacoes.length === 0) {
            html = '<tr><td colspan="5" class="text-center py-4"><div class="text-muted"><i class="ti ti-history ti-lg mb-2"></i><p class="mb-0">Nenhuma locação encontrada para este patrimônio</p></div></td></tr>';
        } else {
            locacoes.forEach(function(locacao) {
                let statusClass = {
                    'reserva': 'bg-warning',
                    'em_andamento': 'bg-primary',
                    'finalizada': 'bg-success',
                    'cancelada': 'bg-danger',
                    'atrasada': 'bg-danger'
                }[locacao.status] || 'bg-secondary';
                
                let statusLabel = {
                    'reserva': 'Reserva',
                    'em_andamento': 'Em Andamento',
                    'finalizada': 'Finalizada',
                    'cancelada': 'Cancelada',
                    'atrasada': 'Atrasada'
                }[locacao.status] || locacao.status;
                
                let dataInicio = locacao.data_inicio ? formatarData(locacao.data_inicio) : '-';
                let dataFim = locacao.data_fim ? formatarData(locacao.data_fim) : '-';
                let cliente = locacao.cliente ? locacao.cliente.nome : 'N/A';
                let contrato = locacao.numero_contrato || locacao.id_locacao;
                
                html += `
                    <tr>
                        <td><strong>#${contrato}</strong></td>
                        <td>${cliente}</td>
                        <td>${dataInicio} - ${dataFim}</td>
                        <td><span class="badge ${statusClass}">${statusLabel}</span></td>
                        <td>
                            <a href="/locacoes/${locacao.id_locacao}" class="btn btn-sm btn-icon btn-outline-primary">
                                <i class="ti ti-eye"></i>
                            </a>
                        </td>
                    </tr>
                `;
            });
        }
        
        $('#tbody-historico').html(html);
    }
    
    function formatarData(data) {
        if (!data) return '-';
        let d = new Date(data);
        return d.toLocaleDateString('pt-BR');
    }
});
</script>
@endsection
