#!/usr/bin/env sh
# Le prove su un WordPress vero (dev/compose.yml) con Complianz e Lingua Bridge attivi: tests/wp/run.php.
set -eu
cd "$(dirname "$0")"
./up.sh > /dev/null
. ./.env
export CB_DB_PASSWORD CB_PORT
docker compose run --rm -T cli wp plugin activate consenso-bridge --quiet
docker compose run --rm -T cli wp eval-file /cb-tests/run.php 2>&1
docker compose run --rm -T cli wp eval-file /cb-tests/aggiornatore-run.php 2>&1
