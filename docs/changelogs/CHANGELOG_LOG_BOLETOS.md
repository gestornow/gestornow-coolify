## Resumo
Foi implementado o mapeamento de log centralizado para o modulo de Boletos no padrao do projeto.
Foi criado o map `BoletosMap`, registrado no `LogMap` e integrado ao model `Boleto` via trait `RegistraAtividade` para captura automatica de `created`, `updated` e `deleted`.
Tambem foram adicionados eventos manuais de negocio no controller para consulta, visualizacao de PDF e alteracao de vencimento.

## Arquivos Criados
| Caminho | Responsabilidade |
| --- | --- |
| `app/ActivityLog/Maps/BoletosMap.php` | Definir entidade, label, valor, campos sensiveis e eventos do modulo Boletos |
| `CHANGELOG_LOG_BOLETOS.md` | Documentar implementacao, eventos e decisoes tecnicas |

## Arquivos Modificados
### 1) `app/ActivityLog/LogMap.php`
- Registro adicionado:
```php
'Boleto' => BoletosMap::class,
```
- Motivo:
Habilitar resolucao central de eventos para o model `Boleto`.

### 2) `app/Models/Boleto.php`
- Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;

class Boleto extends Model
{
    use HasFactory, RegistraAtividade;
```
- Motivo:
Ativar observer universal para logs automaticos de CRUD.

### 3) `app/Http/Controllers/Financeiro/BoletosController.php`
- Trechos adicionados:
```php
use App\ActivityLog\ActionLogger;

ActionLogger::log($boleto->fresh(), 'pdf_visualizado');
ActionLogger::log($boleto->fresh(), 'consulta');
ActionLogger::log($boleto->fresh(), 'vencimento_alterado');
ActionLogger::log($novoBoleto->fresh(), 'vencimento_alterado');
```
- Motivo:
Registrar eventos de negocio nos fluxos de uso do boleto alem do CRUD automatico.

### 4) `app/Http/Controllers/Admin/AdminLogController.php`
- Mapa de modulo atualizado com:
```php
'boleto' => 'Boleto',
```
- Motivo:
Exibir nome amigavel da entidade no painel administrativo de logs.

## Eventos Registrados
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
| --- | --- | --- | --- | --- |
| `created` | `boleto.gerado` | `Gerou boleto #{id}` | `boletos`, `financeiro`, `geracao` | `verde` |
| `updated` | `boleto.atualizado` | `Atualizou boleto #{id}` | `boletos`, `financeiro`, `atualizacao` | `amarelo` |
| `deleted` | `boleto.excluido` | `Excluiu boleto #{id}` | `boletos`, `financeiro`, `exclusao` | `vermelho` |
| `consulta` | `boleto.consultado` | `Consultou situacao do boleto #{id}` | `boletos`, `financeiro`, `consulta` | `azul` |
| `pdf_visualizado` | `boleto.pdf_visualizado` | `Visualizou PDF do boleto #{id}` | `boletos`, `financeiro`, `pdf` | `azul-claro` |
| `vencimento_alterado` | `boleto.vencimento_alterado` | `Alterou vencimento de boleto #{id}` | `boletos`, `financeiro`, `vencimento`, `alteracao` | `laranja` |

## Tratamento de dados sensiveis
Campos sensiveis definidos no map:
- `codigo_barras`
- `linha_digitavel`
- `json_resposta`
- `json_webhook`
- `url_pdf`

Como sao protegidos no log (`antes`/`depois`):
- O `UniversalObserver` aplica mascaramento automatico com valor padrao do projeto (`[OCULTO]`).

## Observacoes
- A geracao do boleto ja dispara log automatico via `created` no model `Boleto`.
- A alteracao de vencimento pode gerar mais de um log de update (mudanca de status/campos) alem do evento manual de negocio, por design.

## Checklist de validacao recomendada
- Gerar boleto e validar `boleto.gerado`.
- Consultar boleto e validar `boleto.consultado`.
- Abrir PDF e validar `boleto.pdf_visualizado`.
- Alterar vencimento e validar `boleto.vencimento_alterado` para boleto antigo e novo.
- Confirmar mascaramento de `linha_digitavel`/`codigo_barras`/JSONs em `antes` e `depois`.
