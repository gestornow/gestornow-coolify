-- ===============================================================
-- Script de correções e adições para o GestorNow
-- Execute este script no servidor de produção
-- ===============================================================

-- ===============================================================
-- 1. CORREÇÕES DE COLUNAS FALTANTES
-- ===============================================================

-- Adicionar coluna 'status' na tabela produtos_terceiros
ALTER TABLE `produtos_terceiros` 
ADD COLUMN IF NOT EXISTS `status` ENUM('ativo', 'inativo') DEFAULT 'ativo';

-- Adicionar coluna 'deleted_at' na tabela produtos_terceiros (soft delete)
ALTER TABLE `produtos_terceiros` 
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL;

-- Adicionar coluna 'deleted_at' na tabela locacao_servicos (soft delete)
ALTER TABLE `locacao_servicos` 
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL;

-- Adicionar coluna 'deleted_at' na tabela locacao_despesas (soft delete)
ALTER TABLE `locacao_despesas` 
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL;

-- Adicionar colunas de timestamps na tabela fluxo_caixa
ALTER TABLE `fluxo_caixa` 
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE `fluxo_caixa` 
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL;


-- ===============================================================
-- 2. ADICIONAR MÓDULO DE MODELOS DE CONTRATO
-- ===============================================================

-- Primeiro, verificar se existe um módulo pai de "Configurações" ou "Locações"
-- Vamos adicionar como submódulo de Locações se existir

-- Verificar o ID do módulo de Locações
SELECT @id_locacoes := id_modulo FROM modulos WHERE LOWER(nome) = 'locações' OR LOWER(nome) = 'locacoes' LIMIT 1;

-- Se não existe módulo pai de Locações, inserir como módulo principal
-- Caso contrário, inserir como submódulo

-- Inserir o módulo de Modelos de Contrato (se não existir)
INSERT INTO `modulos` (`nome`, `id_modulo_pai`, `descricao`, `icone`, `rota`, `ordem`, `categoria`, `ativo`, `tem_submodulos`)
SELECT 'Modelos de Contrato', @id_locacoes, 'Gerenciar templates de contratos para locações', 'ti ti-file-text', 'modelos-contrato', 
       COALESCE((SELECT MAX(ordem) FROM modulos m2 WHERE m2.id_modulo_pai = @id_locacoes), 0) + 1, 
       'Locações', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE rota = 'modelos-contrato');

-- ===============================================================
-- 3. ASSOCIAR MÓDULO A TODOS OS PLANOS ATIVOS
-- ===============================================================

-- Obter o ID do módulo recém-criado
SELECT @id_modulo_contrato := id_modulo FROM modulos WHERE rota = 'modelos-contrato' LIMIT 1;

-- Inserir o módulo em todos os planos ativos (se ainda não estiver)
INSERT INTO `plano_modulos` (`id_plano`, `id_modulo`, `ativo`, `created_at`, `updated_at`)
SELECT p.id_plano, @id_modulo_contrato, 1, NOW(), NOW()
FROM planos p
WHERE p.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM plano_modulos pm 
    WHERE pm.id_plano = p.id_plano AND pm.id_modulo = @id_modulo_contrato
  );

-- ===============================================================
-- 4. ATUALIZAR PERMISSÕES PARA ADMINISTRADORES
-- ===============================================================

-- Inserir permissões para usuários administradores (se a tabela de permissões existir)
-- INSERT INTO `usuario_permissoes` (`id_usuario`, `id_modulo`, `pode_ler`, `pode_criar`, `pode_editar`, `pode_excluir`)
-- SELECT u.id, @id_modulo_contrato, 1, 1, 1, 1
-- FROM users u
-- WHERE u.finalidade = 'administrador'
--   AND NOT EXISTS (
--     SELECT 1 FROM usuario_permissoes up 
--     WHERE up.id_usuario = u.id AND up.id_modulo = @id_modulo_contrato
--   );

-- ===============================================================
-- FIM DO SCRIPT
-- ===============================================================

SELECT 'Script executado com sucesso!' AS resultado;
