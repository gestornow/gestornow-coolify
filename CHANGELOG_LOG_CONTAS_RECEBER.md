## Resumo
Foi implementado o map de log centralizado para o módulo de Contas a Receber, com suporte a eventos CRUD e eventos customizados de recebimento (baixa total, baixa parcial e estorno).
Também foi registrada a entidade no LogMap e integrada a chamada de log nos pontos reais de execução do fluxo de baixa/estorno, sem alterar regras de negócio existentes.
A captura automática de created/updated/deleted foi habilitada via trait no model, seguindo o padrão já usado em Contas a Pagar.

## Arquivos Criados
| Caminho | Responsabilidade |
|---|---|
| app/ActivityLog/Maps/ContasReceberMap.php | Define entidade, tags, label, valor, campos sensíveis e mapeamento de eventos de Contas a Receber |
| CHANGELOG_LOG_CONTAS_RECEBER.md | Documentação da implementação do log no módulo de Contas a Receber |

## Arquivos Modificados

### 1) app/ActivityLog/LogMap.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/app/ActivityLog/LogMap.php
- Trecho que existia antes:
```php
use App\ActivityLog\Maps\ContasPagarMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
];

if ($nomeModelo === 'ContasAPagar' && isset(self::$maps['ContaPagar'])) {
    return self::$maps['ContaPagar'];
}
```
- Trecho adicionado:
```php
use App\ActivityLog\Maps\ContasReceberMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
    'ContaReceber' => ContasReceberMap::class,
];

if ($nomeModelo === 'ContasAReceber' && isset(self::$maps['ContaReceber'])) {
    return self::$maps['ContaReceber'];
}
```
- Motivo da alteração:
Registrar o novo map e permitir resolução do model ContasAReceber para o alias ContaReceber, no mesmo padrão já adotado para ContasAPagar/ContaPagar.

### 2) app/Models/ContasAReceber.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/app/Models/ContasAReceber.php
- Trecho que existia antes:
```php
class ContasAReceber extends Model
{
    use HasFactory, SoftDeletes;
```
- Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;

class ContasAReceber extends Model
{
    use HasFactory, SoftDeletes, RegistraAtividade;
```
- Motivo da alteração:
Habilitar captura automática dos eventos CRUD (created, updated, deleted) via infraestrutura centralizada já existente.

### 3) app/Http/Controllers/Financeiro/ContasAReceberController.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/app/Http/Controllers/Financeiro/ContasAReceberController.php
- Trecho que existia antes:
```php
// darBaixa: registrava recebimento, dava refresh e retornava JSON
$recebimento = $this->contasAReceberService->registrarRecebimento(...);
$conta->refresh();

// removerBaixa: reabria conta e commitava sem log custom
$conta->update([...]);
DB::commit();

// excluirRecebimento: excluía via service e fazia refresh sem log custom
$this->contasAReceberService->excluirRecebimento($recebimento);
$conta->refresh();
```
- Trecho adicionado:
```php
use App\ActivityLog\ActionLogger;
use Illuminate\Support\Facades\Cache;

// darBaixa: cálculo de contexto + logDireto diferenciando total/parcial
Cache::put('audit_silenciar_updated_' . class_basename($conta) . '_' . $conta->getKey(), true, now()->addSeconds(20));
...
ActionLogger::logDireto(
    model: $conta,
    evento: $ehBaixaTotal ? 'baixa' : 'baixa_parcial',
    acao: $ehBaixaTotal ? 'conta_receber.baixa' : 'conta_receber.baixa_parcial',
    ...
    cor: $ehBaixaTotal ? 'verde-escuro' : 'ciano',
    tags: $ehBaixaTotal
        ? ['contas_receber', 'financeiro', 'recebimento']
        : ['contas_receber', 'financeiro', 'recebimento', 'parcial']
);

// removerBaixa
ActionLogger::log($conta, 'estorno');

// excluirRecebimento
ActionLogger::log($conta, 'estorno');
```
- Motivo da alteração:
Registrar eventos customizados no ponto real onde as operações ocorrem com sucesso, mantendo as transações e sem refatorar lógica existente.

## Eventos Registrados
| Evento | Ação gravada | Descrição gerada | Tags | Cor |
|---|---|---|---|---|
| created | conta_receber.criada | Criou a conta a receber #ID — DESCRIÇÃO | contas_receber, financeiro, cadastro | verde |
| updated | conta_receber.editada | Editou a conta a receber #ID — DESCRIÇÃO | contas_receber, financeiro, edicao | amarelo |
| deleted | conta_receber.excluida | Excluiu a conta a receber #ID — DESCRIÇÃO | contas_receber, financeiro, exclusao | vermelho |
| baixa | conta_receber.baixa | Baixa total na conta #ID — Recebido: R$ X,XX | contas_receber, financeiro, recebimento | verde-escuro |
| baixa_parcial | conta_receber.baixa_parcial | Baixa parcial na conta #ID — Recebido: R$ X,XX — Saldo: R$ Y,YY (com parcela quando existir) | contas_receber, financeiro, recebimento, parcial | ciano |
| estorno | conta_receber.estorno | Estornou recebimento da conta a receber #ID — DESCRIÇÃO | contas_receber, financeiro, estorno | laranja |

## Detalhamento da Baixa Parcial
- Quais campos do model são usados:
  - id_contas
  - descricao
  - valor_total
  - valor_pago
  - numero_parcela
  - total_parcelas
  - status (derivado na composição de antes/depois)

- O que aparece no campo `descricao`:
  - Formato: `Baixa parcial na conta #ID — Recebido: R$ X,XX — Saldo: R$ Y,YY`
  - Se houver parcelamento: sufixo `— Parcela N/T`.

- O que vai no campo `contexto`:
  - evento (baixa_parcial)
  - tipo_baixa (parcial)
  - id_recebimento
  - data_pagamento
  - valor_recebido_parcela
  - valor_pago_antes / valor_pago_depois
  - saldo_restante_antes / saldo_restante_depois
  - forma_pagamento
  - banco
  - observacoes
  - numero_parcela / total_parcelas

- O que vai nos campos `antes` e `depois`:
  - antes:
    - valor_pago
    - saldo_restante
    - status (`pendente` ou `parcialmente_recebido`)
  - depois:
    - valor_pago
    - saldo_restante
    - status (`parcialmente_recebido` ou `pago` conforme quitação)

## Pendências e Observações
- Não foi identificado fluxo ativo de cancelamento específico de Contas a Receber (embora exista método `cancelar` no service); portanto o evento `cancelamento` não foi integrado para evitar log de operação inexistente no fluxo atual.
- A validação de problemas de código (diagnostics) dos arquivos alterados retornou sem erros.
- A execução de validação funcional (requisições HTTP reais e conferência em `registro_atividades`) depende de ambiente de execução da aplicação e base de dados disponíveis no momento do teste manual/integrado.
- O padrão de diferenciação entre baixa total e baixa parcial foi replicado conforme referência do módulo de Contas a Pagar.

## Atualização — Modal de Log de Atividades (Contas a Receber)

### Objetivo
Replicar em Contas a Receber o mesmo processo de visualização de logs já existente em Contas a Pagar, via modal (SweetAlert), consumindo endpoint dedicado e exibindo antes/depois/contexto.

### Arquivos adicionais modificados

#### 4) routes/web.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/routes/web.php
- Trecho adicionado:
```php
Route::get('contas-a-receber/{id}/logs-atividades', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'logsAtividades'])->name('contas-a-receber.logs-atividades');
```
- Motivo da alteração:
Disponibilizar endpoint para o frontend buscar os logs da conta selecionada.

#### 5) app/Http/Controllers/Financeiro/ContasAReceberController.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/app/Http/Controllers/Financeiro/ContasAReceberController.php
- Trecho adicionado (resumo):
```php
use App\Models\RegistroAtividade;

public function logsAtividades(string $id)
{
  // valida empresa da conta
  // busca em registro_atividades por entidade_tipo = conta_receber
  // ordena por ocorrido_em desc, limit 50
  // retorna JSON com conta e logs
}
```
- Motivo da alteração:
Fornecer os dados para o modal de log no mesmo padrão usado em Contas a Pagar.

#### 6) resources/views/financeiro/contas-a-receber/index.blade.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/resources/views/financeiro/contas-a-receber/index.blade.php
- Trecho adicionado:
```php
<a class="dropdown-item" href="javascript:void(0)" onclick="verLogAtividadesConta({{ $conta->id_contas }}, '{{ $conta->descricao }}')">
  <i class="ti ti-activity me-2"></i>Log de Atividades
</a>
```
- Motivo da alteração:
Expor a ação de abertura do modal de logs na listagem de contas a receber.

#### 7) public/assets/js/financeiro/contas-a-receber.js
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/public/assets/js/financeiro/contas-a-receber.js
- Trecho adicionado (resumo):
```javascript
function verLogAtividadesConta(idConta, descricao) { ... }
function mostrarModalLogAtividades(descricao, logs) { ... }
function gerarHtmlLogAtividades(descricao, logs) { ... }
function normalizarCorLog(cor) { ... }
function formatarObjetoDetalhado(obj) { ... }
function gerarResumoContextoLog(contexto) { ... }
window.verLogAtividadesConta = verLogAtividadesConta;
```
- Motivo da alteração:
Renderizar o modal de log com visual e comportamento equivalentes ao de Contas a Pagar.

### Observação de compatibilidade visual
- O mapeamento de cores do modal inclui cores adicionais usadas em Contas a Receber (`verde-escuro`, `ciano`, `vermelho-escuro`) para manter consistência de exibição.
