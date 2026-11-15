#!/usr/bin/env bash
set -euo pipefail

backup_file=${1:?Usage: restore-mysql.sh BACKUP_FILE.age}
: "${DB_HOST:?Set DB_HOST}"
: "${DB_DATABASE:?Set DB_DATABASE}"
: "${DB_USERNAME:?Set DB_USERNAME}"
: "${DB_PASSWORD:?Set DB_PASSWORD}"
: "${AGE_IDENTITY_FILE:?Set AGE_IDENTITY_FILE to the private recovery key file}"
: "${CONFIRM_DATABASE_RESTORE:?Set CONFIRM_DATABASE_RESTORE=YES only after verifying the target database}"

[[ "$CONFIRM_DATABASE_RESTORE" == 'YES' ]] || { echo 'Restore cancelled; set CONFIRM_DATABASE_RESTORE=YES explicitly.' >&2; exit 1; }
[[ -f "$backup_file" && -f "$AGE_IDENTITY_FILE" ]] || { echo 'Backup or recovery identity file not found.' >&2; exit 1; }
command -v mysql >/dev/null || { echo 'mysql client is required.' >&2; exit 1; }
command -v age >/dev/null || { echo 'age is required.' >&2; exit 1; }
[[ "$DB_PASSWORD" != *$'\n'* && "$DB_PASSWORD" != *$'\r'* ]] || { echo 'Newlines are not supported in DB_PASSWORD.' >&2; exit 1; }

umask 077
config_file=$(mktemp "${TMPDIR:-/tmp}/nexus-mysql.XXXXXX.cnf")
cleanup() { rm -f -- "$config_file"; }
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

echo "Restoring into ${DB_HOST}/${DB_DATABASE}. This replaces matching schema data; the explicit confirmation was supplied."
age --decrypt --identity "$AGE_IDENTITY_FILE" "$backup_file" \
    | gzip -dc \
    | mysql --defaults-extra-file="$config_file" "$DB_DATABASE"
