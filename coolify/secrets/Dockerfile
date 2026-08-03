FROM alpine:3.22

RUN apk add --no-cache apache2-utils openssl

COPY secrets/entrypoint.sh /usr/local/bin/baocache-secrets
RUN chmod 0555 /usr/local/bin/baocache-secrets

ENTRYPOINT ["/usr/local/bin/baocache-secrets"]
