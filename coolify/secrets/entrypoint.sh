#!/bin/sh
set -eu

umask 077
mkdir -p /data

create_secret() {
    secret_path="$1"
    if [ ! -s "$secret_path" ]; then
        openssl rand -base64 48 | tr -d '\r\n' > "$secret_path"
    fi
    chmod 0444 "$secret_path"
}

create_secret /data/metrics-token
create_secret /data/purge-password

# Nginx validates Basic Auth in an unprivileged worker, so the htpasswd file
# must be readable by that worker. The named volume is mounted only into the
# internal Nginx/WordPress services; the hash is never published or exported.
# Rebuild atomically on each secrets-service start, so a recreated password
# cannot leave the endpoint permanently returning HTTP 401. `-i` keeps the
# generated password out of the process argument list.
password="$(tr -d '\r\n' < /data/purge-password)"
temporary_file=/data/.purge.htpasswd.tmp
umask 077
printf '%s\n' "$password" | htpasswd -iBc "$temporary_file" baocache >/dev/null
mv "$temporary_file" /data/purge.htpasswd
chmod 0444 /data/purge.htpasswd

exec tail -f /dev/null
