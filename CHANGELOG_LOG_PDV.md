## Resumo
Foi implementado o mapeamento de log centralizado para o modulo de PDV (vendas), seguindo o padrao do projeto.
Foi criado o map `PdvVendasMap`, registrado no `LogMap` e integrado ao model `Venda` via trait `RegistraAtividade` para captura automatica de `created`, `updated` e `deleted`.
Tambem foram adicionados eventos de negocio manuais no fluxo do PDV: `finalizacao` e `cancelamento`.

## Arquivos Criados
| Caminho | Responsabilidade |
| --- | --- |
| `app/ActivityLog/Maps/PdvVendasMap.php` | Definir entidade, label, valor, campos sensiveis e eventos de log de PDV |
| `CHANGELOG_LOG_PDV.md` | Documentar implementacao, eventos e decisoes tecnicas |

## Arquivos Modificados
### 1) `app/ActivityLog/LogMap.php`
- Trecho adicionado:
```php
use App\ActivityLog\Maps\PdvVendasMap;

private static array $maps = [
    'Venda' => PdvVendasMap::class,
];
```
- Motivo da alteracao:
Registrar o map de vendas PDV no resolvedor central para habilitar observer e eventos customizados.

### 2) `app/Domain/Venda/Models/Venda.php`
- Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;

class Venda extends Model
{
    use SoftDeletes, RegistraAtividade;
```
- Motivo da alteracao:
Conectar o model de venda ao `UniversalObserver` para logs automaticos de CRUD.

### 3) `app/Http/Controllers/Venda/PDVController.php`
- Trechos adicionados:
```php
use App\ActivityLog\ActionLogger;

ActionLogger::log($venda, 'finalizacao');
ActionLogger::log($venda->fresh(), 'cancelamento');
```
- Motivo da alteracao:
Registrar eventos de negocio do PDV nos pontos de sucesso de finalizar e cancelar venda.

### 4) `app/Http/Controllers/Admin/AdminLogController.php`
- Trecho adicionado no mapa de modulos:
```php
'pdv_venda' => 'PDV Venda',
```
- Motivo da alteracao:
Exibir nome amigavel da entidade no painel administrativo de logs.

## Eventos Registrados
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
| --- | --- | --- | --- | --- |
| `created` | `pdv.venda_criada` | `Criou venda PDV #{numero}` | `pdv`, `vendas`, `novo_cadastro` | `verde` |
| `updated` | `pdv.venda_editada` | `Editou venda PDV #{numero}` | `pdv`, `vendas`, `edicao` | `amarelo` |
| `deleted` | `pdv.venda_excluida` | `Excluiu venda PDV #{numero}` | `pdv`, `vendas`, `exclusao` | `vermelho` |
| `finalizacao` | `pdv.venda_finalizada` | `Finalizou venda PDV #{numero}` | `pdv`, `vendas`, `finalizacao` | `verde-escuro` |
| `cancelamento` | `pdv.venda_cancelada` | `Cancelou venda PDV #{numero}` | `pdv`, `vendas`, `cancelamento` | `vermelho` |

## Tratamento de dados sensiveis
Campos sensiveis definidos no map:
- `observacoes`

Campos sensiveis globais protegidos pelo observer:
- `senha`
- `remember_token`
- `session_token`
- `codigo_reset`
- `google_calendar_token`

Como sao protegidos no log (`antes`/`depois`):
- O `UniversalObserver` aplica mascaramento automatico com valor padrao do projeto (`[OCULTO]`).

## Decisoes tecnicas
- Eventos manuais foram adicionados para refletir operacoes de negocio do PDV, sem remover os logs automaticos de CRUD.
- A entidade de auditoria foi definida como `pdv_venda` para facilitar filtro e leitura no modulo de logs.

## Checklist de validacao recomendada
- Finalizar venda no PDV e verificar `pdv.venda_criada` e `pdv.venda_finalizada`.
- Cancelar venda no PDV e verificar `pdv.venda_editada` e `pdv.venda_cancelada`.
- Validar `entidade_tipo = pdv_venda` e `entidade_id = id_venda`.
- Confirmar `id_empresa` preenchido e mascaramento de `observacoes` nos diffs.
