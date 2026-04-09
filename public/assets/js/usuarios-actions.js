/**
 * Handlers para ações de usuários (bloquear, desbloquear, deletar, ativar)
 * Gerenciamento de Usuários - Index, Show e Edit
 */
$(document).ready(function() {
    'use strict';

    // Event handler usando delegação (funciona em elementos criados dinamicamente)
    $(document).on('click', '.user-action', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const action = $btn.data('action');
        const userId = $btn.data('id');
        const baseUrl = $btn.data('base-url') || window.location.origin + '/usuarios';

        if (!userId) {
            console.error('✗ ID do usuário não encontrado');
            return false;
        }

        // Configurações para cada ação
        const configs = {
            'block': {
                title: 'Bloquear Usuário',
                text: 'Tem certeza que deseja bloquear este usuário?',
                icon: 'warning',
                confirmText: 'Sim, bloquear',
                confirmColor: '#d33',
                method: 'PUT',
                data: { status: 'bloqueado' }
            },
            'unlock': {
                title: 'Desbloquear Usuário',
                text: 'Tem certeza que deseja desbloquear este usuário?',
                icon: 'question',
                confirmText: 'Sim, desbloquear',
                confirmColor: '#28a745',
                method: 'PUT',
                data: { status: 'ativo' }
            },
            'activate': {
                title: 'Ativar Usuário',
                text: 'Tem certeza que deseja ativar este usuário?',
                icon: 'question',
                confirmText: 'Sim, ativar',
                confirmColor: '#28a745',
                method: 'PUT',
                data: { status: 'ativo' }
            },
            'inactivate': {
                title: 'Inativar Usuário',
                text: 'Tem certeza que deseja inativar este usuário?',
                icon: 'warning',
                confirmText: 'Sim, inativar',
                confirmColor: '#ffc107',
                method: 'PUT',
                data: { status: 'inativo' }
            },
            'delete': {
                title: 'Deletar Usuário',
                text: 'Esta ação não pode ser desfeita. Tem certeza?',
                icon: 'error',
                confirmText: 'Sim, deletar',
                confirmColor: '#d33',
                method: 'DELETE',
                data: {}
            }
        };

        const config = configs[action];
        if (!config) {
            console.error('Ação desconhecida:', action);
            return false;
        }

        // Verificar se SweetAlert2 está disponível
        if (typeof Swal === 'undefined') {
            console.error('✗ SweetAlert2 não carregado!');
            if (confirm(config.text)) {
                executeAction(baseUrl + '/' + userId, config.method, config.data);
            }
            return false;
        }

        // Mostrar modal do SweetAlert2 DIRETO, SEM setTimeout
        Swal.fire({
            title: config.title,
            text: config.text,
            icon: config.icon,
            showCancelButton: true,
            confirmButtonColor: config.confirmColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: config.confirmText,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                console.log('✓ Confirmado! Executando ação...');
                executeAction(baseUrl + '/' + userId, config.method, config.data);
            }
        });

        return false;
    });

    /**
     * Executa a ação via AJAX (sem reload da página)
     */
    function executeAction(url, method, data) {
        console.log('Executando via AJAX:', method, url, data);
        
        // Adiciona o token CSRF
        data._token = $('meta[name="csrf-token"]').attr('content');
        
        // Para PUT/DELETE, adiciona _method
        if (method !== 'POST') {
            data._method = method;
        }

        $.ajax({
            url: url,
            method: 'POST', // Laravel usa POST com _method para PUT/DELETE
            data: data,
            success: function(response) {
                console.log('✓ Sucesso:', response);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: response.message || 'Ação executada com sucesso!',
                    confirmButtonText: 'OK',
                    timer: 2000,
                    timerProgressBar: true
                }).then(() => {
                    // Recarrega a página após fechar o modal
                    window.location.reload();
                });
            },
            error: function(xhr) {
                console.error('✗ Erro:', xhr);
                
                const errorMsg = xhr.responseJSON?.message || 'Erro ao executar a ação';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: errorMsg,
                    confirmButtonText: 'OK'
                });
            }
        });
    }

});
