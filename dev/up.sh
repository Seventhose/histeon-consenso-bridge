#!/usr/bin/env sh
# Accende il WordPress di prova di Consenso Bridge e lo installa al primo giro. La password resta in dev/.env.
set -eu
cd "$(dirname "$0")"
if [ ! -f .env ]; then
  {
    echo "CB_DB_PASSWORD=$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')"
    echo "CB_ADMIN_PASSWORD=$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')"
    echo "CB_PORT=8960"
  } > .env
  chmod 600 .env
fi
. ./.env
export CB_DB_PASSWORD CB_PORT
docker compose up -d --wait db wordpress
until docker compose exec -T wordpress test -f /var/www/html/wp-config.php; do sleep 1; done
docker compose exec -T -u root wordpress sh -c 'mkdir -p /var/www/html/wp-content/uploads && chown -R www-data:www-data /var/www/html/wp-content/uploads'
wp() { docker compose run --rm -T cli wp "$@"; }
if ! wp core is-installed 2>/dev/null; then
  wp core install --url="http://localhost:${CB_PORT}" --title="Consenso Bridge Prova" --admin_user=admin \
    --admin_password="$CB_ADMIN_PASSWORD" --admin_email=admin@example.test --skip-email
fi
wp rewrite structure '/%postname%/' --quiet
wp plugin activate complianz-gdpr lingua-bridge --quiet
# Complianz crea le sue tabelle e il primo banner solo dall'amministrazione: qui li forziamo una volta.
wp eval 'wp_set_current_user(1); cmplz_install_cookiebanner_table(true); if (!cmplz_get_cookiebanners()) { $b = new CMPLZ_COOKIEBANNER(); $b->save(); }' 2>/dev/null
# Il plugin si attiva a parte: se manca ancora (prima delle schede) la pila resta comunque accesa.
wp plugin activate consenso-bridge --quiet 2>/dev/null || echo "consenso-bridge non attivabile (ancora da scrivere?)"
echo "Consenso Bridge prova: http://localhost:${CB_PORT} (utente 'admin', password in dev/.env)"
