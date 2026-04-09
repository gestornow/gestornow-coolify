# Sistema de Sessão Única por Usuário

## Objetivo
Garantir que um mesmo usuário não possa estar logado em dois lugares ao mesmo tempo. Quando o usuário faz login em um novo dispositivo/navegador, a sessão anterior é automaticamente encerrada.

## Implementação

### 1. Middleware: VerifyUniqueSession
**Localização:** `app/Http/Middleware/VerifyUniqueSession.php`

Este middleware verifica em cada requisição se o `session_token` armazenado na sessão do usuário ainda corresponde ao `session_token` armazenado no banco de dados.

**Como funciona:**
- A cada requisição, o middleware compara:
  - `session('session_token')` → Token armazenado na sessão do navegador atual
  - `$user->session_token` → Token armazenado no banco de dados

- Se os tokens forem diferentes, significa que o usuário fez login em outro lugar, e:
  1. O usuário é deslogado automaticamente
  2. A sessão é limpa completamente
  3. Redireciona para a tela de login com mensagem explicativa

### 2. Registro do Middleware
**Localização:** `app/Http/Kernel.php`

O middleware foi adicionado em duas formas:

1. **No grupo 'web'** (linha ~40): Executado automaticamente em todas as rotas web
2. **Como alias** (linha ~68): Disponível como `verify.unique.session` para uso específico em rotas

### 3. Fluxo de Autenticação

#### Login
1. Usuário faz login em `LoginController@login`
2. `SecureAuthService::tentarLogin()` gera um novo `session_token`
3. O token é salvo:
   - No banco de dados (`usuarios.session_token`)
   - Na sessão do Laravel (`session('session_token')`)
   - No cache (se aplicável)

#### Durante o uso
1. A cada requisição, o middleware `VerifyUniqueSession` é executado
2. Compara os tokens (sessão vs. banco de dados)
3. Se diferentes → logout automático com mensagem

#### Novo login em outro dispositivo
1. Quando o usuário faz login em outro lugar:
   - Um NOVO `session_token` é gerado
   - É salvo no banco, substituindo o anterior
2. No dispositivo anterior:
   - Na próxima requisição, o middleware detecta que os tokens são diferentes
   - Usuário é deslogado automaticamente

### 4. Mensagens de Aviso
**Localização:** `resources/views/content/authentications/auth-login-cover.blade.php`

Adicionado suporte para mensagens do tipo `warning`:

```blade
@if (session('warning'))
    <div class="alert alert-warning">
        {{ session('warning') }}
    </div>
@endif
```

**Mensagem exibida quando deslogado:**
> "Sua sessão foi encerrada porque você fez login em outro dispositivo ou navegador."

## Segurança

### Campos na tabela `usuarios`
- `session_token` (VARCHAR 255, NULLABLE): Token único de sessão
- `remember_token` (TEXT, NULLABLE): Token para "lembrar-me"

### Proteções implementadas
1. ✅ Apenas uma sessão ativa por usuário
2. ✅ Logout automático ao detectar novo login
3. ✅ Mensagem clara ao usuário sobre o motivo do logout
4. ✅ Limpeza completa da sessão anterior
5. ✅ Logs detalhados para auditoria

## Testes

### Cenário 1: Login simultâneo
1. Faça login no Chrome
2. Faça login no Firefox (mesmo usuário)
3. Volte ao Chrome e tente navegar
4. ✅ Resultado: Deslogado automaticamente com mensagem

### Cenário 2: Login após logout
1. Faça login normalmente
2. Faça logout
3. Faça login novamente
4. ✅ Resultado: Login funciona normalmente

### Cenário 3: Sessão expirada
1. Faça login
2. Aguarde a sessão expirar
3. Tente navegar
4. ✅ Resultado: Redirecionado para login

## Logs

Todos os eventos são registrados em `storage/logs/laravel.log`:

```php
[INFO] VerifyUniqueSession - Verificando sessão única
[WARNING] VerifyUniqueSession - Sessão invalidada: usuário logou em outro lugar
```

## Observações Importantes

1. **Não afeta logout manual:** O usuário ainda pode fazer logout normalmente
2. **Não afeta "lembrar-me":** A funcionalidade de lembrar login continua funcionando
3. **Performance:** Verificação é rápida (apenas comparação de strings)
4. **Cache:** O sistema continua usando cache para validação de sessão

## Manutenção

### Para desativar temporariamente
Remova ou comente a linha no `app/Http/Kernel.php`:
```php
\App\Http\Middleware\VerifyUniqueSession::class,
```

### Para ajustar a mensagem
Edite o arquivo `app/Http/Middleware/VerifyUniqueSession.php`, linha ~36:
```php
->with('warning', 'Sua mensagem personalizada aqui');
```
