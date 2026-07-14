#!/bin/bash
set -euo pipefail

ARCHIVE="${1:-/home/debian/portfolio/classificator-deploy.tgz}"
TARGET="/var/www/okpd2"
TMP="/home/debian/okpd2-build"
SNIP_SRC="/home/debian/portfolio/okpd2-nginx-snippet.conf"

echo "==> Unpack ${ARCHIVE}"
rm -rf "${TMP}"
mkdir -p "${TMP}"
tar -xzf "${ARCHIVE}" -C "${TMP}"

echo "==> Install to ${TARGET}"
sudo mkdir -p "${TARGET}"
sudo rsync -a --delete \
  --exclude='.env' \
  --exclude='node_modules' \
  --exclude='storage/logs/*' \
  "${TMP}/" "${TARGET}/"
sudo chown -R debian:debian "${TARGET}"

cd "${TARGET}"

echo "==> PHP extensions + composer"
if ! command -v php >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  export NEEDRESTART_MODE=a
  sudo apt-get update -y
  sudo apt-get install -y php-fpm php-cli php-sqlite3 php-mbstring php-xml php-curl php-zip php-bcmath
fi

php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
php /tmp/composer-setup.php --2 --install-dir=/tmp --filename=composer
sudo mv /tmp/composer /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
rm -f /tmp/composer-setup.php

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --force
fi

php -r "
\$f = '.env';
\$t = file_get_contents(\$f);
\$t = preg_replace('/^APP_ENV=.*/m', 'APP_ENV=production', \$t);
\$t = preg_replace('/^APP_DEBUG=.*/m', 'APP_DEBUG=false', \$t);
\$t = preg_replace('/^APP_URL=.*/m', 'APP_URL=https://avaks.online/okpd2', \$t);
if (!preg_match('/^APP_URL=/m', \$t)) { \$t .= PHP_EOL.'APP_URL=https://avaks.online/okpd2'; }
file_put_contents(\$f, \$t);
"

composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R 775 storage bootstrap/cache

PHP_SOCK="$(ls /run/php/php8.4-fpm.sock /run/php/php8.3-fpm.sock /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)"
if [ -z "${PHP_SOCK}" ]; then
  echo "PHP-FPM socket not found"
  exit 1
fi
echo "Using PHP socket: ${PHP_SOCK}"
sudo systemctl enable php*-fpm 2>/dev/null || true
sudo systemctl restart php*-fpm 2>/dev/null || true

echo "==> Patch nginx"
if [ -f "${SNIP_SRC}" ]; then
  SNIP_TMP="/tmp/okpd2-nginx-snippet.conf"
  sed "s|__PHP_FPM_SOCK__|${PHP_SOCK}|g" "${SNIP_SRC}" > "${SNIP_TMP}"
  sudo python3 - <<PY
from pathlib import Path
conf = Path('/etc/nginx/sites-enabled/avaks.online')
text = conf.read_text()
marker = '    location = /api/health'
snip = Path('${SNIP_TMP}').read_text()
if 'location ^~ /api/okpd2/' in text:
    print('okpd2 locations already present')
else:
    count = 0
    while marker in text:
        text = text.replace(marker, snip + '\\n\\n' + marker, 1)
        count += 1
    conf.write_text(text)
    print(f'inserted okpd2 into {count} server block(s)')

sock = "${PHP_SOCK}"
tnved_api = f'''    location ^~ /api/tnved/ {{
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/okpd2/public/index.php;
        fastcgi_param REQUEST_URI \$request_uri;
        fastcgi_pass unix:{sock};
    }}'''
tnved_redirect = '''    location = /tnved {
        return 301 /okpd2/tnved;
    }'''
if 'location ^~ /api/tnved/' not in text:
    text = text.replace('location ^~ /api/okpd2/', tnved_api + '\\n\\n    location ^~ /api/okpd2/', 1)
    print('inserted api/tnved location')
if 'location = /tnved' not in text:
    text = text.replace('location = /okpd2 {', tnved_redirect + '\\n\\n    location = /okpd2 {', 1)
    print('inserted /tnved redirect')
conf.write_text(text)
PY
  sudo nginx -t
  sudo systemctl reload nginx
fi

echo "==> Smoke test"
curl -fsS -o /dev/null -w "UI HTTP %{http_code}\n" http://127.0.0.1/okpd2/ -H 'Host: avaks.online' || true
curl -fsS -o /dev/null -w "TNVED HTTP %{http_code}\n" http://127.0.0.1/okpd2/tnved -H 'Host: avaks.online' || true
curl -fsS http://127.0.0.1/api/okpd2/meta -H 'Host: avaks.online' | head -c 200 || true
echo
echo "==> Done"
