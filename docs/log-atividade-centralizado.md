# Log de Atividades Centralizado

## Visão geral
Este projeto usa um mecanismo centralizado de auditoria para registrar ações de negócio em `registro_atividades`, com suporte a:
- eventos automáticos de CRUD (`created`, `updated`, `deleted`)
- eventos customizados de domínio (ex.: `baixa`, `estorno`)
- execução assíncrona via fila (`logs`), com fallback síncrono
- mascaramento de campos sensíveis
- contexto multiempresa (`id_empresa`)

## Componentes principais

### 1) Entrada única de log
Arquivo: `app/ActivityLog/ActionLogger.php`

Responsável por:
- receber evento (`log(model, evento)`) ou payload pronto (`logDireto(...)`)
- resolver metadados padrão (empresa, usuário, IP, origem)
- despachar `GravarRegistroAtividadeJob` na fila `logs`
- fallback síncrono se o dispatch falhar

### 2) Contrato de mapeamento
Arquivo: `app/ActivityLog/Contracts/ActivityMap.php`

Define a estrutura que cada módulo deve implementar:
- `entidadeTipo()`
- `tags()`
- `label()`
- `valor()`
- `camposSensiveis()`
- `eventos()`

### 3) Map do módulo
Arquivo: `app/ActivityLog/Maps/ContasPagarMap.php`

Concentra regras de auditoria do módulo:
- ação gravada (`conta_pagar.criada`, `conta_pagar.editada`...)
- descrição legível
- ícone/cor/tags
- campos sensíveis adicionais

### 4) Registro central de maps
Arquivo: `app/ActivityLog/LogMap.php`

Faz o vínculo `Model -> Map` e resolve a configuração por evento.

### 5) Observer universal
Arquivo: `app/ActivityLog/Observers/UniversalObserver.php`

Comportamento:
- `created`: grava estado `depois`
- `updating`: salva snapshot `antes` em cache (`audit_antes_{id}`)
- `updated`: compara mudanças com `getChanges()`, ignora `updated_at/deleted_at`, grava `antes/depois`
- `deleted`: grava estado `antes`
- aplica máscara em campos sensíveis globais

### 6) Trait para ativar auditoria
Arquivo: `app/ActivityLog/Traits/RegistraAtividade.php`

Ao adicionar a trait no model, o `UniversalObserver` passa a ser registrado automaticamente.

### 7) Persistência assíncrona
Arquivo: `app/ActivityLog/Jobs/GravarRegistroAtividadeJob.php`

Responsável por gravar na tabela `registro_atividades`.
- `tries = 3`
- fallback final em `Log::error` no método `failed`

### 8) Model de destino
Arquivo: `app/Models/RegistroAtividade.php`

Model Eloquent da tabela `registro_atividades`, com casts de JSON:
- `contexto`
- `antes`
- `depois`
- `tags`

## Fluxo de execução

### Eventos automáticos (CRUD)
1. Model com trait `RegistraAtividade` dispara observer.
2. Observer consulta `LogMap` para aquele model/evento.
3. Observer monta `antes/depois/contexto` e chama `ActionLogger::logDireto(...)`.
4. `ActionLogger` tenta enfileirar job.
5. Job grava em `registro_atividades`.
6. Se falhar enqueue, `ActionLogger` grava de forma síncrona.

### Eventos customizados
1. Código de negócio conclui operação com sucesso.
2. Chama `ActionLogger::log($model, 'evento_customizado')`.
3. `ActionLogger` consulta map e segue o mesmo fluxo assíncrono/fallback.

## Como aplicar em outros módulos

### Passo 1 — Criar Map do módulo
Criar arquivo em `app/ActivityLog/Maps/` implementando `ActivityMap`.

No `eventos()`, definir ao menos:
- `created`
- `updated`
- `deleted`

E eventos customizados somente se existirem no módulo.

### Passo 2 — Registrar no LogMap
No `app/ActivityLog/LogMap.php`, incluir entrada no array estático de maps.

### Passo 3 — Habilitar observer no Model
No model do módulo:
- importar `RegistraAtividade`
- adicionar trait junto das demais

### Passo 4 — Integrar eventos customizados
Nos pontos de sucesso da operação de negócio:
- `ActionLogger::log($model, 'evento_customizado');`

Boas práticas:
- chamar sempre após sucesso
- se já houver transaction, manter a chamada dentro dela
- não alterar lógica de negócio existente

## Checklist de validação
- CRUD do módulo continua funcionando
- cria registro em `registro_atividades` no create/update/delete
- para update, `antes/depois` contém somente campos alterados
- `id_empresa` preenchido
- campos JSON válidos
- campos sensíveis mascarados (`[OCULTO]`)
- evento customizado aparece com ação correta
- fallback síncrono funciona sem fila

## Consulta rápida no banco
```sql
SELECT id_registro, id_empresa, id_usuario, acao, entidade_tipo, entidade_id, ocorrido_em
FROM registro_atividades
ORDER BY id_registro DESC
LIMIT 50;
```

## Observações operacionais
- A fila padrão do projeto pode estar em `sync`; nesse cenário o processamento ocorre no mesmo request.
- Quando houver worker ativo, o ideal é manter a fila `logs` dedicada para auditoria.
- O sistema foi pensado para extensão incremental por módulo via Map, sem duplicar lógica de logging.
