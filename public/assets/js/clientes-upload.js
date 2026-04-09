// ============================================
// CLIENTES UPLOAD - Imagens e Anexos
// ============================================

// Base URL da API
const API_BASE_URL = 'https://api.gestornow.com';

// Lock para prevenir chamadas duplicadas
let imagensCarregando = false;
let anexosCarregando = false;

function normalizarUrlArquivoCliente(url, filename, empresaId, tipo) {
    if (!url && filename && empresaId && tipo) {
        return `${API_BASE_URL}/uploads/clientes/${tipo}/${empresaId}/${filename}`;
    }

    if (!url) {
        return null;
    }

    let finalUrl = String(url).trim();

    finalUrl = finalUrl
        .replace(/^https\/\//i, 'https://')
        .replace(/^http\/\//i, 'http://')
        .replace(/^https:\/(?!\/)/i, 'https://')
        .replace(/^http:\/(?!\/)/i, 'http://');

    if (/^\/\//.test(finalUrl)) {
        finalUrl = 'https:' + finalUrl;
    }

    if (/^api\.gestornow\.com/i.test(finalUrl)) {
        finalUrl = 'https://' + finalUrl;
    }

    if (!/^https?:\/\//i.test(finalUrl)) {
        finalUrl = API_BASE_URL + '/' + finalUrl.replace(/^\/+/, '');
    }

    finalUrl = finalUrl.replace(/^(https?:\/\/api\.gestornow\.com)(https?:\/\/api\.gestornow\.com)/i, '$1');
    finalUrl = finalUrl.replace('/api/clientes/imagens/', '/uploads/clientes/imagens/');
    finalUrl = finalUrl.replace('/api/clientes/anexos/', '/uploads/clientes/anexos/');

    return finalUrl;
}

// ============================================
// UPLOAD DE IMAGENS
// ============================================

// Habilitar botão de upload quando arquivo for selecionado
$('#fotoUpload').on('change', function() {
    const hasFile = this.files && this.files.length > 0;
    $('#btnUploadFoto').prop('disabled', !hasFile);
    
    // Preview da imagem
    if (hasFile) {
        const file = this.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#fotoPreview').html(
                `<div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); flex-shrink: 0;">
                    <img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                </div>`
            );
        };
        reader.readAsDataURL(file);
    }
});

// Upload de foto
$('#btnUploadFoto').on('click', function() {
    const clienteId = $('#clienteId').val();
    const empresaId = $('#empresaId').val();
    const fileInput = document.getElementById('fotoUpload');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        Swal.fire({icon: 'warning', title: 'Atenção', text: 'Selecione uma imagem primeiro'});
        return;
    }
    
    if (!clienteId) {
        Swal.fire({icon: 'error', title: 'Erro', text: 'ID do cliente não encontrado'});
        return;
    }
    
    const file = fileInput.files[0];
    const nomeOriginal = file.name.split('.')[0];
    
    // Compressão da imagem com Canvas
    compressImage(file, 1920, 1920, 0.85).then(compressedBlob => {
        const formData = new FormData();
        formData.append('file', compressedBlob, file.name);
        formData.append('idEmpresa', empresaId);
        formData.append('idCliente', clienteId);
        formData.append('nomeImagemCliente', nomeOriginal);
        
        const $btn = $('#btnUploadFoto');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Enviando...');
        
        $.ajax({
            url: `${API_BASE_URL}/api/clientes/imagens`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({icon: 'success', title: 'Sucesso!', text: 'Foto enviada com sucesso', timer: 2000, showConfirmButton: false});
                    
                    // Atualizar preview
                    if (response.data && response.data.file && response.data.file.url) {
                        const imageUrl = normalizarUrlArquivoCliente(response.data.file.url, response.data.file.filename, empresaId, 'imagens');
                        $('#fotoPreview').html(
                            `<div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); flex-shrink: 0;">
                                <img src="${imageUrl}" alt="Foto do Cliente" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                            </div>`
                        );
                        
                        // Atualizar campo hidden com filename
                        $('#fotoFilename').val(response.data.file.filename);
                        
                        // Mostrar botão de deletar
                        if ($('#btnDeletarFoto').length === 0) {
                            $('#btnUploadFoto').after(`
                                <button type="button" class="btn btn-sm btn-danger" id="btnDeletarFoto">
                                    <i class="ti ti-trash me-1"></i> Remover Foto
                                </button>
                            `);
                        }
                    }
                    
                    fileInput.value = '';
                }
            },
            error: function(xhr) {
                Swal.fire({icon: 'error', title: 'Erro', text: xhr.responseJSON?.message || 'Erro ao enviar imagem'});
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="ti ti-upload me-1"></i> Upload Foto');
            }
        });
    });
});

// Deletar foto
$(document).on('click', '#btnDeletarFoto', function() {
    const clienteId = $('#clienteId').val();
    const empresaId = $('#empresaId').val();
    const filename = $('#fotoFilename').val();
    
    if (!filename) {
        Swal.fire({icon: 'warning', title: 'Atenção', text: 'Nenhuma foto para deletar'});
        return;
    }
    
    Swal.fire({
        title: 'Tem certeza?',
        text: 'A foto será removida permanentemente',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, deletar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `${API_BASE_URL}/api/clientes/imagens/${empresaId}/${filename}`,
                method: 'DELETE',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({icon: 'success', title: 'Deletado!', text: 'Foto removida com sucesso', timer: 2000, showConfirmButton: false});
                        
                        // Remover preview - pegar primeira letra do nome do cliente
                        const nomeCliente = (
                            $('#clienteNome').val() ||
                            $('meta[name="cliente_nome"]').attr('content') ||
                            $('h4:contains("Editar Cliente")').text().replace('Editar Cliente', '').trim() ||
                            $('h5:contains("Editar Cliente")').text().replace('Editar Cliente:', '').trim()
                        );
                        const inicial = nomeCliente ? nomeCliente.charAt(0).toUpperCase() : 'C';
                        
                        $('#fotoPreview').html(`
                            <div class="avatar avatar-xl">
                                <span class="avatar-initial rounded-circle bg-label-primary fs-1">${inicial}</span>
                            </div>
                        `);
                        
                        $('#fotoFilename').val('');
                        $('#btnDeletarFoto').remove();
                    }
                },
                error: function(xhr) {
                    Swal.fire({icon: 'error', title: 'Erro', text: xhr.responseJSON?.message || 'Erro ao deletar imagem'});
                }
            });
        }
    });
});

// ============================================
// UPLOAD DE ANEXOS
// ============================================

// Habilitar botão de upload quando arquivo for selecionado
$('#anexoUpload').on('change', function() {
    const hasFile = this.files && this.files.length > 0;
    $('#btnUploadAnexo').prop('disabled', !hasFile);
});

// Upload de anexo
$('#btnUploadAnexo').on('click', function() {
    const clienteId = $('#clienteId').val();
    const empresaId = $('#empresaId').val();
    const fileInput = document.getElementById('anexoUpload');
    const nomeAnexo = $('#nomeAnexo').val().trim();
    
    if (!fileInput.files || fileInput.files.length === 0) {
        Swal.fire({icon: 'warning', title: 'Atenção', text: 'Selecione um arquivo primeiro'});
        return;
    }
    
    if (!clienteId) {
        Swal.fire({icon: 'error', title: 'Erro', text: 'ID do cliente não encontrado'});
        return;
    }
    
    if (!nomeAnexo) {
        Swal.fire({icon: 'warning', title: 'Atenção', text: 'Informe o nome do documento'});
        return;
    }
    
    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('file', file);
    formData.append('idEmpresa', empresaId);
    formData.append('idCliente', clienteId);
    formData.append('nomeAnexoCliente', nomeAnexo);
    
    const $btn = $('#btnUploadAnexo');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Enviando...');
    
    $.ajax({
        url: `${API_BASE_URL}/api/clientes/anexos`,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                Swal.fire({icon: 'success', title: 'Sucesso!', text: 'Anexo enviado com sucesso', timer: 2000, showConfirmButton: false});
                
                fileInput.value = '';
                $('#nomeAnexo').val('');
                
                // Carregar lista de anexos
                carregarAnexos();
            }
        },
        error: function(xhr) {
            Swal.fire({icon: 'error', title: 'Erro', text: xhr.responseJSON?.message || 'Erro ao enviar anexo'});
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="ti ti-upload me-1"></i> Upload Anexo');
        }
    });
});

// Carregar lista de anexos
window.carregarAnexos = function() {
    if (anexosCarregando) return;
    
    const clienteId = $('#clienteId').val();
    const empresaId = $('#empresaId').val();
    
    if (!clienteId) return;
    
    anexosCarregando = true;
    
    $('#listaAnexos').html('<p class="text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Carregando anexos...</p>');
    
    $.ajax({
        url: `${API_BASE_URL}/api/clientes/anexos/${empresaId}?idCliente=${clienteId}`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.data.files.length > 0) {
                let html = '<div class="list-group">';
                response.data.files.forEach(file => {
                    const fileUrl = normalizarUrlArquivoCliente(file.url, file.name, empresaId, 'anexos');
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="ti ti-file-text me-2"></i>
                                <a href="${fileUrl}" target="_blank">${file.name}</a>
                                <small class="text-muted d-block">${file.sizeInMB} MB</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger btn-deletar-anexo" data-filename="${file.name}">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                    `;
                });
                html += '</div>';
                $('#listaAnexos').html(html);
            } else {
                $('#listaAnexos').html('<p class="text-muted small">Nenhum anexo encontrado</p>');
            }
        },
        error: function(xhr) {
            $('#listaAnexos').html('<p class="text-danger small">Erro ao carregar anexos</p>');
        },
        complete: function() {
            anexosCarregando = false;
        }
    });
};

// Deletar anexo
$(document).on('click', '.btn-deletar-anexo', function() {
    const filename = $(this).data('filename');
    const empresaId = $('#empresaId').val();
    
    Swal.fire({
        title: 'Tem certeza?',
        text: 'O anexo será removido permanentemente',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, deletar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `${API_BASE_URL}/api/clientes/anexos/${empresaId}/${filename}`,
                method: 'DELETE',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({icon: 'success', title: 'Deletado!', text: 'Anexo removido com sucesso', timer: 2000, showConfirmButton: false});
                        carregarAnexos();
                    }
                },
                error: function(xhr) {
                    Swal.fire({icon: 'error', title: 'Erro', text: xhr.responseJSON?.message || 'Erro ao deletar anexo'});
                }
            });
        }
    });
});

// ============================================
// UTILITÁRIOS
// ============================================

// Função de compressão de imagem
function compressImage(file, maxWidth, maxHeight, quality) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                
                if (width > height) {
                    if (width > maxWidth) {
                        height *= maxWidth / width;
                        width = maxWidth;
                    }
                } else {
                    if (height > maxHeight) {
                        width *= maxHeight / height;
                        height = maxHeight;
                    }
                }
                
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob(resolve, 'image/jpeg', quality);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// ============================================
// CARREGAR FOTO DO CLIENTE AO ABRIR A PÁGINA
// ============================================
function carregarFotoCliente() {
    const clienteId = $('#clienteId').val();
    const empresaId = $('#empresaId').val();
    
    if (!clienteId || !empresaId) {
        return;
    }
    
    $.ajax({
        url: `${API_BASE_URL}/api/clientes/imagens/${empresaId}?idCliente=${clienteId}`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.data.files.length > 0) {
                // Pega o primeiro arquivo (mais recente)
                const file = response.data.files[0];
                const imageUrl = normalizarUrlArquivoCliente(file.url, file.name, empresaId, 'imagens');
                
                // Atualizar preview
                $('#fotoPreview').html(
                    `<div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); flex-shrink: 0;">
                        <img src="${imageUrl}" alt="Foto do Cliente" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                    </div>`
                );
                
                // Atualizar campo hidden com filename
                $('#fotoFilename').val(file.name);
                
                // Mostrar botão de deletar se não existir
                if ($('#btnDeletarFoto').length === 0) {
                    $('#btnUploadFoto').after(`
                        <button type="button" class="btn btn-sm btn-danger" id="btnDeletarFoto">
                            <i class="ti ti-trash me-1"></i> Remover Foto
                        </button>
                    `);
                }
            }
        },
        error: function(xhr) {
            console.log('Erro ao carregar foto do cliente:', xhr);
        }
    });
}

// Carregar foto ao iniciar página
$(document).ready(function() {
    // Aguardar um pouco para garantir que o DOM está pronto
    setTimeout(function() {
        carregarFotoCliente();
    }, 100);
});
