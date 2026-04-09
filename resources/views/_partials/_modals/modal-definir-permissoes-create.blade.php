<!-- Modal Definir Permissões - Create -->
<div class="modal fade" id="permissoesModalCreate" tabindex="-1" aria-labelledby="permissoesModalLabelCreate" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="permissoesFormCreate">
                <div class="modal-header">
                    <h5 class="modal-title" id="permissoesModalLabelCreate">
                        <i class="ti ti-lock me-2"></i>
                        Configurar Permissões
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        <i class="ti ti-info-circle me-2"></i>
                        Selecione as permissões que este usuário terá em cada módulo do sistema.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-gradient" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                    <th style="width: 30%; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">Módulo</th>
                                    <th style="width: 17%; text-align: center; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">
                                        <input type="checkbox" class="form-check-input" id="checkAllLerCreate">
                                        <div style="font-size: 0.85rem; margin-top: 4px;">Ler</div>
                                    </th>
                                    <th style="width: 17%; text-align: center; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">
                                        <input type="checkbox" class="form-check-input" id="checkAllCriarCreate">
                                        <div style="font-size: 0.85rem; margin-top: 4px;">Criar</div>
                                    </th>
                                    <th style="width: 17%; text-align: center; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">
                                        <input type="checkbox" class="form-check-input" id="checkAllEditarCreate">
                                        <div style="font-size: 0.85rem; margin-top: 4px;">Editar</div>
                                    </th>
                                    <th style="width: 19%; text-align: center; border-top: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; font-weight: 700; color: #495057; padding: 12px;">
                                        <input type="checkbox" class="form-check-input" id="checkAllDeletarCreate">
                                        <div style="font-size: 0.85rem; margin-top: 4px;">Deletar</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="modulosTableBodyCreate">
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
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i>
                        Salvar Permissões
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
