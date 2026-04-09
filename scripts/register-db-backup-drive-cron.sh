#!/usr/bin/env bash
set -euo pipefail

PROJECT_PATH=""
REMOTE_PATH=""
SCHEDULE="0 2 * * *"
COMPOSE_SERVICE="mysql"
DUMP_MODE="auto"
DB_HOST=""
DB_PORT=""
DB_USER=""
DB_PASSWORD=""
KEEP_REMOTE_FILES=2
CRON_TAG="GESTORNOW_DB_BACKUP_DRIVE"

usage() {
  cat <<'EOF'
Uso:
  register-db-backup-drive-cron.sh --project-path /var/www/html --remote-path gdrive:/gestornow/backups [opcoes]

Opcoes:
  --schedule "0 2 * * *"      Expressao cron (padrao: 02:00 diario)
  --dump-mode MODO             Modo do dump: auto|docker|host (padrao: auto)
  --compose-service NOME       Servico do MySQL no docker compose (padrao: mysql)
  --db-host HOST               Host do banco no modo host
  --db-port PORTA              Porta do banco no modo host
  --db-user USUARIO            Usuario do banco
  --db-password SENHA          Senha do banco
  --keep-remote-files N        Quantidade de arquivos para manter no remoto (padrao: 2)
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --project-path)
      PROJECT_PATH="$2"
      shift 2
      ;;
    --remote-path)
      REMOTE_PATH="$2"
      shift 2
      ;;
    --schedule)
      SCHEDULE="$2"
      shift 2
      ;;
    --dump-mode)
      DUMP_MODE="$2"
      shift 2
      ;;
    --compose-service)
      COMPOSE_SERVICE="$2"
      shift 2
      ;;
    --db-host)
      DB_HOST="$2"
      shift 2
      ;;
    --db-port)
      DB_PORT="$2"
      shift 2
      ;;
    --db-user)
      DB_USER="$2"
      shift 2
      ;;
    --db-password)
      DB_PASSWORD="$2"
      shift 2
      ;;
    --keep-remote-files)
      KEEP_REMOTE_FILES="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Parametro invalido: $1"
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$PROJECT_PATH" || -z "$REMOTE_PATH" ]]; then
  echo "--project-path e --remote-path sao obrigatorios."
  usage
  exit 1
fi

if [[ ! -d "$PROJECT_PATH" ]]; then
  echo "Diretorio do projeto nao encontrado: $PROJECT_PATH"
  exit 1
fi

BACKUP_SCRIPT="$PROJECT_PATH/scripts/backup-db-drive.sh"
if [[ ! -f "$BACKUP_SCRIPT" ]]; then
  echo "Script nao encontrado: $BACKUP_SCRIPT"
  exit 1
fi

LOG_DIR="$PROJECT_PATH/storage/logs"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/db-backup-drive.log"

CRON_CMD="cd '$PROJECT_PATH' && /usr/bin/env bash '$BACKUP_SCRIPT' --project-path '$PROJECT_PATH' --remote-path '$REMOTE_PATH' --compose-service '$COMPOSE_SERVICE' --keep-remote-files '$KEEP_REMOTE_FILES' >> '$LOG_FILE' 2>&1"

if [[ "$DUMP_MODE" != "auto" && "$DUMP_MODE" != "docker" && "$DUMP_MODE" != "host" ]]; then
  echo "--dump-mode invalido: $DUMP_MODE (use auto, docker ou host)"
  exit 1
fi

CRON_CMD="cd '$PROJECT_PATH' && /usr/bin/env bash '$BACKUP_SCRIPT' --project-path '$PROJECT_PATH' --remote-path '$REMOTE_PATH' --dump-mode '$DUMP_MODE' --compose-service '$COMPOSE_SERVICE' --keep-remote-files '$KEEP_REMOTE_FILES'"

if [[ -n "$DB_HOST" ]]; then
  CRON_CMD+=" --db-host '$DB_HOST'"
fi
if [[ -n "$DB_PORT" ]]; then
  CRON_CMD+=" --db-port '$DB_PORT'"
fi
if [[ -n "$DB_USER" ]]; then
  CRON_CMD+=" --db-user '$DB_USER'"
fi
if [[ -n "$DB_PASSWORD" ]]; then
  CRON_CMD+=" --db-password '$DB_PASSWORD'"
fi

CRON_CMD+=" >> '$LOG_FILE' 2>&1"
CRON_LINE="$SCHEDULE $CRON_CMD"

TMP_CRON="$(mktemp)"
{ crontab -l 2>/dev/null || true; } > "$TMP_CRON"

awk -v begin="# BEGIN ${CRON_TAG}" -v end="# END ${CRON_TAG}" '
  $0==begin {skip=1; next}
  $0==end {skip=0; next}
  skip!=1 {print}
' "$TMP_CRON" > "${TMP_CRON}.clean"

{
  cat "${TMP_CRON}.clean"
  echo "# BEGIN ${CRON_TAG}"
  echo "$CRON_LINE"
  echo "# END ${CRON_TAG}"
} | crontab -

rm -f "$TMP_CRON" "${TMP_CRON}.clean"

echo "Cron registrado com sucesso"
echo "Schedule: $SCHEDULE"
echo "RemotePath: $REMOTE_PATH"
echo "Log: $LOG_FILE"
