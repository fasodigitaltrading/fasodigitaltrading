#!/usr/bin/env bash
set -euo pipefail

: "${DB_HOST:?Set DB_HOST}"
: "${DB_DATABASE:?Set DB_DATABASE}"
: "${DB_USERNAME:?Set DB_USERNAME}"
: "${DB_PASSWORD:?Set DB_PASSWORD}"
: "${BACKUP_AGE_RECIPIENT:?Set BACKUP_AGE_RECIPIENT to the recovery public key}"
: "${BACKUP_DIR:?Set BACKUP_DIR to a protected backup directory}"

command -v mysqldump >/dev/null || { echo 'mysqldump is required.' >&2; exit 1; }
command -v age >/dev/null || { echo 'age is required.' >&2; exit 1; }
[[ "$DB_PASSWORD" != *$'\n'* && "$DB_PASSWORD" != *$'\r'* ]] || { echo 'Newlines are not supported in DB_PASSWORD.' >&2; exit 1; }

umask 077
mkdir -p -- "$BACKUP_DIR"
config_file=$(mktemp "${TMPDIR:-/tmp}/nexus-mysql.XXXXXX.cnf")
archive_tmp=''
cleanup() {
    rm -f -- "$config_file"
    if [[ -n "$archive_tmp" ]]; then rm -f -- "$archive_tmp"; fi
}
trap cleanup EXIT

escape_cnf() {
    local value="$1"
    value=${value//\\/\\\\}
    value=${value//\"/\\\"}
    printf '%s' "$value"
}

{
    printf '[client]\nhost="%s"\n' "$(escape_cnf "$DB_HOST")"
    printf 'user="%s"\npassword="%s"\n' "$(escape_cnf "$DB_USERNAME")" "$(escape_cnf "$DB_PASSWORD")"
    if [[ -n "${DB_PORT:-}" ]]; then printf 'port=%s\n' "$DB_PORT"; fi
} > "$config_file"
chmod 600 "$config_file"

timestamp=$(date -u +%Y%m%dT%H%M%SZ)
archive="$BACKUP_DIR/nexus-${DB_DATABASE}-${timestamp}.sql.gz.age"
[[ ! -e "$archive" ]] || { echo 'Backup destination already exists.' >&2; exit 1; }
archive_tmp="${archive}.partial"

mysqldump --defaults-extra-file="$config_file" --single-transaction --quick --routines --triggers --no-tablespaces "$DB_DATABASE" \
    | gzip -c \
    | age --recipient "$BACKUP_AGE_RECIPIENT" > "$archive_tmp"

mv -- "$archive_tmp" "$archive"
archive_tmp=''
echo "Encrypted database backup created: $archive"
