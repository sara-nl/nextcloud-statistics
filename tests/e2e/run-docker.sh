#!/usr/bin/env bash
# Starts the official Nextcloud Docker image, installs the app from this checkout and runs
# the end-to-end test against it. The containers are removed afterwards.
#
# usage: tests/e2e/run-docker.sh <image tag, e.g. 35 or latest> [port]
#
# DB=sqlite (default), mysql (MariaDB) or pgsql picks the database.
# FORCE_ENABLE=1 enables the app on a Nextcloud version newer than max-version in info.xml.
# UPGRADE_FROM=<git ref, e.g. v2.5.3> first installs that version of the app, stores settings
# with it, then upgrades to this checkout and checks that the settings survived.
# E2E_APPS sets the apps to install from the app store, so that their collectors are tested
# (default: calendar contacts deck spreed forms mail richdocuments). An app that can't be
# installed, for example because it doesn't support this Nextcloud yet, is skipped with a notice.
# Build the frontend first (npm ci && npm run build) to also check that the page's script loads.
set -euo pipefail
TAG=${1:?usage: $0 <image tag, e.g. 35 or latest> [port]}
PORT=${2:-8080}
DB=${DB:-sqlite}
APPS=${E2E_APPS-calendar contacts deck spreed forms mail richdocuments}
APP_DIR=$(cd "$(dirname "$0")/../.." && pwd)
NAME=stats_collector-e2e-$TAG-$DB
IMAGE=nextcloud:$TAG-apache
if [ "$TAG" = latest ]; then
	IMAGE=nextcloud:apache
fi
APP_PATH=/var/www/html/custom_apps/stats_collector

cleanup() {
	docker rm -fv "$NAME" "$NAME-db" >/dev/null 2>&1 || true
	docker network rm "$NAME" >/dev/null 2>&1 || true
}
cleanup
trap cleanup EXIT
docker network create "$NAME" >/dev/null

db_password=$(head -c 18 /dev/urandom | base64 | tr -d '/+=')
case $DB in
	sqlite)
		db_env="-e SQLITE_DATABASE=nextcloud" ;;
	mysql)
		docker run -d --name "$NAME-db" --network "$NAME" -e MARIADB_RANDOM_ROOT_PASSWORD=1 \
			-e MARIADB_DATABASE=nextcloud -e MARIADB_USER=nextcloud -e MARIADB_PASSWORD="$db_password" \
			mariadb:11.4 --transaction-isolation=READ-COMMITTED >/dev/null
		db_env="-e MYSQL_HOST=$NAME-db -e MYSQL_DATABASE=nextcloud -e MYSQL_USER=nextcloud -e MYSQL_PASSWORD=$db_password" ;;
	pgsql)
		docker run -d --name "$NAME-db" --network "$NAME" \
			-e POSTGRES_DB=nextcloud -e POSTGRES_USER=nextcloud -e POSTGRES_PASSWORD="$db_password" \
			postgres:17-alpine >/dev/null
		db_env="-e POSTGRES_HOST=$NAME-db -e POSTGRES_DB=nextcloud -e POSTGRES_USER=nextcloud -e POSTGRES_PASSWORD=$db_password" ;;
	*)
		echo "DB must be sqlite, mysql or pgsql" >&2; exit 2 ;;
esac

# shellcheck disable=SC2086 # db_env holds several arguments
docker run -d --name "$NAME" --network "$NAME" -p "127.0.0.1:$PORT:80" $db_env \
	-e NEXTCLOUD_ADMIN_USER=admin \
	-e NEXTCLOUD_ADMIN_PASSWORD="$(head -c 24 /dev/urandom | base64)" \
	-e NEXTCLOUD_TRUSTED_DOMAINS=localhost \
	"$IMAGE" >/dev/null

wait_for_nextcloud() {
	for _ in $(seq 1 150); do
		if curl -s "http://localhost:$PORT/status.php" | grep -q '"installed":true'; then
			return
		fi
		sleep 2
	done
	docker logs "$NAME" | tail -20
	exit 1
}
echo "Waiting for Nextcloud ($TAG, $DB) to be installed..."
wait_for_nextcloud

occ() { docker exec -u www-data "$NAME" php occ "$@"; }
# New users get no skeleton files, which keeps the file counts predictable
occ config:system:set skeletondirectory --value='' >/dev/null

for app in $APPS; do
	if out=$(occ app:install "$app" 2>&1) || out=$(occ app:enable "$app" 2>&1); then
		echo "Installed $app"
	else
		echo "NOTICE could not install $app, its collector is not tested: $(echo "$out" | tail -1)"
	fi
done

# Copies the app into the container; reads a tar stream of the app's files on stdin
install_app() {
	docker exec "$NAME" rm -rf "$APP_PATH"
	docker exec "$NAME" mkdir -p "$APP_PATH"
	docker exec -i "$NAME" tar -C "$APP_PATH" -xf - 2>/dev/null
	docker exec "$NAME" chown -R www-data:www-data "$APP_PATH"
}
# COPYFILE_DISABLE keeps macOS tar from adding ._ files, which Nextcloud would load as classes
app_files() {
	(cd "$APP_DIR" && COPYFILE_DISABLE=1 tar -cf - appinfo lib templates img css $(ls -d js 2>/dev/null))
}

# Only errors from here on fail the run, not those of installing the apps above
docker exec "$NAME" sh -c ': > /var/www/html/data/nextcloud.log'

status=0
if [ -n "${UPGRADE_FROM:-}" ]; then
	echo "== Installing $UPGRADE_FROM first"
	git -C "$APP_DIR" archive "$UPGRADE_FROM" appinfo lib templates img css | install_app
	occ app:enable ${FORCE_ENABLE:+--force} stats_collector
	"$APP_DIR/tests/e2e/upgrade.sh" before "http://localhost:$PORT" "$NAME" || status=$?
	app_files | install_app
	occ upgrade
	# The web server's OPcache would otherwise keep running the old version for up to a
	# minute, as on a real server until PHP is restarted
	docker restart "$NAME" >/dev/null
	wait_for_nextcloud
	"$APP_DIR/tests/e2e/upgrade.sh" after "http://localhost:$PORT" "$NAME" || status=$?
else
	app_files | install_app
	occ app:enable ${FORCE_ENABLE:+--force} stats_collector
fi

"$APP_DIR/tests/e2e/e2e.sh" "http://localhost:$PORT" "$NAME" || status=$?

log=$(docker exec "$NAME" sh -c 'cat /var/www/html/data/nextcloud.log 2>/dev/null' || true)
echo "== Nextcloud log (warnings and errors)"
echo "$log" | grep -E '"level":[2-4],' || true

# Any error in the log fails the run, and so does any warning about this app, such as a
# collector whose query failed.
unexpected=$(echo "$log" | python3 -c 'import json,sys
for line in sys.stdin:
    try:
        entry = json.loads(line)
    except ValueError:
        continue
    # Not the url: every request to the app has stats_collector in it
    text = entry.get("app", "") + entry.get("message", "") + json.dumps(entry.get("exception", ""))
    level = entry.get("level", 0)
    # occ upgrade logs at debug level while it runs, so skip debug and info
    if level >= 3 or (level == 2 and ("stats_collector" in text or "StatsCollector" in text)):
        print(entry.get("app", "") + ": " + entry.get("message", "")[:300])')
if [ -n "$unexpected" ]; then
	echo "== Unexpected entries in the Nextcloud log"
	echo "$unexpected"
	status=1
fi
exit $status
