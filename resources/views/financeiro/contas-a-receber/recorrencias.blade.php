@extends('layouts.layoutMaster')

@section('title', 'Contas Recorrentes - A Receber')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            <i class="ti ti-repeat me-2"></i>
                            Contas Recorrentes - A Receber
                        </h5>
                        <small class="text-muted">{{ $conta->descricao }} - Periodicidade: {{ ucfirst($conta->tipo_recorrencia) }}</small>
                    </div>
                    <a href="{{ route('financeiro.contas-a-receber.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>
                        Voltar
                    </a>
                </div>
            </div>

            <!-- Resumo das Recorrências -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-success rounded p-2 me-3">
                                    <i class="ti ti-list ti-sm"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Total de Recorrências</small>
                                    <h5 class="mb-0">{{ $recorrencias->count() }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-success rounded p-2 me-3">
                                    <i class="ti ti-check ti-sm"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Recebidas</small>
                                    <h5 class="mb-0">{{ $recorrencias->where('status', 'pago')->count() }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-warning rounded p-2 me-3">
                                    <i class="ti ti-clock ti-sm"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Pendentes</small>
                                    <h5 class="mb-0">{{ $recorrencias->where('status', 'pendente')->count() }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-info rounded p-2 me-3">
                                    <i class="ti ti-cash ti-sm"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Valor por Período</small>
                                    <h5 class="mb-0">R$ {{ number_format($conta->valor_total, 2, ',', '.') }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Recorrências -->
            @include('_partials/_modals/modal-recorrencias-tabela', [
                'recorrencias' => $recorrencias,
                'tipoConta' => 'receber',
                'editRoute' => 'contas-receber.edit',
                'btnColor' => 'success'
            ])
        </div>
    </div>
</div>
@endsection

@section('page-script')
@include('_partials/_modals/modal-delete-conta', [
    'deleteRoute' => '/financeiro/contas-a-receber'
])
@endsection
