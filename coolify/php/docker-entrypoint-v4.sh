#!/bin/sh
set -eu

# Docker Compose mounts a secret at /run/secrets/<secret-name> unless a
# target is explicitly set.  The Compose secret is named
# "digitalocean_mysql_ca" (with underscores), so use that canonical path.
# Keep the former path as a compatibility fallback for manually created
# Docker secrets from older deployments.
ca_source=/run/secrets/digitalocean_mysql_ca
if [ ! -s "$ca_source" ] && [ -s /run/secrets/digitalocean-mysql-ca.crt ]; then
    ca_source=/run/secrets/digitalocean-mysql-ca.crt
fi
ca_target=/usr/local/share/ca-certificates/digitalocean-mysql-ca.crt
ca_normalized=/tmp/digitalocean-mysql-ca.input
ca_decoded=/tmp/digitalocean-mysql-ca.decoded

if [ ! -s "$ca_source" ]; then
    echo "ERROR: DigitalOcean MySQL CA secret is missing or empty." >&2
    exit 1
fi

# Coolify can wrap multiline values in quotes. Older configurations can also
# contain literal "\n" sequences or a base64-encoded PEM.
tr -d '\r' < "$ca_source" \
    | sed -e "1s/^[[:space:]]*['\"]//" -e "\$s/['\"][[:space:]]*\$//" \
    > "$ca_normalized"

normalized_value=$(cat "$ca_normalized")
printf '%b' "$normalized_value" > "$ca_target"

if ! grep -q -- '-----BEGIN CERTIFICATE-----' "$ca_target"; then
    sed 's/^base64:[[:space:]]*//' "$ca_normalized" \
        | tr -d '[:space:]' \
        | base64 -d > "$ca_decoded" 2>/dev/null || true

    if grep -q -- '-----BEGIN CERTIFICATE-----' "$ca_decoded" 2>/dev/null; then
        tr -d '\r' < "$ca_decoded" > "$ca_target"
    fi
fi

if ! grep -q -- '-----BEGIN CERTIFICATE-----' "$ca_target" \
    || ! grep -q -- '-----END CERTIFICATE-----' "$ca_target" \
    || ! openssl x509 -in "$ca_target" -noout >/dev/null 2>&1; then
    received_bytes=$(wc -c < "$ca_source" | tr -d ' ')
    echo "ERROR: DB_SSL_CA could not be parsed as a PEM certificate (${received_bytes} bytes received)." >&2
    echo "In Coolify Normal view, enable Multiline and paste the complete downloaded DigitalOcean CA." >&2
    echo "Do not paste a filename, connection string, quotation marks, or placeholder text." >&2
    exit 1
fi

update-ca-certificates >/dev/null
rm -f "$ca_normalized" "$ca_decoded"

if [ "${SYNC_BUNDLED_PLUGINS:-false}" = "true" ]; then
    mkdir -p /usr/src/wordpress/wp-content/plugins
    bundled_count=0

    for plugin_path in /opt/bundled-plugins/*; do
        [ -e "$plugin_path" ] || continue
        plugin_name=$(basename "$plugin_path")
        case "$plugin_name" in
            *.md|*.txt) continue ;;
        esac

        cp -a "$plugin_path" /usr/src/wordpress/wp-content/plugins/
        bundled_count=$((bundled_count + 1))
    done

    # Populate a new named volume before synchronizing to the live plugin path.
    # The official helper runs the WordPress installation logic and returns
    # because its command is "true".
    /usr/local/bin/docker-ensure-installed.sh true

    mkdir -p /var/www/html/wp-content/plugins
    for plugin_path in /opt/bundled-plugins/*; do
        [ -e "$plugin_path" ] || continue
        plugin_name=$(basename "$plugin_path")
        case "$plugin_name" in
            *.md|*.txt) continue ;;
        esac

        cp -a "$plugin_path" /var/www/html/wp-content/plugins/
        echo "Bundled plugin synchronized: $plugin_name"
    done

    if [ -f /opt/bundled-dropins/object-cache.php ]; then
        cp -a /opt/bundled-dropins/object-cache.php /usr/src/wordpress/wp-content/object-cache.php
        cp -a /opt/bundled-dropins/object-cache.php /var/www/html/wp-content/object-cache.php
        chown www-data:www-data \
            /usr/src/wordpress/wp-content/object-cache.php \
            /var/www/html/wp-content/object-cache.php
        echo "Bundled drop-in synchronized: object-cache.php"
    fi

    chown -R www-data:www-data /var/www/html/wp-content/plugins

    if [ "$bundled_count" -eq 0 ]; then
        echo "NOTICE: No bundled plugin folder or PHP plugin file was found in wordpress/plugins/."
    fi
fi

if [ "${SYNC_BUNDLED_THEMES:-false}" = "true" ]; then
    mkdir -p /usr/src/wordpress/wp-content/themes
    theme_count=0

    for theme_path in /opt/bundled-themes/*; do
        [ -d "$theme_path" ] || continue
        theme_name=$(basename "$theme_path")
        cp -a "$theme_path" /usr/src/wordpress/wp-content/themes/
        theme_count=$((theme_count + 1))
    done

    /usr/local/bin/docker-ensure-installed.sh true

    mkdir -p /var/www/html/wp-content/themes
    for theme_path in /opt/bundled-themes/*; do
        [ -d "$theme_path" ] || continue
        theme_name=$(basename "$theme_path")
        cp -a "$theme_path" /var/www/html/wp-content/themes/
        echo "Bundled theme synchronized: $theme_name"
    done

    chown -R www-data:www-data /var/www/html/wp-content/themes

    if [ "$theme_count" -eq 0 ]; then
        echo "NOTICE: No bundled theme folder was found in wordpress/themes/."
    fi
fi

if wp --path=/var/www/html core is-installed --allow-root >/dev/null 2>&1; then
    for plugin_name in ${AUTO_ACTIVATE_BUNDLED_PLUGINS:-}; do
        if wp --path=/var/www/html plugin is-installed "$plugin_name" --allow-root >/dev/null 2>&1; then
            wp --path=/var/www/html plugin activate "$plugin_name" --skip-themes --allow-root >/dev/null
            echo "Bundled plugin active: $plugin_name"
        fi
    done

    theme_name=${AUTO_ACTIVATE_BUNDLED_THEME:-}
    if [ -n "$theme_name" ]; then
        if ! wp --path=/var/www/html theme is-installed blocksy --allow-root >/dev/null 2>&1; then
            echo "NOTICE: Blocksy parent theme is not installed; child theme activation was skipped."
        elif wp --path=/var/www/html theme is-installed "$theme_name" --allow-root >/dev/null 2>&1; then
            wp --path=/var/www/html theme activate "$theme_name" --skip-plugins --allow-root >/dev/null
            echo "Bundled theme active: $theme_name"
        fi
    fi
fi

exec docker-entrypoint.sh "$@"
