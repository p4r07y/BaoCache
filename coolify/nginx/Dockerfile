# syntax=docker/dockerfile:1

FROM alpine:3.22 AS module-builder

ARG NGINX_VERSION=1.28.0
ARG NGX_CACHE_PURGE_COMMIT=285354eddd5675c765ba2b79dac09f5d3065b22f

RUN apk add --no-cache build-base curl git linux-headers openssl-dev pcre2-dev zlib-dev

RUN set -eux; \
    curl -fsSLO "https://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz"; \
    tar -xzf "nginx-${NGINX_VERSION}.tar.gz"; \
    git clone https://github.com/nginx-modules/ngx_cache_purge.git /tmp/ngx_cache_purge; \
    git -C /tmp/ngx_cache_purge checkout --detach "${NGX_CACHE_PURGE_COMMIT}"; \
    cd "nginx-${NGINX_VERSION}"; \
    ./configure --with-compat --add-dynamic-module=/tmp/ngx_cache_purge; \
    make modules

FROM nginx:1.28.0-alpine

COPY --from=module-builder /nginx-1.28.0/objs/ngx_http_cache_purge_module.so /etc/nginx/modules/ngx_http_cache_purge_module.so

RUN mkdir -p /var/lib/baocache /run/baocache-secrets /usr/share/nginx/html/__baocache && chown nginx:nginx /var/lib/baocache

RUN test -s /etc/nginx/modules/ngx_http_cache_purge_module.so

COPY nginx/nginx.conf /etc/nginx/nginx.conf
COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY nginx/maintenance.html /usr/share/nginx/html/__baocache/maintenance.html

# Compose DNS is unavailable while the image is being built. Validate the full
# configuration against a temporary local FastCGI address, then restore the
# runtime upstream name used on Coolify's application network.
RUN set -eux; \
    cp /etc/nginx/conf.d/default.conf /tmp/default.conf; \
    sed -i 's/fastcgi_pass wordpress:9000;/fastcgi_pass 127.0.0.1:9000;/' /etc/nginx/conf.d/default.conf; \
    nginx -t; \
    mv /tmp/default.conf /etc/nginx/conf.d/default.conf
