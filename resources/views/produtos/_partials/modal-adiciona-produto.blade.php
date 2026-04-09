<div class="modal fade" id="produtoModal" tabindex="-1" aria-labelledby="produtoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="produtoCreateForm" action="{{ route('produtos.store') }}" method="POST" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center" id="produtoModalLabel">
                        <i class="ti ti-package-import me-2"></i>
                        Novo Produto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    @csrf
                    <div class="row g-3">
                        <!-- Informações Básicas -->
                        <div class="col-12">
                            <h6 class="text-primary mb-2">
                                <i class="ti ti-info-circle me-1"></i>
                                Informações Básicas
                            </h6>
                        </div>

                        <div class="col-md-12">
                            <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input id="nome" name="nome" type="text" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="codigo" class="form-label">Código</label>
                            <input id="codigo" name="codigo" type="text" class="form-control" value="{{ old('codigo') }}">
                        </div>

                        <div class="col-md-6">
                            <label for="numero_serie" class="form-label">Número de Série</label>
                            <input id="numero_serie" name="numero_serie" type="text" class="form-control" value="{{ old('numero_serie') }}">
                        </div>

                        <div class="col-md-12">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea id="descricao" name="descricao" class="form-control" rows="2">{{ old('descricao') }}</textarea>
                        </div>

                        <!-- Preços -->
                        <div class="col-12 mt-3">
                            <h6 class="text-primary mb-2">
                                <i class="ti ti-currency-dollar me-1"></i>
                                Preços
                            </h6>
                        </div>

                        <div class="col-md-4">
                            <label for="preco_custo" class="form-label">Preço de Custo</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input id="preco_custo" name="preco_custo" type="text" class="form-control mask-money" value="{{ old('preco_custo', '0,00') }}">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="preco_venda" class="form-label">Preço de Venda</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input id="preco_venda" name="preco_venda" type="text" class="form-control mask-money" value="{{ old('preco_venda', '0,00') }}">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="preco_locacao" class="form-label">Preço de Locação</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input id="preco_locacao" name="preco_locacao" type="text" class="form-control mask-money" value="{{ old('preco_locacao', '0,00') }}">
                            </div>
                        </div>

                        <!-- Estoque -->
                        <div class="col-12 mt-3">
                            <h6 class="text-primary mb-2">
                                <i class="ti ti-box me-1"></i>
                                Estoque
                            </h6>
                        </div>

                        <div class="col-md-6">
                            <label for="estoque_total" class="form-label">Estoque Total</label>
                            <input id="estoque_total" name="estoque_total" type="number" class="form-control" value="{{ old('estoque_total', 0) }}" min="0">
                        </div>

                        <div class="col-md-6">
                            <label for="quantidade" class="form-label">Quantidade</label>
                            <input id="quantidade" name="quantidade" type="number" class="form-control" value="{{ old('quantidade', 0) }}" min="0">
                        </div>

                        <!-- Status -->
                        <div class="col-md-12">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="ativo" {{ old('status', 'ativo') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                                <option value="inativo" {{ old('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="ti ti-x me-1"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="produtoCreateSubmit">
                        <i class="ti ti-check me-1"></i>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('page-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(document).ready(function() {
    // Máscaras
    $('.mask-money').mask('#.##0,00', {reverse: true});

    // Submit do formulário via AJAX
    $('#produtoCreateForm').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $('#produtoCreateSubmit');
        
        const nome = $form.find('input[name="nome"]').val();
        
        if (!nome) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção!',
                text: 'O nome do produto é obrigatório.',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Salvando...');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: response.message,
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    $submitBtn.prop('disabled', false).html('<i class="ti ti-check me-1"></i>Salvar');
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: response.message || 'Erro ao cadastrar produto.'
                    });
                }
            },
            error: function(xhr) {
                $submitBtn.prop('disabled', false).html('<i class="ti ti-check me-1"></i>Salvar');
                
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    let errorMsg = '';
                    for (const field in errors) {
                        errorMsg += errors[field].join('<br>') + '<br>';
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro de Validação',
                        html: errorMsg
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: xhr.responseJSON?.message || 'Erro ao cadastrar produto.'
                    });
                }
            }
        });
    });

    // Limpar formulário quando modal fechar
    $('#produtoModal').on('hidden.bs.modal', function() {
        $('#produtoCreateForm')[0].reset();
        $('#produtoCreateForm').find('.is-invalid').removeClass('is-invalid');
        $('#produtoCreateSubmit').prop('disabled', false).html('<i class="ti ti-check me-1"></i>Salvar');
    });
});
</script>
@endpush
