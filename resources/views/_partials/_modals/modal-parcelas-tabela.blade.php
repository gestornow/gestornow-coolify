{{-- Tabela de Parcelas --}}
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Listagem de Parcelas</h5>
    </div>
    <div class="card-datatable table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Parcela</th>
                    <th>Valor</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                    <th>Valor {{ $tipoConta === 'receber' ? 'Recebido' : 'Pago' }}</th>
                    <th>Data {{ $tipoConta === 'receber' ? 'Recebimento' : 'Pagamento' }}</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($parcelas as $parcela)
                    <tr>
                        <td>
                            <strong>{{ $parcela->numero_parcela }}/{{ $parcela->total_parcelas }}</strong>
                        </td>
                        <td>R$ {{ number_format($parcela->valor_total, 2, ',', '.') }}</td>
                        <td>
                            {{ \Carbon\Carbon::parse($parcela->data_vencimento)->format('d/m/Y') }}
                            @if($parcela->isVencida())
                                <br><small class="text-danger"><i class="ti ti-alert-circle"></i> Vencida</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $parcela->getStatusBadgeClass() }}">
                                {{ $parcela->status_label }}
                            </span>
                        </td>
                        <td>R$ {{ number_format($parcela->valor_pago, 2, ',', '.') }}</td>
                        <td>
                            @if($parcela->data_pagamento)
                                {{ \Carbon\Carbon::parse($parcela->data_pagamento)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route($editRoute, $parcela->id_contas) }}" 
                                    class="btn btn-sm btn-icon btn-outline-{{ $btnColor ?? 'primary' }}"
                                    title="Editar">
                                    <i class="ti ti-edit"></i>
                                </a>
                                @if($parcela->status !== 'pago')
                                    <button type="button" 
                                        class="btn btn-sm btn-icon btn-outline-danger"
                                        title="Excluir"
                                        onclick="excluirConta({{ $parcela->id_contas }}, 'Parcela {{ $parcela->numero_parcela }}/{{ $parcela->total_parcelas }}')">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
