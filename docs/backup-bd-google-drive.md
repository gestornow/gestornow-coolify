# Backup diario do banco no Google Drive (mantendo 2 ultimos)

Este guia configura:
- backup diario completo do MySQL
- envio para Google Drive
- retencao de apenas 2 backups no remoto
- no terceiro dia, o mais antigo e removido automaticamente

## 1) Cenario recomendado: servidor Oracle Linux

Arquivos usados:
- `scripts/backup-db-drive.sh`
- `scripts/register-db-backup-drive-cron.sh`

### Pre-requisitos

1. Docker e Docker Compose funcionando no servidor
2. rclone instalado
3. remote do Google Drive configurado no rclone (exemplo: `gdrive`)

Importante:
- `gdrive:/gestornow/backups` nao e uma pasta local do servidor.
- `gdrive` e apenas o nome (apelido) da conexao da sua conta Google Drive no rclone.
- A parte depois de `:` e a pasta dentro do seu Google Drive.
- Se a porta `3306` estiver fechada para internet, isso nao impede backup local no proprio servidor.

Exemplo de instalacao/configuracao no Linux:

```bash
curl https://rclone.org/install.sh | sudo bash
rclone config
```

No `rclone config`, crie um remote do tipo `drive` (Google Drive).
Voce pode usar qualquer nome: `meudrive`, `google`, `backupdrive` etc.

### Teste manual

```bash
cd /var/www/html
chmod +x scripts/backup-db-drive.sh scripts/register-db-backup-drive-cron.sh
./scripts/backup-db-drive.sh \
	--project-path /var/www/html \
	--dump-mode host \
	--db-host 164.152.61.60 \
	--db-port 3306 \
	--db-user backup_gestor \
	--db-password "SENHA_FORTE_AQUI" \
	--remote-path "meudrive:/gestornow/backups" \
	--keep-remote-files 2
```

Validar no Drive:

```bash
rclone lsf meudrive:/gestornow/backups
```

### Registrar execucao diaria (cron)

Exemplo para rodar todo dia as 02:00:

```bash
cd /var/www/html
./scripts/register-db-backup-drive-cron.sh \
	--project-path /var/www/html \
	--dump-mode host \
	--db-host 164.152.61.60 \
	--db-port 3306 \
	--db-user backup_gestor \
	--db-password "SENHA_FORTE_AQUI" \
	--remote-path "meudrive:/gestornow/backups" \
	--schedule "0 2 * * *" \
	--keep-remote-files 2
```

Log da execucao:
- `storage/logs/db-backup-drive.log`

Ver cron registrado:

```bash
crontab -l
```

## 2) Opcional: ambiente Windows

Se algum ambiente seu rodar no Windows, existem os scripts:
- `scripts/backup-db-drive.ps1`
- `scripts/register-db-backup-drive-task.ps1`

## Observacoes

- Os scripts tentam ler `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` do arquivo `.env`.
- No modo `host`, os scripts usam `DB_HOST` e `DB_PORT` do `.env` (fallback para `127.0.0.1:3306`).
- O dump local e salvo em `storage/app/backups/db`.
- A retencao de 2 arquivos acontece no remoto (Drive).
