#!/bin/sh
set -eu

secret_path="${REDIS_PASSWORD_FILE:-/run/secrets/redis_password}"

if [ ! -r "$secret_path" ]; then
    echo "Redis password secret is not readable: $secret_path" >&2
    exit 1
fi

password="$(tr -d '\r\n' < "$secret_path")"
if [ -z "$password" ]; then
    echo "Redis password secret is empty." >&2
    exit 1
fi

# Do not put the password in the process list. Redis reads this temporary
# configuration file, which is private to the unprivileged Redis user.
runtime_config="/tmp/redis-runtime.conf"
umask 077
{
    cat /usr/local/etc/redis/redis.conf
    printf '\nrequirepass %s\n' "$password"
} > "$runtime_config"

exec redis-server "$runtime_config"
