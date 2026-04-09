# Asaas + Webhook + Scheduler

Guia rapido para fechar os 3 pontos em producao.

## 1) Configurar `ASAAS_API_KEY` e `ASAAS_BASE_URL` no `.env`

No servidor, no arquivo `.env`, adicione:

```env
ASAAS_API_KEY=coloque_sua_api_key_aqui
ASAAS_BASE_URL=https://api.asaas.com
ASAAS_WEBHOOK_TOKEN=defina_um_token_forte_opcional
```

Observacoes:
- Em sandbox, use `ASAAS_BASE_URL=https://sandbox.asaas.com/api`.
- `ASAAS_WEBHOOK_TOKEN` e opcional, mas recomendado para validar origem do webhook.

Depois, aplique:

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

## 2) Apontar webhook Asaas para assinatura

Endpoint da aplicacao:

```text
POST https://gestornow.com/api/webhooks/assinaturas/asaas
```

No painel Asaas:
- URL: `https://gestornow.com/api/webhooks/assinaturas/asaas`
- Metodo: `POST`
- Content-Type: `application/json`
- Token (se configurado): usar o mesmo valor de `ASAAS_WEBHOOK_TOKEN`

Eventos recomendados:
- `PAYMENT_CONFIRMED`
- `PAYMENT_RECEIVED`
- `PAYMENT_RECEIVED_IN_CASH_UNDONE`
- `PAYMENT_OVERDUE`
- `PAYMENT_DELETED`
- `PAYMENT_REFUNDED`
- `PAYMENT_CHARGEBACK_REQUESTED`

Teste rapido (exemplo):

```bash
curl -X POST "https://gestornow.com/api/webhooks/assinaturas/asaas" \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: SEU_TOKEN_WEBHOOK" \
  -d '{"event":"PAYMENT_RECEIVED","payment":{"id":"pay_test_123","status":"RECEIVED"}}'
```

## 3) Garantir scheduler ativo (`schedule:run` a cada minuto)

### Linux (cron)

Edite o crontab do usuario que roda a app (`crontab -e`):

```cron
* * * * * cd /caminho/para/gestornow-2.0 && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Se usa Sail/Docker:

```cron
* * * * * cd /caminho/para/gestornow-2.0 && ./vendor/bin/sail artisan schedule:run >> /dev/null 2>&1
```

### Windows (Task Scheduler)

Use o script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\register-laravel-scheduler.ps1 -ProjectPath "C:\caminho\gestornow-2.0" -PhpPath "C:\php\php.exe"
```

## Verificacao

```bash
php artisan schedule:list
```

Os comandos de billing ja estao no agendamento do Laravel (`app/Console/Kernel.php`):
- `billing:assinaturas-gerar-mensalidades`
- `billing:assinaturas-processar-inadimplencia --dias=5`
