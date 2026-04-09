<!-- Modal Definir Permissões -->
<div class="modal fade" id="permissoesModal" tabindex="-1" aria-labelledby="permissoesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="permissoesForm" action="{{ route('usuarios.salvar-permissoes') }}" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="permissoesModalLabel">
                        <i class="ti ti-lock me-2"></i>
                        Definir Permissões
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    @csrf
                    <input type="hidden" id="usuarioIdPermissao" name="id_usuario" value="">

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-gradient" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                    <th style="width: 30%; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">Módulo</th>
                                    <th style="width: 17%; text-align: center; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">
                                        <input type="checkbox" class="form-check-input" id="checkAllLer">
                                        <div style="font-size: 0.85rem; margin-top: 4px;">Ler</div>
                                    </th>
                                    <th style="width: 17%; text-align: center; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">
                                        <input type="checkbox" class="form-check-input" id="checkAllCriar">
                                        <div style="font-size: 0.85rem; margin-top: 4px;">Criar</div>
                                    </th>
                                    <th style="width: 17%; text-align: center; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">
                                        <input type="checkbox" class="form-check-input" id="checkAllEditar">
                                        <div style="font-size: 0.85rem; margin-top: 4px;">Editar</div>
                                    </th>
                                    <th style="width: 19%; text-align: center; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">
                                        <input type="checkbox" class="form-check-input" id="checkAllDeletar">
                                        <div style="font-size: 0.85rem; margin-top: 4px;">Deletar</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="modulosTableBody">
                                <!-- Preenchido via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="ti ti-x me-1"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="permissoesSubmit">
                        <i class="ti ti-check me-1"></i>
                        Salvar Permissões
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


