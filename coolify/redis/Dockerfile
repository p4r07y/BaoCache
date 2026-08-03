# syntax=docker/dockerfile:1

FROM redis:7.4-alpine

COPY --chown=redis:redis redis/redis.conf /usr/local/etc/redis/redis.conf
COPY --chown=redis:redis --chmod=0555 redis/redis-secure-entrypoint.sh /usr/local/bin/redis-secure-entrypoint.sh

# Redis does not need root privileges and Docker secrets are readable by this
# unprivileged process. Keeping the custom entrypoint in the image fixes the
# Coolify startup failure caused by a command that referenced a missing file.
USER redis
