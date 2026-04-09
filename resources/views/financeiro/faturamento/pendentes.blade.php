@extends('layouts.layoutMaster')

@section('title', 'Locações Pendentes de Faturamento')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Locações Pendentes de Faturamento</h4>
                    <p class="text-muted mb-0">Locações com status 'Aprovado' ou 'Encerrado' que ainda não foram faturadas.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('financeiro.faturamento.index') }}" class="btn btn-outline-primary">
                        <i class="ti ti-file-invoice me-1"></i>
                        Ver Faturamentos Realizados
                    </a>
                </div>
            </div>

            @if(!$tabelaDisponivel)
                <div class="alert alert-warning" role="alert">
                    A tabela <strong>faturamento_locacoes</strong> ainda não existe no banco. Execute o script SQL <strong>database/sql/create_faturamento_locacoes.sql</strong> para habilitar esta funcionalidade.
                </div>
            @endif

            <!-- Estatísticas -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted">Total de Locações Pendentes</span>
                            <h4 class="mb-0 mt-1">{{ $stats['total_locacoes'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted">Valor Total a Faturar</span>
                            <h4 class="mb-0 mt-1 text-primary">R$ {{ number_format($stats['valor_total'] ?? 0, 2, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('financeiro.faturamento.pendentes') }}">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="aprovado" {{ request('status') === 'aprovado' ? 'selected' : '' }}>Aprovado</option>
                                    <option value="encerrado" {{ request('status') === 'encerrado' ? 'selected' : '' }}>Encerrado</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Busca</label>
                                <input type="text" name="busca" value="{{ request('busca') }}" class="form-control" placeholder="Número do contrato ou nome do cliente">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="ti ti-filter me-1"></i> Filtrar
                                </button>
                                <a href="{{ route('financeiro.faturamento.pendentes') }}" class="btn btn-outline-secondary">
                                    <i class="ti ti-x"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Barra de Ações em Lote -->
            <div class="card mb-3 d-none" id="barraAcoesLote">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="contador-selecionados">0</strong> locação(ões) selecionada(s)
                            <span class="ms-2 text-muted">|</span>
                            <span class="ms-2 text-primary fw-semibold">Total: R$ <span id="valor-selecionados">0,00</span></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limparSelecao()">
                                <i class="ti ti-x me-1"></i>
                                Limpar Seleção
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalFaturamentoLote()">
                                <i class="ti ti-file-invoice me-1"></i>
                                Faturar Selecionadas
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Locações -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" id="checkAll" onchange="selecionarTodas(this)">
                                    </th>
                                    <th>Locação</th>
                                    <th>Cliente</th>
                                    <th>Período</th>
                                    <th class="text-end">Valor</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($locacoes as $locacao)
                                    @php
                                        $statusClass = match($locacao->status) {
                                            'aprovado' => 'bg-label-info',
                                            'encerrado' => 'bg-label-success',
                                            default => 'bg-label-secondary'
                                        };
                                        $statusLabel = ucfirst($locacao->status);
                                    @endphp
                                    <tr>
                                        <td>
                                            <input type="checkbox" 
                                                   class="form-check-input check-locacao" 
                                                   data-id="{{ $locacao->id_locacao }}"
                                                   data-valor="{{ $locacao->valor_final }}"
                                                   data-contrato="{{ $locacao->numero_contrato ?? $locacao->id_locacao }}"
                                                   onchange="atualizarSelecao()">
                                        </td>
                                        <td>
                                            <a href="{{ route('locacoes.show', $locacao->id_locacao) }}" class="text-primary fw-semibold" target="_blank">
                                                #{{ $locacao->numero_contrato ?? $locacao->id_locacao }}
                                            </a>
                                        </td>
                                        <td>
                                            @if($locacao->cliente)
                                                {{ $locacao->cliente->nome ?? $locacao->cliente->razao_social }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small>
                                                {{ optional($locacao->data_inicio)->format('d/m/Y') ?? '-' }} até 
                                                {{ optional($locacao->data_fim)->format('d/m/Y') ?? '-' }}
                                            </small>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            R$ {{ number_format((float) $locacao->valor_final, 2, ',', '.') }}
                                        </td>
                                        <td>
                                            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalFaturamento({{ $locacao->id_locacao }}, '{{ $locacao->numero_contrato }}', {{ $locacao->valor_final }})">
                                                <i class="ti ti-file-invoice me-1"></i>
                                                Faturar
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Nenhuma locação pendente de faturamento encontrada.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(method_exists($locacoes, 'links'))
                        <div class="mt-3">
                            {{ $locacoes->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Faturamento Individual -->
<div class="modal fade" id="modalFaturamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Faturar Locação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formFaturamento" onsubmit="realizarFaturamento(event)">
                <div class="modal-body">
                    <input type="hidden" id="id_locacao_faturamento" name="id_locacao">
                    
                    <div class="alert alert-info">
                        <strong>Locação:</strong> <span id="numero_contrato_display"></span><br>
                        <strong>Valor:</strong> <span id="valor_display"></span>
                    </div>

                    <div class="mb-3">
                        <label for="data_vencimento" class="form-label">Data de Vencimento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" required>
                        <small class="text-muted">Data base para geração das parcelas</small>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="quantidade_parcelas" class="form-label">Parcelas</label>
                            <input type="number" class="form-control" id="quantidade_parcelas" name="quantidade_parcelas" min="1" max="24" value="1">
                            <small class="text-muted">1 = à vista</small>
                        </div>
                        <div class="col-md-4">
                            <label for="intervalo_parcelas" class="form-label">Intervalo</label>
                            <select class="form-select" id="intervalo_parcelas">
                                <option value="7">Semanal (7 dias)</option>
                                <option value="15">Quinzenal (15 dias)</option>
                                <option value="30" selected>Mensal (30 dias)</option>
                                <option value="60">Bimestral (60 dias)</option>
                                <option value="90">Trimestral (90 dias)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-primary w-100" onclick="gerarParcelasFaturamento()">
                                <i class="ti ti-refresh me-1"></i>
                                Gerar Parcelas
                            </button>
                        </div>
                    </div>

                    <div id="div_parcelas_faturamento" class="mb-3" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">
                                        <i class="ti ti-calendar-event me-1"></i>
                                        Parcelas do Faturamento
                                    </h6>
                                    <small class="text-muted">
                                        <strong>Total:</strong> <span id="total_parcelas_faturamento">0</span>
                                    </small>
                                </div>
                                
                                <div id="lista_parcelas_faturamento">
                                    <!-- Parcelas serão adicionadas aqui -->
                                </div>

                                <div class="alert alert-info mt-3 mb-0">
                                    <small>
                                        <strong>Total Parcelas:</strong> R$ <span id="total_valor_parcelas">0,00</span><br>
                                        <strong>Valor Original:</strong> R$ <span id="valor_original_display">0,00</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3" maxlength="1000"></textarea>
                        <small class="text-muted">Máximo 1000 caracteres</small>
                    </div>

                    <div class="alert alert-warning mb-0">
                        <i class="ti ti-alert-triangle me-1"></i>
                        <strong>Atenção:</strong> Ao faturar, será criado automaticamente um registro em "Contas a Receber".
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnFaturar">
                        <i class="ti ti-check me-1"></i>
                        Confirmar Faturamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Faturamento em Lote -->
<div class="modal fade" id="modalFaturamentoLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Faturar Múltiplas Locações</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formFaturamentoLote" onsubmit="realizarFaturamentoLote(event)">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong><span id="total-locacoes-lote">0</span> locação(ões) selecionada(s)</strong><br>
                        <strong>Valor Total:</strong> R$ <span id="valor-total-lote">0,00</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Locações que serão faturadas:</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Locação</th>
                                        <th class="text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody id="lista-locacoes-lote">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="data_vencimento_lote" class="form-label">Data de Vencimento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_vencimento_lote" name="data_vencimento" required>
                        <small class="text-muted">Data base para geração das parcelas</small>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="quantidade_parcelas_lote" class="form-label">Parcelas</label>
                            <input type="number" class="form-control" id="quantidade_parcelas_lote" name="quantidade_parcelas" min="1" max="24" value="1">
                            <small class="text-muted">1 = à vista</small>
                        </div>
                        <div class="col-md-4">
                            <label for="intervalo_parcelas_lote" class="form-label">Intervalo</label>
                            <select class="form-select" id="intervalo_parcelas_lote">
                                <option value="7">Semanal (7 dias)</option>
                                <option value="15">Quinzenal (15 dias)</option>
                                <option value="30" selected>Mensal (30 dias)</option>
                                <option value="60">Bimestral (60 dias)</option>
                                <option value="90">Trimestral (90 dias)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-primary w-100" onclick="gerarParcelasFaturamentoLote()">
                                <i class="ti ti-refresh me-1"></i>
                                Gerar Parcelas
                            </button>
                        </div>
                    </div>

                    <div id="div_parcelas_faturamento_lote" class="mb-3" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">
                                        <i class="ti ti-calendar-event me-1"></i>
                                        Parcelas do Faturamento
                                    </h6>
                                    <small class="text-muted">
                                        <strong>Total:</strong> <span id="total_parcelas_faturamento_lote">0</span>
                                    </small>
                                </div>
                                
                                <div id="lista_parcelas_faturamento_lote">
                                    <!-- Parcelas serão adicionadas aqui -->
                                </div>

                                <div class="alert alert-info mt-3 mb-0">
                                    <small>
                                        <strong>Total Parcelas:</strong> R$ <span id="total_valor_parcelas_lote">0,00</span><br>
                                        <strong>Valor Original:</strong> R$ <span id="valor_original_display_lote">0,00</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observacoes_lote" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes_lote" name="observacoes" rows="3" maxlength="1000"></textarea>
                        <small class="text-muted">Máximo 1000 caracteres</small>
                    </div>

                    <div class="alert alert-warning mb-0">
                        <i class="ti ti-alert-triangle me-1"></i>
                        <strong>Atenção:</strong> Será criado um único registro em "Contas a Receber" com o valor consolidado de todas as locações.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnFaturarLote">
                        <i class="ti ti-check me-1"></i>
                        Confirmar Faturamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
let modalFaturamento;
let modalFaturamentoLote;
let locacoesSelecionadas = [];

document.addEventListener('DOMContentLoaded', function() {
    modalFaturamento = new bootstrap.Modal(document.getElementById('modalFaturamento'));
    modalFaturamentoLote = new bootstrap.Modal(document.getElementById('modalFaturamentoLote'));
});

function selecionarTodas(checkbox) {
    const checkboxes = document.querySelectorAll('.check-locacao');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    atualizarSelecao();
}

function atualizarSelecao() {
    const checkboxes = document.querySelectorAll('.check-locacao:checked');
    const barra = document.getElementById('barraAcoesLote');
    const contador = document.getElementById('contador-selecionados');
    const valorSpan = document.getElementById('valor-selecionados');
    
    locacoesSelecionadas = [];
    let valorTotal = 0;
    
    checkboxes.forEach(cb => {
        const id = cb.dataset.id;
        const valor = parseFloat(cb.dataset.valor || 0);
        const contrato = cb.dataset.contrato;
        
        locacoesSelecionadas.push({
            id: id,
            valor: valor,
            contrato: contrato
        });
        
        valorTotal += valor;
    });
    
    contador.textContent = locacoesSelecionadas.length;
    valorSpan.textContent = valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    if (locacoesSelecionadas.length > 0) {
        barra.classList.remove('d-none');
    } else {
        barra.classList.add('d-none');
    }
    
    // Atualizar checkbox "Selecionar Todas"
    const checkAll = document.getElementById('checkAll');
    const totalCheckboxes = document.querySelectorAll('.check-locacao').length;
    checkAll.checked = locacoesSelecionadas.length === totalCheckboxes && totalCheckboxes > 0;
}

function limparSelecao() {
    document.querySelectorAll('.check-locacao').forEach(cb => cb.checked = false);
    document.getElementById('checkAll').checked = false;
    atualizarSelecao();
}

function abrirModalFaturamento(idLocacao, numeroContrato, valor) {
    document.getElementById('id_locacao_faturamento').value = idLocacao;
    document.getElementById('numero_contrato_display').textContent = '#' + numeroContrato;
    document.getElementById('valor_display').textContent = 'R$ ' + parseFloat(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Definir data de vencimento padrão (30 dias)
    const dataVencimento = new Date();
    dataVencimento.setDate(dataVencimento.getDate() + 30);
    document.getElementById('data_vencimento').value = dataVencimento.toISOString().split('T')[0];
    
    // Resetar quantidade de parcelas
    document.getElementById('quantidade_parcelas').value = 1;
    document.getElementById('intervalo_parcelas').value = 30;
    document.getElementById('div_parcelas_faturamento').style.display = 'none';
    
    // Limpar observações
    document.getElementById('observacoes').value = '';
    
    // Armazenar valor e número do contrato para cálculo de parcelas
    window.valorFaturamento = parseFloat(valor);
    window.numeroContratoFaturamento = numeroContrato;
    
    modalFaturamento.show();
}

function abrirModalFaturamentoLote() {
    if (locacoesSelecionadas.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione pelo menos uma locação.',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    // Atualizar informações no modal
    document.getElementById('total-locacoes-lote').textContent = locacoesSelecionadas.length;
    
    let valorTotal = 0;
    let listaHtml = '';
    
    locacoesSelecionadas.forEach(loc => {
        valorTotal += loc.valor;
        listaHtml += `
            <tr>
                <td>#${loc.contrato}</td>
                <td class="text-end">R$ ${loc.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            </tr>
        `;
    });
    
    document.getElementById('valor-total-lote').textContent = valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('lista-locacoes-lote').innerHTML = listaHtml;
    
    // Definir data de vencimento padrão (30 dias)
    const dataVencimento = new Date();
    dataVencimento.setDate(dataVencimento.getDate() + 30);
    document.getElementById('data_vencimento_lote').value = dataVencimento.toISOString().split('T')[0];
    
    // Resetar quantidade de parcelas
    document.getElementById('quantidade_parcelas_lote').value = 1;
    document.getElementById('intervalo_parcelas_lote').value = 30;
    document.getElementById('div_parcelas_faturamento_lote').style.display = 'none';
    
    // Limpar observações
    document.getElementById('observacoes_lote').value = '';
    
    // Armazenar valor para cálculo de parcelas
    window.valorFaturamentoLote = valorTotal;
    
    modalFaturamentoLote.show();
}

async function realizarFaturamento(event) {
    event.preventDefault();
    
    const btnFaturar = document.getElementById('btnFaturar');
    const btnTextOriginal = btnFaturar.innerHTML;
    
    try {
        btnFaturar.disabled = true;
        btnFaturar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Faturando...';
        
        const formData = new FormData(event.target);
        const idLocacao = document.getElementById('id_locacao_faturamento').value;
        const quantidadeParcelas = parseInt(document.getElementById('quantidade_parcelas').value) || 1;
        
        // Adicionar quantidade de parcelas
        formData.append('quantidade_parcelas', quantidadeParcelas);
        
        // Se tem mais de 1 parcela, coletar os dados das parcelas customizadas
        if (quantidadeParcelas > 1) {
            const parcelas = document.querySelectorAll('#lista_parcelas_faturamento .parcela-item');
            
            if (parcelas.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Por favor, clique em "Gerar Parcelas" antes de faturar.',
                    confirmButtonText: 'OK'
                });
                btnFaturar.disabled = false;
                btnFaturar.innerHTML = btnTextOriginal;
                return;
            }
            
            // Já vem do FormData os campos parcelas[i][descricao], parcelas[i][data_vencimento], parcelas[i][valor]
        }
        
        const response = await fetch(`/financeiro/faturamento/faturar/${idLocacao}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            modalFaturamento.hide();
            mostrarMensagem(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message,
                confirmButtonText: 'OK'
            });
            btnFaturar.disabled = false;
            btnFaturar.innerHTML = btnTextOriginal;
        }
    } catch (error) {
        console.error('Erro ao faturar:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao processar faturamento. Por favor, tente novamente.',
            confirmButtonText: 'OK'
        });
        btnFaturar.disabled = false;
        btnFaturar.innerHTML = btnTextOriginal;
    }
}

async function realizarFaturamentoLote(event) {
    event.preventDefault();
    
    const btnFaturar = document.getElementById('btnFaturarLote');
    const btnTextOriginal = btnFaturar.innerHTML;
    
    try {
        btnFaturar.disabled = true;
        btnFaturar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Faturando...';
        
        const formData = new FormData(event.target);
        const idsLocacoes = locacoesSelecionadas.map(loc => loc.id);
        const quantidadeParcelas = parseInt(document.getElementById('quantidade_parcelas_lote').value) || 1;
        const dataVencimento = document.getElementById('data_vencimento_lote').value;
        
        const payload = {
            locacoes: idsLocacoes,
            observacoes: formData.get('observacoes') || '',
            data_vencimento: dataVencimento,
            quantidade_parcelas: quantidadeParcelas
        };
        
        // Se tem mais de 1 parcela, coletar os dados das parcelas customizadas
        if (quantidadeParcelas > 1) {
            const parcelas = document.querySelectorAll('#lista_parcelas_faturamento_lote .parcela-item');
            
            if (parcelas.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Por favor, clique em "Gerar Parcelas" antes de faturar.',
                    confirmButtonText: 'OK'
                });
                btnFaturar.disabled = false;
                btnFaturar.innerHTML = btnTextOriginal;
                return;
            }
            
            payload.parcelas = [];
            parcelas.forEach((parcela) => {
                const descricao = parcela.querySelector('input[name*="[descricao]"]').value;
                const data_vencimento = parcela.querySelector('input[name*="[data_vencimento]"]').value;
                const valor = parcela.querySelector('input[name*="[valor]"]').value;
                
                payload.parcelas.push({
                    descricao: descricao,
                    data_vencimento: data_vencimento,
                    valor: valor
                });
            });
        }
        
        const response = await fetch('/financeiro/faturamento/faturar-lote', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            modalFaturamentoLote.hide();
            mostrarMensagem(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message,
                confirmButtonText: 'OK'
            });
            btnFaturar.disabled = false;
            btnFaturar.innerHTML = btnTextOriginal;
        }
    } catch (error) {
        console.error('Erro ao faturar lote:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao processar faturamento em lote. Por favor, tente novamente.',
            confirmButtonText: 'OK'
        });
        btnFaturar.disabled = false;
        btnFaturar.innerHTML = btnTextOriginal;
    }
}

function mostrarMensagem(mensagem, tipo) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${tipo} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        <i class="ti ti-check me-1"></i>
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => alertDiv.remove(), 5000);
}

// ============================================
// Funções de Parcelamento - Modal Individual
// ============================================

function gerarParcelasFaturamento() {
    const quantidade = parseInt(document.getElementById('quantidade_parcelas').value) || 1;
    const intervalo = parseInt(document.getElementById('intervalo_parcelas').value) || 30;
    const dataBase = document.getElementById('data_vencimento').value;
    const valorTotal = window.valorFaturamento || 0;
    const numeroContrato = window.numeroContratoFaturamento || '';
    
    if (!dataBase) {
        alert('Informe a data de vencimento base!');
        return;
    }
    
    if (valorTotal <= 0) {
        alert('Valor do faturamento inválido!');
        return;
    }
    
    if (quantidade === 1) {
        document.getElementById('div_parcelas_faturamento').style.display = 'none';
        document.getElementById('lista_parcelas_faturamento').innerHTML = '';
        return;
    }
    
    document.getElementById('div_parcelas_faturamento').style.display = 'block';
    
    const valorParcela = valorTotal / quantidade;
    const diferenca = valorTotal - (valorParcela * quantidade);
    
    let html = '';
    
    for (let i = 0; i < quantidade; i++) {
        const dataVenc = new Date(dataBase);
        dataVenc.setDate(dataVenc.getDate() + (i * intervalo));
        const dataFormatada = dataVenc.toISOString().split('T')[0];
        
        let valor = valorParcela;
        if (i === quantidade - 1) {
            valor += diferenca; // Ajusta última parcela
        }
        
        html += `
            <div class="row mb-2 align-items-center parcela-item" data-index="${i}">
                <div class="col-md-1">
                    <input type="text" class="form-control form-control-sm text-center" value="${i + 1}/${quantidade}" readonly>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm" name="parcelas[${i}][descricao]" value="Fatura Locação ${numeroContrato} - Parcela ${i + 1}/${quantidade}" placeholder="Descrição">
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control form-control-sm" name="parcelas[${i}][data_vencimento]" value="${dataFormatada}" required>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm text-end parcela-valor" name="parcelas[${i}][valor]" value="${valor.toFixed(2)}" data-original="${valor.toFixed(2)}" onchange="redistribuirValorParcelasFaturamento(${i})" required>
                </div>
            </div>
        `;
    }
    
    document.getElementById('lista_parcelas_faturamento').innerHTML = html;
    document.getElementById('total_parcelas_faturamento').textContent = quantidade;
    document.getElementById('valor_original_display').textContent = valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Aplica máscara de dinheiro após um pequeno delay para garantir que os elementos existam no DOM
    setTimeout(() => {
        $('#lista_parcelas_faturamento .parcela-valor').each(function() {
            const valor = parseFloat($(this).val()) || 0;
            $(this).val(valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        });
        atualizarTotalParcelasFaturamento();
    }, 50);
}

function redistribuirValorParcelasFaturamento(indexEditado) {
    const valorTotal = window.valorFaturamento || 0;
    const parcelas = document.querySelectorAll('#lista_parcelas_faturamento .parcela-item');
    
    const inputEditado = parcelas[indexEditado].querySelector('.parcela-valor');
    const valorEditadoStr = inputEditado.value.replace('R$', '').trim().replace(/\./g, '').replace(',', '.');
    const valorEditado = parseFloat(valorEditadoStr) || 0;
    
    let somaOutras = 0;
    let countOutras = 0;
    
    parcelas.forEach((parcela, i) => {
        if (i !== indexEditado) {
            countOutras++;
        }
    });
    
    if (countOutras > 0) {
        const valorRestante = valorTotal - valorEditado;
        const valorParaOutras = valorRestante / countOutras;
        const diferenca = valorRestante - (valorParaOutras * countOutras);
        
        let indexUltima = -1;
        parcelas.forEach((parcela, i) => {
            if (i !== indexEditado) {
                indexUltima = i;
                const input = parcela.querySelector('.parcela-valor');
                let novoValor = valorParaOutras;
                
                input.value = novoValor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                input.setAttribute('data-original', novoValor.toFixed(2));
            }
        });
        
        // Ajusta última parcela
        if (indexUltima >= 0) {
            const inputUltima = parcelas[indexUltima].querySelector('.parcela-valor');
            const valorAtualStr = inputUltima.value.replace('R$', '').trim().replace(/\./g, '').replace(',', '.');
            const valorAtual = parseFloat(valorAtualStr) || 0;
            const novoValorUltima = valorAtual + diferenca;
            inputUltima.value = novoValorUltima.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            inputUltima.setAttribute('data-original', novoValorUltima.toFixed(2));
        }
    }
    
    atualizarTotalParcelasFaturamento();
}

function atualizarTotalParcelasFaturamento() {
    const parcelas = document.querySelectorAll('#lista_parcelas_faturamento .parcela-valor');
    let total = 0;
    
    parcelas.forEach(input => {
        const valorStr = input.value.replace('R$', '').trim().replace(/\./g, '').replace(',', '.');
        const valor = parseFloat(valorStr) || 0;
        total += valor;
    });
    
    document.getElementById('total_valor_parcelas').textContent = total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// ============================================
// Funções de Parcelamento - Modal Lote
// ============================================

function gerarParcelasFaturamentoLote() {
    const quantidade = parseInt(document.getElementById('quantidade_parcelas_lote').value) || 1;
    const intervalo = parseInt(document.getElementById('intervalo_parcelas_lote').value) || 30;
    const dataBase = document.getElementById('data_vencimento_lote').value;
    const valorTotal = window.valorFaturamentoLote || 0;
    
    if (!dataBase) {
        alert('Informe a data de vencimento base!');
        return;
    }
    
    if (valorTotal <= 0) {
        alert('Valor do faturamento inválido!');
        return;
    }
    
    if (quantidade === 1) {
        document.getElementById('div_parcelas_faturamento_lote').style.display = 'none';
        document.getElementById('lista_parcelas_faturamento_lote').innerHTML = '';
        return;
    }
    
    document.getElementById('div_parcelas_faturamento_lote').style.display = 'block';
    
    const valorParcela = valorTotal / quantidade;
    const diferenca = valorTotal - (valorParcela * quantidade);
    
    let html = '';
    
    for (let i = 0; i < quantidade; i++) {
        const dataVenc = new Date(dataBase);
        dataVenc.setDate(dataVenc.getDate() + (i * intervalo));
        const dataFormatada = dataVenc.toISOString().split('T')[0];
        
        let valor = valorParcela;
        if (i === quantidade - 1) {
            valor += diferenca; // Ajusta última parcela
        }
        
        html += `
            <div class="row mb-2 align-items-center parcela-item" data-index="${i}">
                <div class="col-md-1">
                    <input type="text" class="form-control form-control-sm text-center" value="${i + 1}/${quantidade}" readonly>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm" name="parcelas[${i}][descricao]" value="Fatura Locação Lote - Parcela ${i + 1}/${quantidade}" placeholder="Descrição">
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control form-control-sm" name="parcelas[${i}][data_vencimento]" value="${dataFormatada}" required>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm text-end parcela-valor" name="parcelas[${i}][valor]" value="${valor.toFixed(2)}" data-original="${valor.toFixed(2)}" onchange="redistribuirValorParcelasFaturamentoLote(${i})" required>
                </div>
            </div>
        `;
    }
    
    document.getElementById('lista_parcelas_faturamento_lote').innerHTML = html;
    document.getElementById('total_parcelas_faturamento_lote').textContent = quantidade;
    document.getElementById('valor_original_display_lote').textContent = valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Aplica máscara de dinheiro após um pequeno delay para garantir que os elementos existam no DOM
    setTimeout(() => {
        $('#lista_parcelas_faturamento_lote .parcela-valor').each(function() {
            const valor = parseFloat($(this).val()) || 0;
            $(this).val(valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        });
        atualizarTotalParcelasFaturamentoLote();
    }, 50);
}

function redistribuirValorParcelasFaturamentoLote(indexEditado) {
    const valorTotal = window.valorFaturamentoLote || 0;
    const parcelas = document.querySelectorAll('#lista_parcelas_faturamento_lote .parcela-item');
    
    const inputEditado = parcelas[indexEditado].querySelector('.parcela-valor');
    const valorEditadoStr = inputEditado.value.replace('R$', '').trim().replace(/\./g, '').replace(',', '.');
    const valorEditado = parseFloat(valorEditadoStr) || 0;
    
    let somaOutras = 0;
    let countOutras = 0;
    
    parcelas.forEach((parcela, i) => {
        if (i !== indexEditado) {
            countOutras++;
        }
    });
    
    if (countOutras > 0) {
        const valorRestante = valorTotal - valorEditado;
        const valorParaOutras = valorRestante / countOutras;
        const diferenca = valorRestante - (valorParaOutras * countOutras);
        
        let indexUltima = -1;
        parcelas.forEach((parcela, i) => {
            if (i !== indexEditado) {
                indexUltima = i;
                const input = parcela.querySelector('.parcela-valor');
                let novoValor = valorParaOutras;
                
                input.value = novoValor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                input.setAttribute('data-original', novoValor.toFixed(2));
            }
        });
        
        // Ajusta última parcela
        if (indexUltima >= 0) {
            const inputUltima = parcelas[indexUltima].querySelector('.parcela-valor');
            const valorAtualStr = inputUltima.value.replace('R$', '').trim().replace(/\./g, '').replace(',', '.');
            const valorAtual = parseFloat(valorAtualStr) || 0;
            const novoValorUltima = valorAtual + diferenca;
            inputUltima.value = novoValorUltima.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            inputUltima.setAttribute('data-original', novoValorUltima.toFixed(2));
        }
    }
    
    atualizarTotalParcelasFaturamentoLote();
}

function atualizarTotalParcelasFaturamentoLote() {
    const parcelas = document.querySelectorAll('#lista_parcelas_faturamento_lote .parcela-valor');
    let total = 0;
    
    parcelas.forEach(input => {
        const valorStr = input.value.replace('R$', '').trim().replace(/\./g, '').replace(',', '.');
        const valor = parseFloat(valorStr) || 0;
        total += valor;
    });
    
    document.getElementById('total_valor_parcelas_lote').textContent = total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

</script>
@endsection
