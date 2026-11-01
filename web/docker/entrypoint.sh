#!/bin/sh
# Shared entrypoint for the `init-web` (one-shot) and `web` services.
set -e

mkdir -p /data/backups
# The CakePHP security salt is generated once and persisted on the data volume.
if [ -z "$SECURITY_SALT" ]; then
    [ -f /data/.salt ] || (umask 077 && head -c 48 /dev/urandom | od -An -tx1 | tr -d ' \n' > /data/.salt)
    SECURITY_SALT="$(cat /data/.salt)"
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
