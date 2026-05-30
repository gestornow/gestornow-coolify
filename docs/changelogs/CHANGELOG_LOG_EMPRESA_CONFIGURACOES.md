# Changelog - Log Empresa e Configuracoes

Data: 2026-03-08
Escopo: Empresa + Configuracoes

## Objetivo
Implementar o mapeamento e a integracao de logs de atividade para eventos de Empresa e Configuracoes, exibindo eventos de negocio no padrao central do ActivityLog.

## Arquivos alterados
- `app/ActivityLog/Maps/EmpresaMap.php` (novo)
- `app/ActivityLog/LogMap.php`
- `app/Domain/Auth/Models/Empresa.php`
- `app/Http/Controllers/Admin/FiliaisController.php`
- `app/Http/Controllers/Configuracao/EmpresaConfiguracaoController.php`
- `app/Http/Controllers/Onboarding/OnboardingController.php`

## Eventos adicionados no mapa `EmpresaMap`
- `created`
- `updated`
- `deleted`
- `status_alterado`
- `bloqueio`
- `desbloqueio`
- `cancelamento`
- `configuracoes_atualizadas`
- `onboarding_dados_atualizados`
- `plano_vinculado_ou_reativado`

## Integracoes aplicadas
1. Registro do map no `LogMap`
- Inclusao de `EmpresaMap` no import e no array de registros (`Empresa` => `EmpresaMap::class`).

2. Observer padrao para Empresa
- Inclusao do trait `RegistraAtividade` no model `Empresa` para capturar `created/updated/deleted` via observer universal.

3. Eventos manuais (acao de negocio)
- `FiliaisController@updateStatus`:
  - `status_alterado`
  - `bloqueio` (quando novo status e bloqueado/teste bloqueado/inativo)
  - `cancelamento` (quando novo status e cancelado)
  - `desbloqueio` (quando sai de bloqueado/teste bloqueado/inativo)
- `EmpresaConfiguracaoController@update`:
  - `configuracoes_atualizadas`
- `OnboardingController@salvarDados`:
  - `onboarding_dados_atualizados`

## Validacao tecnica
- Verificacao de erros de linguagem (Problems): sem erros nos arquivos alterados.
- Confirmacao de correspondencia entre eventos disparados e eventos declarados no `EmpresaMap`.

## Observacoes
- A gravacao em `registro_atividades` permanece via `ActionLogger` com dispatch de job para fila `logs` (com fallback sincrono em caso de falha no dispatch).
- Para visualizacao imediata em ambiente onde a fila nao processa, garantir worker ativo para a fila `logs`.
