-- ==================================================
-- Tabela de Histórico de Pagamentos - Contas a Pagar
-- ==================================================
CREATE TABLE IF NOT EXISTS `pagamentos_contas_pagar` (
  `id_pagamento` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_conta_pagar` BIGINT(20) NOT NULL,
  `id_empresa` BIGINT(20) UNSIGNED NOT NULL,
  `data_pagamento` DATE NOT NULL,
  `valor_pago` DECIMAL(10,2) NOT NULL,
  `id_forma_pagamento` BIGINT(20) NULL DEFAULT NULL,
  `id_bancos` BIGINT(20) NULL DEFAULT NULL,
  `observacoes` TEXT NULL DEFAULT NULL,
  `id_usuario` BIGINT(20) UNSIGNED NULL DEFAULT NULL COMMENT 'Usuário que registrou o pagamento',
  `id_fluxo_caixa` BIGINT(20) UNSIGNED NULL DEFAULT NULL COMMENT 'Referência ao fluxo de caixa',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_pagamento`) USING BTREE,
  INDEX `idx_conta_pagar` (`id_conta_pagar`) USING BTREE,
  INDEX `idx_empresa` (`id_empresa`) USING BTREE,
  INDEX `idx_data_pagamento` (`data_pagamento`) USING BTREE,
  INDEX `idx_forma_pagamento` (`id_forma_pagamento`) USING BTREE,
  INDEX `idx_bancos` (`id_bancos`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de pagamentos parciais das contas a pagar';


