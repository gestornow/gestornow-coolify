#!/usr/bin/env bash
set -euo pipefail

PROJECT_PATH=""
REMOTE_PATH=""
COMPOSE_SERVICE="mysql"
DUMP_MODE="auto"
DB_NAME=""
DB_USER=""
DB_PASSWORD=""
DB_HOST=""
DB_PORT=""
KEEP_REMOTE_FILES=2
LOCAL_BACKUP_DIR=""

usage() {
  cat <<'EOF'
Uso:
  backup-db-drive.sh --project-path /var/www/html --remote-path gdrive:/gestornow/backups [opcoes]

Opcoes:
  --dump-mode MODO             Modo do dump: auto|docker|host (padrao: auto)
  --compose-service NOME       Servico do MySQL no docker compose (padrao: mysql)
  --db-name NOME               Nome do banco (fallback: DB_DATABASE do .env)
  --db-user USUARIO            Usuario do banco (fallback: DB_USERNAME do .env)
  --db-password SENHA          Senha do banco (fallback: DB_PASSWORD do .env)
  --db-host HOST               Host do banco no modo host (fallback: DB_HOST ou 127.0.0.1)
  --db-port PORTA              Porta do banco no modo host (fallback: DB_PORT ou 3306)
  --keep-remote-files N        Quantidade de arquivos para manter no remoto (padrao: 2)
  --local-backup-dir CAMINHO   Diretorio local dos dumps (padrao: storage/app/backups/db)
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
    --dump-mode)
      DUMP_MODE="$2"
      shift 2
      ;;
    --compose-service)
      COMPOSE_SERVICE="$2"
      shift 2
      ;;
    --db-name)
      DB_NAME="$2"
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
    --db-host)
      DB_HOST="$2"
      shift 2
      ;;
    --db-port)
      DB_PORT="$2"
      shift 2
      ;;
    --keep-remote-files)
      KEEP_REMOTE_FILES="$2"
      shift 2
      ;;
    --local-backup-dir)
      LOCAL_BACKUP_DIR="$2"
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

if ! command -v rclone >/dev/null 2>&1; then
  echo "Comando rclone nao encontrado no PATH."
  exit 1
fi

if ! command -v mysqldump >/dev/null 2>&1; then
  echo "Comando mysqldump nao encontrado no PATH."
  exit 1
fi

if [[ "$DUMP_MODE" != "auto" && "$DUMP_MODE" != "docker" && "$DUMP_MODE" != "host" ]]; then
  echo "--dump-mode invalido: $DUMP_MODE (use auto, docker ou host)"
  exit 1
fi

COMPOSE_FILE="$PROJECT_PATH/docker-compose.yml"

read_dotenv_value() {
  local key="$1"
  local env_file="$2"

  if [[ ! -f "$env_file" ]]; then
    return 0
  fi

  local line
  line="$(grep -E "^${key}=" "$env_file" | tail -n 1 || true)"
  line="${line#${key}=}"

  line="${line%\r}"
  if [[ "$line" =~ ^\".*\"$ ]]; then
    line="${line:1:${#line}-2}"
  fi
  if [[ "$line" =~ ^\'.*\'$ ]]; then
    line="${line:1:${#line}-2}"
  fi

  printf "%s" "$line"
}

ENV_FILE="$PROJECT_PATH/.env"

if [[ -z "$DB_NAME" ]]; then
  DB_NAME="$(read_dotenv_value "DB_DATABASE" "$ENV_FILE")"
fi
if [[ -z "$DB_USER" ]]; then
  DB_USER="$(read_dotenv_value "DB_USERNAME" "$ENV_FILE")"
fi
if [[ -z "$DB_PASSWORD" ]]; then
  DB_PASSWORD="$(read_dotenv_value "DB_PASSWORD" "$ENV_FILE")"
fi
if [[ -z "$DB_HOST" ]]; then
  DB_HOST="$(read_dotenv_value "DB_HOST" "$ENV_FILE")"
fi
if [[ -z "$DB_PORT" ]]; then
  DB_PORT="$(read_dotenv_value "DB_PORT" "$ENV_FILE")"
fi

if [[ -z "$DB_NAME" || -z "$DB_USER" || -z "$DB_PASSWORD" ]]; then
  echo "Nao foi possivel resolver DB_DATABASE, DB_USERNAME e DB_PASSWORD."
  echo "Passe --db-name, --db-user e --db-password ou configure no .env."
  exit 1
fi

if [[ -z "$DB_HOST" ]]; then
  DB_HOST="127.0.0.1"
fi
if [[ -z "$DB_PORT" ]]; then
  DB_PORT="3306"
fi

if [[ "$KEEP_REMOTE_FILES" -lt 1 ]]; then
  echo "--keep-remote-files deve ser >= 1"
  exit 1
fi

if [[ -z "$LOCAL_BACKUP_DIR" ]]; then
  LOCAL_BACKUP_DIR="$PROJECT_PATH/storage/app/backups/db"
fi
mkdir -p "$LOCAL_BACKUP_DIR"

escape_sq() {
  printf "%s" "$1" | sed "s/'/'\\\\''/g"
}

TIMESTAMP="$(date +%Y-%m-%d_%H%M%S)"
FILE_NAME="${DB_NAME}_${TIMESTAMP}.sql"
LOCAL_FILE="$LOCAL_BACKUP_DIR/$FILE_NAME"

ESC_DB_PASSWORD="$(escape_sq "$DB_PASSWORD")"
ESC_DB_USER="$(escape_sq "$DB_USER")"
ESC_DB_NAME="$(escape_sq "$DB_NAME")"

run_dump_host() {
  echo "Gerando dump do banco (modo host): $DB_NAME@$DB_HOST:$DB_PORT"
  MYSQL_PWD="$DB_PASSWORD" mysqldump \
    -h "$DB_HOST" \
    -P "$DB_PORT" \
    -u"$DB_USER" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    "$DB_NAME" > "$LOCAL_FILE"
}

run_dump_docker() {
  local esc_db_password esc_db_user esc_db_name dump_cmd
  esc_db_password="$(escape_sq "$DB_PASSWORD")"
  esc_db_user="$(escape_sq "$DB_USER")"
  esc_db_name="$(escape_sq "$DB_NAME")"
  dump_cmd="export MYSQL_PWD='${esc_db_password}'; mysqldump -u'${esc_db_user}' --single-transaction --quick --routines --triggers --events '${esc_db_name}'"

  echo "Gerando dump do banco (modo docker): $DB_NAME via servico $COMPOSE_SERVICE"
  docker compose -f "$COMPOSE_FILE" exec -T "$COMPOSE_SERVICE" sh -lc "$dump_cmd" > "$LOCAL_FILE"
}

is_docker_service_running() {
  if ! command -v docker >/dev/null 2>&1; then
    return 1
  fi
  if [[ ! -f "$COMPOSE_FILE" ]]; then
    return 1
  fi

  docker compose -f "$COMPOSE_FILE" ps --services --status running 2>/dev/null | grep -Fx "$COMPOSE_SERVICE" >/dev/null 2>&1
}

if [[ "$DUMP_MODE" == "host" ]]; then
  run_dump_host
elif [[ "$DUMP_MODE" == "docker" ]]; then
  if ! command -v docker >/dev/null 2>&1; then
    echo "Comando docker nao encontrado no PATH para --dump-mode docker."
    exit 1
  fi
  if [[ ! -f "$COMPOSE_FILE" ]]; then
    echo "docker-compose.yml nao encontrado em: $COMPOSE_FILE"
    exit 1
  fi
  run_dump_docker
else
  if is_docker_service_running; then
    run_dump_docker
  else
    run_dump_host
  fi
fi

if [[ ! -s "$LOCAL_FILE" ]]; then
  echo "Dump vazio ou nao criado: $LOCAL_FILE"
  exit 1
fi

echo "Enviando para o Drive: $REMOTE_PATH/$FILE_NAME"
rclone copyto "$LOCAL_FILE" "$REMOTE_PATH/$FILE_NAME"

echo "Aplicando retencao no remoto (manter $KEEP_REMOTE_FILES)"
mapfile -t REMOTE_FILES < <(rclone lsf "$REMOTE_PATH" --files-only | grep -E "^${DB_NAME}_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\\.sql$" | sort -r || true)

if (( ${#REMOTE_FILES[@]} > KEEP_REMOTE_FILES )); then
  for ((i=KEEP_REMOTE_FILES; i<${#REMOTE_FILES[@]}; i++)); do
    OLD_FILE="${REMOTE_FILES[$i]}"
    echo "Removendo antigo: $REMOTE_PATH/$OLD_FILE"
    rclone deletefile "$REMOTE_PATH/$OLD_FILE"
  done
fi

echo "Backup concluido com sucesso"
echo "Arquivo local: $LOCAL_FILE"
echo "Arquivo remoto: $REMOTE_PATH/$FILE_NAME"
