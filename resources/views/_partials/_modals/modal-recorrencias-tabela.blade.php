{{-- Tabela de Recorrências --}}
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Listagem de Recorrências</h5>
    </div>
    <div class="card-datatable table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vencimento</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Valor {{ $tipoConta === 'receber' ? 'Recebido' : 'Pago' }}</th>
                    <th>Data {{ $tipoConta === 'receber' ? 'Recebimento' : 'Pagamento' }}</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recorrencias as $index => $recorrencia)
                    <tr>
                        <td>
                            <strong>{{ $index + 1 }}</strong>
                        </td>
                        <td>
                            {{ \Carbon\Carbon::parse($recorrencia->data_vencimento)->format('d/m/Y') }}
                            @if($recorrencia->isVencida())
                                <br><small class="text-danger"><i class="ti ti-alert-circle"></i> Vencida</small>
                            @endif
                        </td>
                        <td>R$ {{ number_format($recorrencia->valor_total, 2, ',', '.') }}</td>
                        <td>
                            <span class="badge {{ $recorrencia->getStatusBadgeClass() }}">
                                {{ $recorrencia->status_label }}
                            </span>
                        </td>
                        <td>R$ {{ number_format($recorrencia->valor_pago, 2, ',', '.') }}</td>
                        <td>
                            @if($recorrencia->data_pagamento)
                                {{ \Carbon\Carbon::parse($recorrencia->data_pagamento)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route($editRoute, $recorrencia->id_contas) }}" 
                                    class="btn btn-sm btn-icon btn-outline-{{ $btnColor ?? 'primary' }}"
                                    title="Editar">
                                    <i class="ti ti-edit"></i>
                                </a>
                                @if($recorrencia->status !== 'pago')
                                    <button type="button" 
                                        class="btn btn-sm btn-icon btn-outline-danger"
                                        title="Excluir"
                                        onclick="excluirConta({{ $recorrencia->id_contas }}, 'Recorrência {{ $index + 1 }}')">
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
