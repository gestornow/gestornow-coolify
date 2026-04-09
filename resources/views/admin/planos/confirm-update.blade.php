@extends('layouts.layoutMaster')

@section('title', 'Confirmar Atualização de Plano')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<style>
    /* Blur no fundo quando SweetAlert estiver aberto */
    body.swal2-shown > :not(.swal2-container) {
        filter: blur(5px);
        transition: filter 0.3s ease;
    }
    
    /* Container do SweetAlert */
    .swal2-container {
        backdrop-filter: blur(8px);
        background-color: rgba(0, 0, 0, 0.4) !important;
    }
    
    /* Popup customizado */
    .swal2-popup {
        border-radius: 0.875rem !important;
        padding: 1.5rem !important;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3) !important;
    }
    
    /* Ícone */
    .swal2-icon {
        margin: 0.75rem auto !important;
    }
    
    /* Título */
    .swal2-title {
        font-size: 1.25rem !important;
        font-weight: 600 !important;
        color: #566a7f !important;
        margin-bottom: 0.75rem !important;
        line-height: 1.4 !important;
        text-align: center !important;
    }
    
    /* Conteúdo HTML */
    .swal2-html-container {
        font-size: 0.875rem !important;
        line-height: 1.5 !important;
        color: #697a8d !important;
        margin: 0 !important;
    }
    
    /* Alertas dentro do modal */
    .swal2-html-container .alert {
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
        border: none;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }
    
    .swal2-html-container .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }
    
    .swal2-html-container .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }
    
    .swal2-html-container .alert-heading {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
    }
    
    .swal2-html-container .alert ul {
        margin-bottom: 0;
        padding-left: 1.5rem;
    }
    
    .swal2-html-container .alert li {
        margin-bottom: 0.25rem;
    }
    
    /* Botões */
    .swal2-actions {
        gap: 0.5rem !important;
        margin-top: 1rem !important;
    }
    
    .swal2-confirm,
    .swal2-deny,
    .swal2-cancel {
        font-weight: 500 !important;
        padding: 0.5rem 1.25rem !important;
        border-radius: 0.5rem !important;
        font-size: 0.875rem !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12) !important;
    }
    
    .swal2-confirm:hover,
    .swal2-deny:hover,
    .swal2-cancel:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.18) !important;
    }
    
    /* Animação de entrada */
    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.8);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .swal2-show {
        animation: fadeInScale 0.3s ease !important;
    }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Exibir SweetAlert automaticamente
    Swal.fire({
        html: `
            <div style="text-align: center; margin-bottom: 1.25rem;">
                <i class="ti ti-alert-circle text-warning" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                <h2 style="font-size: 1.25rem; font-weight: 600; color: #566a7f; margin: 0;">Detectamos mudanças nos módulos!</h2>
            </div>
            <div class="text-center" style="max-height: 350px; overflow-y: auto; padding: 0.25rem;">
                <div class="mb-3 p-2 mx-auto" style="background: linear-gradient(135deg, #e7f3ff 0%, #f0f7ff 100%); border-radius: 0.5rem; border-left: 3px solid #696cff; max-width: 500px;">
                    <div class="d-flex align-items-center justify-content-center mb-1">
                        <i class="ti ti-info-circle me-2" style="font-size: 1.2rem; color: #696cff;"></i>
                        <strong style="color: #566a7f; font-size: 0.9rem;">Informação Importante</strong>
                    </div>
                    <p class="mb-0" style="color: #697a8d; font-size: 0.875rem;">
                        O plano <strong style="color: #566a7f;">{{ $plano->nome }}</strong> possui 
                        <strong style="color: #696cff;">{{ $planosContratadosCount }}</strong> contrato(s) ativo(s).
                    </p>
                </div>
                
                @if(count($modulosAdicionados) > 0)
                    <div class="alert alert-success mb-2 mx-auto text-start" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 3px solid #28a745; border-radius: 0.5rem; padding: 0.75rem; max-width: 500px;">
                        <h6 class="alert-heading mb-2" style="color: #155724; font-weight: 600; font-size: 0.875rem;">
                            <i class="ti ti-circle-plus me-1" style="font-size: 1.1rem;"></i>Módulos Adicionados
                        </h6>
                        <ul class="mb-0" style="color: #155724; font-size: 0.8125rem; padding-left: 1.25rem;">
                            @foreach($modulosAdicionados as $idModulo)
                                @php
                                    $modulo = \App\Models\Modulo::find($idModulo);
                                @endphp
                                @if($modulo)
                                    <li style="padding: 0.125rem 0;">
                                        @if($modulo->icone)
                                            <i class="{{ $modulo->icone }} me-1"></i>
                                        @endif
                                        <strong>{{ $modulo->nome }}</strong>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                @if(count($modulosRemovidos) > 0)
                    <div class="alert alert-danger mb-2 mx-auto text-start" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-left: 3px solid #dc3545; border-radius: 0.5rem; padding: 0.75rem; max-width: 500px;">
                        <h6 class="alert-heading mb-2" style="color: #721c24; font-weight: 600; font-size: 0.875rem;">
                            <i class="ti ti-circle-minus me-1" style="font-size: 1.1rem;"></i>Módulos Removidos
                        </h6>
                        <ul class="mb-0" style="color: #721c24; font-size: 0.8125rem; padding-left: 1.25rem;">
                            @foreach($modulosRemovidos as $idModulo)
                                @php
                                    $modulo = \App\Models\Modulo::find($idModulo);
                                @endphp
                                @if($modulo)
                                    <li style="padding: 0.125rem 0;">
                                        @if($modulo->icone)
                                            <i class="{{ $modulo->icone }} me-1"></i>
                                        @endif
                                        <strong>{{ $modulo->nome }}</strong>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <div class="mt-3 p-2 mx-auto" style="background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%); border-radius: 0.5rem; border-left: 3px solid #ffab00; max-width: 500px;">
                    <p class="mb-1 fw-bold" style="color: #566a7f; font-size: 0.9rem;">
                        <i class="ti ti-help-circle me-1" style="color: #ffab00;"></i>Deseja aplicar estas alterações também nos planos contratados?
                    </p>
                    <p class="text-muted small mb-0" style="font-size: 0.75rem; line-height: 1.4;">
                        Se escolher <strong>"Sim"</strong>, todos os {{ $planosContratadosCount }} contrato(s) com o nome 
                        <strong>"{{ $plano->nome }}"</strong> serão atualizados.
                    </p>
                </div>
            </div>
        `,
        icon: null,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="ti ti-check me-1"></i>Sim, aplicar em todos',
        denyButtonText: '<i class="ti ti-file-check me-1"></i>Não, apenas no plano',
        cancelButtonText: '<i class="ti ti-arrow-left me-1"></i>Cancelar',
        customClass: {
            confirmButton: 'btn btn-success me-2',
            denyButton: 'btn btn-primary me-2',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: '580px',
        padding: '1.5rem',
        showClass: {
            popup: 'swal2-show',
            backdrop: 'swal2-backdrop-show'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Aplicar em todos os planos contratados
            submitForm('sim');
        } else if (result.isDenied) {
            // Aplicar apenas no plano
            submitForm('nao');
        } else {
            // Cancelar - voltar para edição
            window.location.href = '{{ route('admin.planos.edit', $plano) }}';
        }
    });
    
    function submitForm(opcao) {
        // Criar formulário e submeter
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('admin.planos.confirm-update') }}';
        
        // CSRF Token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        // Opção escolhida
        const opcaoInput = document.createElement('input');
        opcaoInput.type = 'hidden';
        opcaoInput.name = 'aplicar_planos_contratados';
        opcaoInput.value = opcao;
        form.appendChild(opcaoInput);

        // Token de confirmação (payload armazenado no cache)
        const confirmTokenInput = document.createElement('input');
        confirmTokenInput.type = 'hidden';
        confirmTokenInput.name = 'confirm_token';
        confirmTokenInput.value = '{{ $confirmToken ?? '' }}';
        form.appendChild(confirmTokenInput);
        
        document.body.appendChild(form);
        
        // Mostrar loading com design melhorado
        Swal.fire({
            html: `
                <div style="text-align: center; padding: 1rem;">
                    <i class="ti ti-loader-2" style="font-size: 3rem; color: #696cff; display: block; margin-bottom: 1rem;"></i>
                    <h2 style="font-size: 1.25rem; font-weight: 600; color: #566a7f; margin: 0 0 1rem 0;">Processando...</h2>
                    <p style="color: #697a8d; font-size: 0.9rem; margin-bottom: 1rem;">
                        Aguarde enquanto atualizamos os dados${opcao === 'sim' ? ' do plano e dos contratos' : ' do plano'}.
                    </p>
                    <div class="progress" style="height: 5px; border-radius: 10px; background: #e7e7ff;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" 
                             style="width: 100%; background: linear-gradient(90deg, #696cff 0%, #8e91ff 100%);">
                        </div>
                    </div>
                </div>
            `,
            icon: null,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            width: '400px',
            customClass: {
                popup: 'swal2-show'
            }
        });
        
        form.submit();
    }
});
</script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 d-flex align-items-center justify-content-center" style="min-height: 60vh;">
    <div class="text-center">
        <div class="mb-4">
            <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem; border-width: 0.3rem;">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
        <h4 class="mb-2 text-primary">Processando sua solicitação...</h4>
        <p class="text-muted mb-0">Aguarde um momento enquanto preparamos as informações.</p>
    </div>
</div>
@endsection
