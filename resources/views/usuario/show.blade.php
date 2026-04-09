@extends('layouts.layoutMaster')

@section('title', 'Detalhes do Usuário - ' . ($user->nome ?? 'Usuário'))

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Usuário: {{ $user->nome }}</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Voltar</a>
                        <a href="{{ route('usuarios.editar', $user->id_usuario) }}" class="btn btn-primary">Editar</a>
                    </div>
                </div>
                <div class="card-body">
                    {{-- Visualização somente leitura --}}
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Login</label>
                            <div class="fw-bold">{{ $user->login }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Perfil Global</label>
                            <div>{{ $user->finalidade ?? 'Sem perfil global' }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small">Telefone</label>
                            <div>{{ $user->telefone ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">CPF</label>
                            <div>{{ $user->cpf ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Status</label>
                            <div>
                                @if($user->status === 'ativo') <span class="badge bg-label-success">Ativo</span>
                                @elseif($user->status === 'inativo') <span class="badge bg-label-warning">Inativo</span>
                                @elseif($user->status === 'bloqueado') <span class="badge bg-label-danger">Bloqueado</span>
                                @else <span class="badge bg-label-secondary">Indefinido</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small">Endereço</label>
                            <div>{{ $user->endereco ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">CEP</label>
                            <div>{{ $user->cep ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Bairro</label>
                            <div>{{ $user->bairro ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small">Comissão (%)</label>
                            <div>{{ $user->comissao ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Empresa</label>
                            <div>{{ $user->empresa->nome_empresa ?? '-' }}</div>
                        </div>

                        <div class="col-12 mt-3">
                            <label class="form-label small">Observações</label>
                            <div class="border rounded p-3">{{ $user->observacoes ?? '-' }}</div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

