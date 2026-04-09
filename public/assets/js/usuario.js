(function($){
    
    function loadStats() {
        $.ajax({
            url: '/admin/users/stats', // Ajuste a rota conforme necessário
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const stats = response.data;
                    $('#total-users').text(stats.total || 0);
                    $('#active-users').text(stats.ativos || 0);
                    $('#inactive-users').text(stats.inativos || 0);
                    $('#blocked-users').text(stats.bloqueados || 0);
                }
            },
            error: function() {
                console.error('Erro ao carregar estatísticas');
            }
        });
    }

    // Função para bloquear usuário
    window.blockUser = function(id){
        Swal.fire({
            title: 'Bloquear Usuário',
            input: 'textarea',
            inputLabel: 'Motivo do bloqueio',
            inputPlaceholder: 'Descreva o motivo do bloqueio...',
            inputAttributes: { 
                'aria-label': 'Motivo do bloqueio', 
                'required': true 
            },
            showCancelButton: true,
            confirmButtonText: 'Bloquear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            preConfirm: (motivo) => {
                if (!motivo || motivo.trim() === '') { 
                    Swal.showValidationMessage('Por favor, informe o motivo'); 
                    return false; 
                }
                return motivo;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/usuarios/${id}/block`,
                    method: 'POST',
                    data: { 
                        motivo: result.value, 
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response){ 
                        if (response.success){ 
                            Swal.fire({
                                title: 'Bloqueado!', 
                                text: response.message || 'Usuário bloqueado com sucesso',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                // Recarrega a página para atualizar a listagem
                                window.location.reload();
                            });
                        }
                    },
                    error: function(xhr){ 
                        Swal.fire(
                            'Erro!', 
                            xhr.responseJSON?.message || 'Erro ao bloquear usuário', 
                            'error'
                        ); 
                    }
                });
            }
        });
    };

    // Função para desbloquear usuário
    window.unlockUser = function(id){
        Swal.fire({
            title: 'Desbloquear Usuário?',
            text: 'O usuário poderá acessar o sistema novamente',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, desbloquear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed){
                $.ajax({ 
                    url: `/usuarios/${id}/unlock`, 
                    method: 'POST', 
                    data: { 
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }, 
                    success: function(response){ 
                        if (response.success){ 
                            Swal.fire({
                                title: 'Desbloqueado!', 
                                text: response.message || 'Usuário desbloqueado com sucesso',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } 
                    }, 
                    error: function(xhr){ 
                        Swal.fire(
                            'Erro!', 
                            xhr.responseJSON?.message || 'Erro ao desbloquear usuário', 
                            'error'
                        ); 
                    } 
                });
            }
        });
    };

    // Função para deletar usuário
    window.deleteUser = function(id){
        Swal.fire({
            title: 'Deletar Usuário?',
            text: 'Esta ação não pode ser revertida!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, deletar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed){
                $.ajax({ 
                    url: `/usuarios/${id}`, 
                    method: 'DELETE', 
                    data: { 
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }, 
                    success: function(response){ 
                        if (response.success){ 
                            Swal.fire({
                                title: 'Deletado!', 
                                text: response.message || 'Usuário deletado com sucesso',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } 
                    }, 
                    error: function(xhr){ 
                        Swal.fire(
                            'Erro!', 
                            xhr.responseJSON?.message || 'Erro ao deletar usuário', 
                            'error'
                        ); 
                    } 
                });
            }
        });
    };

    // Função para abrir modal de criação
    window.openUserCreateModal = function(){
        $('#userModal').modal('show');
    };

    // Submit do formulário de criação
    $(document).on('submit', '#userCreateForm', function(e){
        e.preventDefault();
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        // Desabilita botão para evitar múltiplos cliques
        $submitBtn.prop('disabled', true);
        
        $.ajax({
            url: $form.attr('action') || '/usuarios',
            method: 'POST',
            data: $form.serialize(),
            success: function(res){
                // Se existir modal bootstrap, escondê-lo (criacao via modal)
                try { $('#userModal').modal('hide'); } catch(e){}

                // Mostrar SweetAlert modal de sucesso (normal, não toast)
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: res.message || 'Usuário salvo com sucesso',
                    confirmButtonText: 'OK'
                }).then(function() {
                    // Redireciona para a listagem de usuários
                    window.location.href = '/usuarios';
                });
            },
            error: function(xhr){
                $submitBtn.prop('disabled', false);
                const json = xhr.responseJSON || {};
                const msg = json.message || 'Erro ao salvar usuário';
                
                Swal.fire('Erro', msg, 'error');
                
                // Exibe erros de validação
                if (json.errors) {
                    let errorHtml = '<ul class="text-start">';
                    $.each(json.errors, function(field, messages) {
                        $.each(messages, function(i, message) {
                            errorHtml += '<li>' + message + '</li>';
                        });
                    });
                    errorHtml += '</ul>';
                    
                    Swal.fire({
                        title: 'Erros de Validação',
                        html: errorHtml,
                        icon: 'error'
                    });
                }
            }
        });
    });

    // Limpa formulário ao fechar modal
    $('#userModal').on('hidden.bs.modal', function () {
        $('#userCreateForm')[0].reset();
        $('#userCreateForm').find('.is-invalid').removeClass('is-invalid');
        $('#userCreateForm').find('.invalid-feedback').remove();
    });

    // Inicialização
    $(function(){
        loadStats();
        
        // Atualiza stats a cada 30 segundos
        setInterval(loadStats, 30000);
    });

})(jQuery);