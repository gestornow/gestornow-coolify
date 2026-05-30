## Resumo
Foi implementado o mapeamento de log centralizado do modulo de Clientes seguindo o padrao existente no projeto.
Foi criado o map `ClientesMap`, registrado no `LogMap` e integrado ao model `Cliente` via trait `RegistraAtividade` para captura automatica de `created`, `updated` e `deleted` (soft delete).
Nao foram feitas alteracoes na infraestrutura base de log, nem refatoracoes de regra de negocio.

## Arquivos Criados
| Caminho | Responsabilidade |
| --- | --- |
| `app/ActivityLog/Maps/ClientesMap.php` | Definir entidade, label mascarado, campos sensiveis LGPD e eventos de log do modulo Clientes |
| `CHANGELOG_LOG_CLIENTES.md` | Documentar implementacao, diffs, eventos e decisoes tecnicas |

## Arquivos Modificados
### 1) `app/ActivityLog/LogMap.php`
- Trecho que existia antes:
```php
use App\ActivityLog\Maps\ContasPagarMap;
use App\ActivityLog\Maps\ContasReceberMap;
use App\ActivityLog\Maps\FluxoCaixaMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
    'ContaReceber' => ContasReceberMap::class,
    'FluxoCaixa' => FluxoCaixaMap::class,
];
```
- Trecho adicionado:
```php
use App\ActivityLog\Maps\ClientesMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
    'ContaReceber' => ContasReceberMap::class,
    'Cliente' => ClientesMap::class,
    'FluxoCaixa' => FluxoCaixaMap::class,
];
```
- Motivo da alteracao:
Registrar o novo map de Clientes no resolvedor central para que o Observer encontre as configuracoes de evento da entidade.

### 2) `app/Domain/Cliente/Models/Cliente.php`
- Trecho que existia antes:
```php
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Auth\Models\Empresa;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;
```
- Trecho adicionado:
```php
use Illuminate\Database\Eloquent\SoftDeletes;
use App\ActivityLog\Traits\RegistraAtividade;
use App\Domain\Auth\Models\Empresa;

class Cliente extends Model
{
    use HasFactory, SoftDeletes, RegistraAtividade;
```
- Motivo da alteracao:
Conectar o model Cliente ao `UniversalObserver` pela infraestrutura ja existente, habilitando logs automaticos de CRUD sem alterar regras de negocio.

## Eventos Registrados
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
| --- | --- | --- | --- | --- |
| `created` | `cliente.criado` | `Cadastrou o cliente {nome}` | `clientes`, `cadastro`, `novo_cadastro` | `verde` |
| `updated` | `cliente.editado` | `Editou o cadastro do cliente {nome}` | `clientes`, `cadastro`, `edicao` | `amarelo` |
| `deleted` | `cliente.excluido` | `Excluiu o cliente {nome}` | `clientes`, `cadastro`, `exclusao` | `vermelho` |

## Tratamento LGPD
Campos sensiveis definidos no map:
- `cpf_cnpj`
- `rg_ie`
- `data_nascimento`

Como sao protegidos no log (`antes`/`depois`):
- O `UniversalObserver` aplica mascaramento automatico desses campos com valor padrao do projeto (`[OCULTO]`).
- Assim, esses campos nao ficam expostos em texto puro nos snapshots de alteracao.

Como o label mascara CPF/CNPJ:
- O `label()` do `ClientesMap` monta: `{nome} (***{ultimos4})`.
- Exemplo: `Joao Silva (***4567)`.
- O documento completo nao e exposto no `entidade_label`.

O que aparece e o que nao aparece no registro:
- Aparece: nome do cliente em descricao/label, sufixo mascarado do documento no label, campos nao sensiveis alterados, metadados do evento.
- Nao aparece: CPF/CNPJ completo, RG/IE em texto puro, data de nascimento em texto puro dentro de `antes`/`depois`.

## Decisao sobre Mudanca de Status
Como as mudancas de status sao capturadas:
- No modulo Clientes atual, status (`ativo`, `inativo`, `bloqueado`) e alterado via `update` generico.
- Nao ha endpoint/metodo dedicado de bloqueio, inativacao ou reativacao.

Se geram evento proprio ou ficam no `updated`:
- Ficam cobertas por `cliente.editado` (`updated`) no estado atual.
- Nao foram adicionadas chamadas manuais `ActionLogger::log($cliente, 'bloqueio'|'inativacao'|'reativacao')` para evitar duplicidade de logs.

Justificativa da decisao:
- Segue a regra definida para nao duplicar evento quando status e tratado dentro do update generico.
- Mantem consistencia com a infraestrutura atual sem alterar fluxo funcional.

## Pendencias e Observacoes
- O observer atual mascara campos sensiveis como `[OCULTO]` (padrao tecnico atual do projeto), nao como `***` em `antes`/`depois`.
- O contexto adicional detalhado no `created` (nome, email, telefone, tipo_pessoa, id_filial) nao e injetado pelo map no observer atual; a infraestrutura envia contexto padrao por evento.
- Nao existe fluxo de `restore` para Clientes no modulo atual e o observer nao implementa hook `restored`.
- Validacao funcional completa em ambiente de execucao (clicando nas rotas) nao foi executada nesta etapa; foram realizadas validacoes estaticas e de sintaxe sem erros.
