@extends('layouts/contentNavbarLayout')

@section('title', 'Relatório - Contas a Pagar')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Relatório de Contas a Pagar</h5>
        <div>
            <button type="button" class="btn btn-sm btn-danger" onclick="gerarRelatorioPDF()">
                <i class="ti ti-file-type-pdf me-1"></i> Exportar PDF
            </button>
            <button type="button" class="btn btn-sm btn-success" onclick="gerarRelatorioExcel()">
                <i class="ti ti-file-type-xls me-1"></i> Exportar Excel
            </button>
            <a href="{{ route('financeiro.relatorios.contas-pagar') }}" class="btn btn-sm btn-secondary">
                <i class="ti ti-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filtros Aplicados -->
        @if(!empty($filtros))
        <div class="alert alert-info">
            <strong>Filtros aplicados:</strong>
            <ul class="mb-0 mt-2">
                @if(!empty($filtros['data_inicio']))
                <li>Data inicial: {{ date('d/m/Y', strtotime($filtros['data_inicio'])) }}</li>
                @endif
                @if(!empty($filtros['data_fim']))
                <li>Data final: {{ date('d/m/Y', strtotime($filtros['data_fim'])) }}</li>
                @endif
                @if(!empty($filtros['status']))
                <li>Status: {{ ucfirst($filtros['status']) }}</li>
                @endif
                @if(!empty($filtros['id_fornecedor']))
                <li>Fornecedor: {{ $contas->first()->fornecedor->razao_social ?? '' }}</li>
                @endif
                @if(!empty($filtros['id_categoria_contas']))
                <li>Categoria: {{ $contas->first()->categoria->nome ?? '' }}</li>
                @endif
            </ul>
        </div>
        @endif

        <!-- Resumo Financeiro -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title text-white">Total Geral</h6>
                        <h4 class="text-white mb-0">R$ {{ number_format($total_geral, 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title text-white">Total Pago</h6>
                        <h4 class="text-white mb-0">R$ {{ number_format($total_pago, 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="card-title text-white">Total Pendente</h6>
                        <h4 class="text-white mb-0">R$ {{ number_format($total_pendente, 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="card-title text-white">Total Vencido</h6>
                        <h4 class="text-white mb-0">R$ {{ number_format($total_vencido, 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Contas -->
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Vencimento</th>
                        <th>Descrição</th>
                        <th>Fornecedor</th>
                        <th>Documento</th>
                        <th>Categoria</th>
                        <th>Banco</th>
                        <th>Forma Pgto</th>
                        <th class="text-end">Valor Total</th>
                        <th class="text-end">Valor Pago</th>
                        <th class="text-end">Restante</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contas as $conta)
                    <tr>
                        <td>{{ date('d/m/Y', strtotime($conta->data_vencimento)) }}</td>
                        <td>{{ $conta->descricao }}</td>
                        <td>{{ $conta->fornecedor->razao_social ?? '-' }}</td>
                        <td>{{ $conta->documento ?? '-' }}</td>
                        <td>{{ $conta->categoria->nome ?? '-' }}</td>
                        <td>{{ $conta->banco->nome_banco ?? '-' }}</td>
                        <td>{{ $conta->formaPagamento->nome ?? '-' }}</td>
                        <td class="text-end">R$ {{ number_format($conta->valor_total, 2, ',', '.') }}</td>
                        <td class="text-end">R$ {{ number_format($conta->valor_pago ?: 0, 2, ',', '.') }}</td>
                        <td class="text-end">R$ {{ number_format($conta->valor_total - ($conta->valor_pago ?: 0), 2, ',', '.') }}</td>
                        <td class="text-center">
                            @php
                                $badgeClass = 'secondary';
                                if ($conta->status == 'pago') $badgeClass = 'success';
                                elseif ($conta->status == 'vencido') $badgeClass = 'danger';
                                elseif ($conta->status == 'pendente') $badgeClass = 'warning';
                                elseif ($conta->status == 'cancelado') $badgeClass = 'dark';
                            @endphp
                            <span class="badge bg-{{ $badgeClass }}">{{ $conta->status_label }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="text-center">Nenhum registro encontrado</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($contas->isNotEmpty())
                <tfoot class="table-secondary">
                    <tr>
                        <th colspan="7" class="text-end">TOTAIS:</th>
                        <th class="text-end">R$ {{ number_format($total_geral, 2, ',', '.') }}</th>
                        <th class="text-end">R$ {{ number_format($total_pago, 2, ',', '.') }}</th>
                        <th class="text-end">R$ {{ number_format($total_restante, 2, ',', '.') }}</th>
                        <th></th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

<!-- Form oculto para enviar dados para PDF/Excel -->
<form id="formRelatorioPDF" method="POST" action="{{ route('financeiro.relatorios.contas-pagar.pdf') }}" style="display:none;">
    @csrf
    @foreach($filtros as $key => $value)
        @if(!empty($value))
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

<form id="formRelatorioExcel" method="POST" action="{{ route('financeiro.relatorios.contas-pagar.excel') }}" style="display:none;">
    @csrf
    @foreach($filtros as $key => $value)
        @if(!empty($value))
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

@endsection

@section('page-script')
<script>
function gerarRelatorioPDF() {
    document.getElementById('formRelatorioPDF').submit();
}

function gerarRelatorioExcel() {
    document.getElementById('formRelatorioExcel').submit();
}
</script>
@endsection
