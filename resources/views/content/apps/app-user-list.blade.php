@extends('layouts/layoutMaster')

@section('title', 'Gerenciamento de Usuários')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
  @include('admin.users.partials.scripts')
@endsection

@section('content')

{{-- Cards de Estatísticas --}}
<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2" id="total-users">0</h4>
            </div>
            <span>Total de Usuários</span>
          </div>
          <span class="badge bg-label-primary rounded p-2">
            <i class="ti ti-users ti-sm"></i>
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
            <span>Usuários Ativos</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2" id="active-users">0</h4>
            </div>
          </div>
          <span class="badge bg-label-success rounded p-2">
            <i class="ti ti-user-check ti-sm"></i>
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
            <span>Usuários Inativos</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2" id="inactive-users">0</h4>
            </div>
          </div>
          <span class="badge bg-label-warning rounded p-2">
            <i class="ti ti-user-exclamation ti-sm"></i>
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
            <span>Usuários Bloqueados</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2" id="blocked-users">0</h4>
            </div>
          </div>
          <span class="badge bg-label-danger rounded p-2">
            <i class="ti ti-lock ti-sm"></i>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Tabela de Usuários --}}
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-3">Filtros de Busca</h5>
    <div class="d-flex justify-content-between align-items-center row pb-2 gap-3 gap-md-0">
      <div class="col-md-4">
        <select id="filter-status" class="form-select">
          <option value="">Todos os Status</option>
          <option value="ativo">Ativo</option>
          <option value="inativo">Inativo</option>
          <option value="bloqueado">Bloqueado</option>
        </select>
      </div>
      <div class="col-md-4">
        <select id="filter-role" class="form-select">
          <option value="">Todas as Funções</option>
          <option value="admin">Administrador</option>
          <option value="user">Usuário</option>
          <option value="manager">Gerente</option>
        </select>
      </div>
    </div>
  </div>
  
  <div class="card-datatable table-responsive">
    <table class="datatables-users table border-top">
      <thead>
        <tr>
          <th>ID</th>
          <th>Usuário</th>
          <th>Login</th>
          <th>Email</th>
          <th>Status</th>
          <th>Cadastro</th>
          <th>Ações</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection

@include('admin.users.partials.modal_create')