@extends('layouts.layoutMaster')

@section('title', 'Parcelas do Parcelamento')

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
                            <i class="ti ti-credit-card me-2 text-success"></i>
                            Parcelas da Conta a Receber
                        </h5>
                        <small class="text-muted">{{ $conta->descricao }}</small>
                    </div>
                    <a href="{{ route('financeiro.contas-a-receber.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>
                        Voltar
                    </a>
                </div>
            </div>

            <!-- Resumo do Parcelamento -->
            @include('_partials/_modals/modal-parcelas-resumo', [
                'totalParcelas' => $parcelas->count(),
                'totalPagas' => $parcelas->where('status', 'pago')->count(),
                'totalPendentes' => $parcelas->where('status', 'pendente')->count(),
                'valorTotal' => $parcelas->sum('valor_total'),
                'tipoConta' => 'receber',
                'badgeColor' => 'success'
            ])

            <!-- Lista de Parcelas -->
            @include('_partials/_modals/modal-parcelas-tabela', [
                'parcelas' => $parcelas,
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
    'deleteRoute' => '/contas-receber'
])
@endsection
