{{-- Script para exclusão de conta --}}
<script>
function excluirConta(id, descricao) {
    Swal.fire({
        title: 'Confirmar exclusão',
        text: `Deseja realmente excluir "${descricao}"?`,
        icon: 'warning',
        showCancelButton: true,
        showDenyButton: false,
        showCloseButton: false,
        confirmButtonColor: '#696cff',
        cancelButtonColor: '#8592a3',
        confirmButtonText: '<i class="ti ti-check me-1"></i>Sim, excluir!',
        cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
        customClass: {
            confirmButton: 'btn btn-primary me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ $deleteRoute ?? '/financeiro/contas-a-pagar' }}/${id}`;
            
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);
            
            const methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
