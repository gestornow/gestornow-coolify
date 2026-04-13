# Arquitetura e organizacao do codigo

## Estruturas e padroes encontrados

- `app/Domain/<Contexto>` ja existe e concentra parte relevante do dominio em `Models`, `Services`, `Repositories` e `Providers`.
- `app/Http` continua como camada de entrada, com `Controllers`, `Requests`, `Resources`, `Middleware` e `Traits`.
- `resources/views/<modulo>` segue uma organizacao funcional razoavel por contexto de tela, como `usuario`, `financeiro`, `locacoes`, `produtos` e `billing`.
- `app/Services` ainda concentra tanto servicos de negocio quanto infraestrutura, convivendo em paralelo com `app/Domain`.
- `app/Models` segue ativo com modelos fora do dominio modular, o que hoje duplica o padrao de organizacao dos Eloquent models.
- `routes/web.php` concentra rotas de negocio, autenticação, billing, onboarding e tambem blocos grandes herdados do template Vuexy.
- `postfixadmin/` e uma segunda aplicacao dentro do mesmo repositorio, com bootstrap, dependencias e testes proprios.
- O webhook de deploy existe em dois fluxos: [routes/api.php](routes/api.php#L27) via controller Laravel e [public/webhook.php](public/webhook.php#L1) como endpoint legado direto.

## Problemas estruturais atuais

- A aplicacao esta em modo hibrido: parte do negocio ja e modular por dominio, mas outra parte ainda cresce em `app/Services`, `app/Models` e controllers muito grandes.
- Existe superficie publica desnecessaria herdada de template em [routes/web.php](routes/web.php), com rotas de `layouts`, `apps`, `authentications`, `wizard_example`, `user_interface` e `extended_ui` misturadas ao produto real.
- Ha inconsistencias de naming e namespace, como `Auth` e `auth`, `Usuario` e `usuario`, o que aumenta atrito em autoload, busca e manutencao.
- O repositorio mistura codigo da aplicacao, scripts operacionais, documentacao, artefatos de runtime e uma aplicacao terceirizada, sem fronteiras explicitas.
- O README atual ainda e o boilerplate do Laravel e nao ajuda a orientar estrutura, bootstrap local ou convencoes do projeto.
- A cobertura de testes do app principal ainda e pequena e ainda existem `ExampleTest` genericos no diretório `tests`.

## Padrao arquitetural a adotar

### 1. Dominio primeiro, sem reescrever tudo de uma vez

- Todo codigo novo de regra de negocio deve nascer em `app/Domain/<Contexto>`.
- Cada contexto deve concentrar, quando fizer sentido, `Models`, `Services`, `Repositories`, `DTOs`, `Actions` e `Policies` no proprio modulo.
- `app/Services` passa a ser somente area de transicao e de servicos transversais ou de infraestrutura. Nao devem entrar novos servicos de negocio ali.
- `app/Models` entra em congelamento: nenhum model novo deve ser criado nesse diretório. Model tocado em refactor relevante deve ser migrado para o contexto correto em `app/Domain`.

### 2. HTTP como camada fina

- Controllers devem orquestrar request, autorizacao, chamada de servico e resposta.
- Validacao deve preferencialmente ficar em `app/Http/Requests/<Contexto>`.
- Regras de negocio, consultas complexas e efeitos colaterais nao devem crescer em controller.
- Controllers muito grandes devem ser quebrados por caso de uso antes de qualquer reorganizacao cosmetica de pasta.

### 3. Views por modulo funcional

- Views de produto continuam em `resources/views/<modulo>`.
- Conteudo herdado de template deve ficar isolado e sair gradualmente do fluxo principal.
- Nenhuma tela nova de negocio deve nascer em `resources/views/content` se ja existir um modulo funcional equivalente.

### 4. Rotas por responsabilidade

- `routes/web.php` deve virar apenas ponto de agregacao.
- A migracao recomendada e criar arquivos por dominio em `routes/web/`, por exemplo `auth.php`, `billing.php`, `usuarios.php`, `financeiro.php`, `locacoes.php`.
- O mesmo vale para `routes/api.php`, separando webhooks, auth mobile e APIs internas.
- Rotas de template ou demo devem ser removidas ou isoladas atras de flag de ambiente antes de qualquer limpeza de controllers/views associados.

### 5. Operacao e runtime fora da raiz do projeto

- Logs, dumps, arquivos temporarios e saidas operacionais devem ficar apenas em `storage/` ou em volume externo do container.
- Nada de segredo hardcoded em arquivo publico.
- O endpoint oficial de webhook deve ser o controller Laravel; o arquivo publico legado deve existir apenas como compatibilidade temporaria.

## Backlog de organizacao recomendado

### Fase 1. Seguranca e exposicao

- Consolidar o webhook GitHub em um unico fluxo e remover a dependencia operacional de [public/webhook.php](public/webhook.php#L1) quando a infraestrutura estiver apontando para [routes/api.php](routes/api.php#L27).
- Auditar uploads salvos em `public/assets/*` e migrar o que for arquivo dinamico para `storage/app/public` quando possivel.
- Remover artefatos de runtime da raiz do repositorio e garantir ignore para logs operacionais.

### Fase 2. Congelamento de legado estrutural

- Parar de criar classes novas em `app/Models`.
- Parar de criar servicos de negocio em `app/Services`.
- Definir `app/Domain/<Contexto>` como unico destino para novas regras de negocio.

### Fase 3. Quebra por modulo

- Priorizar os contextos com maior volume e risco: `Auth`, `Usuario`, `Financeiro`, `Locacao`, `Billing`.
- Em cada contexto, mapear controller, request, service, model, policy, job, view e testes antes de mover arquivos.
- Refatorar primeiro classes muito centrais e muito grandes, nao arquivos pequenos e estaveis.

### Fase 4. Limpeza de superficie herdada

- Revisar e remover rotas e controllers de demo/template ainda expostos em [routes/web.php](routes/web.php).
- Revisar `resources/views/content`, `app/Http/Controllers/apps`, `app/Http/Controllers/authentications`, `app/Http/Controllers/wizard_example`, `app/Http/Controllers/user_interface` e `app/Http/Controllers/extended_ui`.
- Substituir o README genérico por documentacao real de onboarding tecnico.

### Fase 5. Separacao de fronteiras de repositorio

- Avaliar se `postfixadmin/` deve permanecer no mesmo repositorio. O ideal arquitetural e repositorio separado, submodule ou pipeline independente.
- Se continuar no mesmo repo, documentar explicitamente que ele e um sistema acoplado por infraestrutura e nao parte do dominio principal do Laravel.

### Fase 6. Testes que sustentam a reorganizacao

- Remover `ExampleTest` quando houver cobertura minima real.
- Cobrir primeiro autenticacao, webhook, billing e fluxos criticos de financeiro/locacao.
- Nenhum refactor estrutural grande deve avancar sem pelo menos testes de smoke dos modulos tocados.

## Candidatos imediatos para revisao manual

- [routes/web.php](routes/web.php)
- [app/Models](app/Models)
- [app/Services](app/Services)
- [app/Http/Controllers](app/Http/Controllers)
- [resources/views/content](resources/views/content)
- [README.md](README.md)
- [postfixadmin](postfixadmin)

## Regra pratica para novas contribuicoes

- Se for regra de negocio: `app/Domain/<Contexto>`.
- Se for entrada HTTP: `app/Http`.
- Se for tela: `resources/views/<modulo>`.
- Se for arquivo de runtime: `storage/`.
- Se for demo, experimento ou template: nao misturar com rotas principais do produto.