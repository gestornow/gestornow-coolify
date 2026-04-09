# Atualização de Planos com Confirmação de Módulos

## Funcionalidade Implementada

Quando um plano é editado e há alteração nos módulos (adição ou remoção), o sistema agora:

1. **Detecta as mudanças** nos módulos do plano
2. **Verifica se existem planos contratados** com o mesmo nome
3. **Exibe um SweetAlert** perguntando ao usuário se deseja aplicar as alterações também nos planos contratados
4. **Processa a escolha** do usuário

## Fluxo de Funcionamento

### 1. Edição do Plano
- Usuário acessa a edição de um plano em `/admin/planos/{id}/edit`
- Modifica os módulos (adiciona ou remove)
- Clica em "Atualizar Plano"

### 2. Detecção de Mudanças
O controller `PlanosController::update()` compara:
- **Módulos atuais**: Módulos que estavam ativos antes da edição
- **Módulos novos**: Módulos que foram selecionados no formulário

Se houver diferença E existirem planos contratados com o mesmo nome, o processo segue para confirmação.

### 3. Tela de Confirmação
É exibida a view `admin.planos.confirm-update` que:
- Mostra automaticamente um SweetAlert com:
  - Quantidade de planos contratados afetados
  - Lista de módulos adicionados (em verde)
  - Lista de módulos removidos (em vermelho)
  - 3 opções de ação

### 4. Opções do Usuário

#### Opção 1: "Sim, aplicar em todos"
- Atualiza o plano base
- Aplica as mudanças em **todos os planos contratados** com o mesmo nome
- Módulos adicionados: são inseridos ou reativados nos planos contratados
- Módulos removidos: são desativados (ativo = 0) nos planos contratados

#### Opção 2: "Não, apenas no plano"
- Atualiza **apenas o plano base**
- Planos contratados permanecem inalterados
- Novos contratos criados a partir deste plano usarão a nova configuração

#### Opção 3: "Cancelar"
- Volta para a tela de edição
- Nenhuma alteração é salva

### 5. Processamento
O método `PlanosController::confirmUpdate()`:
- Recupera os dados salvos na sessão
- Cria um novo Request com a escolha do usuário
- Chama `processUpdate()` para salvar as alterações

## Arquivos Modificados

### 1. Controller
**Arquivo**: `app/Http/Controllers/Admin/PlanosController.php`

**Métodos adicionados/modificados**:
- `update()` - Detecta mudanças e redireciona para confirmação se necessário
- `processUpdate()` - Processa a atualização com ou sem aplicação aos planos contratados
- `confirmUpdate()` - Recebe a escolha do usuário e processa

### 2. Rotas
**Arquivo**: `routes/web.php`

**Nova rota adicionada**:
```php
Route::post('planos/confirm-update', [PlanosController::class, 'confirmUpdate'])
    ->name('admin.planos.confirm-update');
```

### 3. View de Confirmação
**Arquivo**: `resources/views/admin/planos/confirm-update.blade.php`

**Características**:
- Exibe SweetAlert automaticamente ao carregar
- Mostra lista de mudanças (adições e remoções)
- 3 botões com cores diferentes:
  - Verde (Confirmar): Aplicar em todos
  - Azul (Deny): Aplicar apenas no plano
  - Cinza (Cancel): Cancelar operação
- Loading durante processamento

## Comportamento Detalhado

### Quando há módulos ADICIONADOS
Para cada plano contratado:
- Verifica se o módulo já existe (pode ter sido removido anteriormente)
- Se existe: reativa (ativo = 1) e atualiza o limite
- Se não existe: cria novo registro em `planos_contratados_modulos`

### Quando há módulos REMOVIDOS
Para cada plano contratado:
- Marca o módulo como inativo (ativo = 0)
- Mantém o registro no banco (soft delete lógico)
- Permite reativar no futuro se necessário

### Dados na Sessão
Durante o processo de confirmação, são armazenados temporariamente na sessão:
- `plano_update_data`: Todos os dados do formulário
- `plano_id`: ID do plano sendo editado

Esses dados são removidos após o processamento.

## Exemplo de Uso

### Cenário 1: Adicionar módulo "Vendas" ao plano "Básico"
1. Plano "Básico" tem 5 contratos ativos
2. Usuário adiciona módulo "Vendas"
3. Sistema pergunta: "Deseja adicionar 'Vendas' nos 5 contratos?"
4. Usuário escolhe "Sim"
5. Módulo "Vendas" é adicionado ao plano e aos 5 contratos

### Cenário 2: Remover módulo "Estoque" do plano "Premium"
1. Plano "Premium" tem 3 contratos ativos
2. Usuário remove módulo "Estoque"
3. Sistema pergunta: "Deseja remover 'Estoque' dos 3 contratos?"
4. Usuário escolhe "Não, apenas no plano"
5. Módulo "Estoque" é removido do plano base
6. Os 3 contratos existentes mantêm o módulo "Estoque"

## Vantagens da Implementação

1. **Controle total**: Administrador decide se quer ou não afetar contratos existentes
2. **Segurança**: Mudanças não são aplicadas automaticamente sem confirmação
3. **Flexibilidade**: Permite manter contratos antigos com configuração diferente
4. **Rastreabilidade**: Logs registram todas as operações
5. **UX Amigável**: SweetAlert com informações claras sobre as mudanças

## Observações Importantes

- A comparação é feita pelo **nome do plano**, não pelo ID
- Módulos removidos não são excluídos, apenas desativados (ativo = 0)
- O sistema mantém histórico de todas as alterações
- Limites dos módulos são preservados ao adicionar/reativar
