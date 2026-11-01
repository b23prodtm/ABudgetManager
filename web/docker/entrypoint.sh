#!/bin/sh
# Shared entrypoint for the `init-web` (one-shot) and `web` services.
set -e

mkdir -p /data/backups
# The CakePHP security salt is generated once and persisted on the data volume.
if [ -z "${SECURITY_SALT:-}" ]; then
    if [ ! -s /data/.salt ]; then
        lock_dir=/data/.salt.lock
        locked=0
        while [ ! -s /data/.salt ]; do
            if mkdir "$lock_dir" 2>/dev/null; then
                locked=1
                break
            fi
            sleep 0.1
        done
        if [ "$locked" -eq 1 ]; then
            salt_tmp=$(mktemp /data/.salt.XXXXXX)
            trap 'rm -f "${salt_tmp:-}"; rmdir "$lock_dir" 2>/dev/null || true' EXIT
            trap 'exit 1' HUP INT TERM
            if [ ! -s /data/.salt ]; then
                if ! head -c 48 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$salt_tmp" \
                    || [ "$(wc -c < "$salt_tmp")" -ne 96 ]; then
                    exit 1
                fi
                chmod 600 "$salt_tmp"
                mv -f "$salt_tmp" /data/.salt
            fi
            rmdir "$lock_dir"
            trap - EXIT HUP INT TERM
        fi
    fi
    SECURITY_SALT="$(cat /data/.salt)"
    [ -n "$SECURITY_SALT" ] || exit 1
    export SECURITY_SALT
fi

if [ "$1" = "init" ]; then
    echo "Applying database migrations..."
    su -s /bin/sh www-data -c "cd /var/www/html && php bin/cake.php migrations migrate"
    chown -R www-data:www-data /data
    echo "Initialisation done"
    exit 0
fi

chown -R www-data:www-data /data /var/www/html/tmp /var/www/html/logs
exec docker-php-entrypoint "$@"
