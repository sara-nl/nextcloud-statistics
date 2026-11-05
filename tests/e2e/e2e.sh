#!/usr/bin/env bash
# End-to-end test against a Nextcloud container with this app enabled.
#
# usage: tests/e2e/e2e.sh <base_url> <container>
#
# Uses the app the way its clients do: the OCC commands, the admin API, the personal
# dashboard (API, page and navigation entry), the public pull API with an API key and the
# background job. Every metric of every collector whose app is enabled must have a value.
# The test changes the app's settings and resets them at the end, so only run it against a
# throwaway instance, such as the one tests/e2e/run-docker.sh starts.
# Needs curl and python3.
set -uo pipefail
BASE=$1; CONTAINER=$2
. "$(dirname "$0")/lib.sh"

# --- setup ---------------------------------------------------------------
create_users
version=$(occ status --output=json | python3 -c 'import json,sys; print(json.load(sys.stdin)["versionstring"])')
dbtype=$(occ config:system:get dbtype)
echo "== Nextcloud $version on $dbtype"

check "a user uploads a file"                          "$(put_file $U sctest.txt hello)" 201
check "the user shares it"                             "$(share $U /sctest.txt $V)" 200

# --- OCC commands ----------------------------------------------------------
occ stats_collector:metrics --enable-all-collectors >/dev/null
check "occ enables every metric of every collector"   "$?" 0
occ stats_collector:configure --instance-label=e2e --retention-days=30 --cron-interval=hourly >/dev/null
check "occ configures the app"                         "$?" 0
check "occ status shows the instance label"            "$(occ stats_collector:status | grep -c 'Instance label: *e2e$')" 1
check "occ collect stores a snapshot"                  "$(occ stats_collector:collect | grep -c '^Snapshot stored.$')" 1
occ stats_collector:collect --preview | sed -n '/^{/,$p' > "$TMP/body"
check "occ collect --preview prints the payload"       "$(py 'd["instance_label"]')" e2e
settle

# --- admin API -------------------------------------------------------------
check "an admin lists the collectors"                  "$(req $A GET $APP/api/collectors)" 200
cp "$TMP/body" "$TMP/collectors.json"
check "a user can't list the collectors"               "$(req $U GET $APP/api/collectors)" 403
check "anonymous can't list the collectors"            "$(req - GET $APP/api/collectors)" 401

sleep 1 # snapshot files are named by the second
check "an admin collects now"                          "$(req $A POST $APP/api/stats/collect)" 200
problems=$(python3 - "$TMP/collectors.json" "$TMP/body" <<'EOF'
import json, sys
collectors = json.load(open(sys.argv[1]))
payload = json.load(open(sys.argv[2]))["collectors"]
tested, untested, problems = [], [], []
for c in collectors:
    if not c["available"]:
        untested.append(c["id"] + " (" + c["app_id"] + " not enabled)")
        continue
    tested.append(c["id"])
    values = payload.get(c["id"], {})
    for m in c["metrics"]:
        value = values.get(m["id"])
        # The database size is only measured on MySQL and PostgreSQL
        if value is None and not (m["id"] == "db_size_bytes" and values.get("db_type") == "sqlite3"):
            problems.append(c["id"] + "." + m["id"])
print("collectors tested: " + " ".join(tested), file=sys.stderr)
if untested:
    print("collectors not tested: " + ", ".join(untested), file=sys.stderr)
print(" ".join(problems))
EOF
)
check "every metric of every available collector has a value" "$problems" ""
check "the users collector counts the test users"      "$(py 'int(d["collectors"]["users"]["total_users"] >= 4)')" 1
check "the users collector counts logged in users"     "$(py 'int(d["collectors"]["users"]["active_users_24h"] >= 2)')" 1
check "the files collector counts the uploaded file"   "$(py 'int(d["collectors"]["files"]["total_files"] >= 1)')" 1
check "the shares collector counts the share"          "$(py 'd["collectors"]["shares"]["shares_user"]')" 1
check "the system collector lists this app"            "$(py 'int("stats_collector" in d["collectors"]["system"]["installed_apps"])')" 1
check "the system collector has the Nextcloud version" "$(py 'int(d["collectors"]["system"]["nc_version"].startswith("'"$version"'"))')" 1

check "an admin reads the settings"                    "$(req $A GET $APP/api/settings)" 200
check "the settings have the label set with occ"       "$(py 'd["instance_label"] + " " + d["snapshot_retention_days"]')" "e2e 30"
check "an admin changes the settings" \
	"$(req_json $A PUT $APP/api/settings '{"instance_label":"e2e-web","cron_interval":"daily","chart_colors":["#112233"],"allowed_groups":[]}')" 200
req $A GET $APP/api/settings >/dev/null
check "the changed settings are stored"                "$(py 'd["instance_label"] + " " + d["cron_interval"] + " " + d["chart_colors"][0]')" "e2e-web daily #112233"
req_json $A PUT $APP/api/settings '{"cron_interval":"every-second"}' >/dev/null
req $A GET $APP/api/settings >/dev/null
check "an unknown cron interval is ignored"            "$(py 'd["cron_interval"]')" daily
check "an admin searches groups"                       "$(req $A GET "$APP/api/settings/groups?search=sctest")" 200
check "the group search finds the test group"          "$(py '",".join(g["id"] for g in d)')" $G

check "an admin picks the metrics of a collector" \
	"$(req_json $A PUT $APP/api/collectors/users/metrics '{"metrics":["total_users","no_such_metric"]}')" 200
check "unknown metrics are dropped"                    "$(py '",".join(d["enabled_metrics"])')" total_users
occ stats_collector:metrics users --enable-all >/dev/null

check "an admin creates an API key"                    "$(req_json $A POST $APP/api/keys '{"label":"e2e-web"}')" 200
web_key=$(py 'd["key"]'); web_key_id=$(py 'd["id"]')
check "the API key has 64 characters"                  "${#web_key}" 64
req $A GET $APP/api/keys >/dev/null
check "the key list shows the key, but not encrypted"  "$(py 'int(any(k["label"] == "e2e-web" for k in d) and all("key_encrypted" not in k for k in d))')" 1
check "the key from the admin API can pull"            "$(pull "$web_key" snapshots/latest)" 200
check "an admin revokes the key"                       "$(req $A DELETE "$APP/api/keys/$web_key_id")" 200

check "an admin reads the dashboard"                   "$(req $A GET $APP/api/stats/dashboard)" 200
check "the dashboard has the latest snapshot"          "$(py 'int("users" in d["collectors"])')" 1
check "an admin lists snapshots"                       "$(req $A GET "$APP/api/stats/snapshots?limit=1")" 200
check "the snapshot list honours the limit"            "$(py 'len(d)')" 1
check "an admin opens the admin settings"              "$(req $A GET /index.php/settings/admin/stats_collector -H 'Accept: text/html')" 200
check "the admin settings page has the app's mount point" "$(grep -c 'id="stats-collector-admin"' "$TMP/body")" 1

# --- personal dashboard ----------------------------------------------------
check "a user outside the allowed groups gets no dashboard" "$(req $U GET $APP/api/personal/dashboard)" 403
check "the viewer gets no dashboard before the group is allowed" "$(req $V GET $APP/api/personal/dashboard)" 403
occ stats_collector:groups add $G >/dev/null
check "occ allows the viewer group"                    "$?" 0
settle
check "occ lists the allowed group"                    "$(occ stats_collector:groups list | grep -c " $G ")" 1
check "a member of an allowed group gets the dashboard" "$(req $V GET $APP/api/personal/dashboard)" 200
check "the dashboard has the collected stats"          "$(py 'int("users" in d["collectors"])')" 1
check "an admin always gets the dashboard"             "$(req $A GET $APP/api/personal/dashboard)" 200
check "the viewer gets the history"                    "$(req $V GET "$APP/api/personal/history?range=24h")" 200
check "the history has the snapshots, numbers only" \
	"$(py 'int(d["count"] >= 2 and all(isinstance(v, (int, float)) for s in d["snapshots"] for c in s["collectors"].values() for v in c.values()))')" 1

check "the viewer saves preferences" "$(req_json $V PUT $APP/api/personal/preferences \
	'{"density":"compact","hidden_sections":["talk"],"hero_pinned":["users.total_users","a.b","c.d","e.f","g.h"],"default_spotlight":"not a key"}')" 200
check "the viewer reads the preferences"               "$(req $V GET $APP/api/personal/preferences)" 200
check "the preferences are stored and validated" \
	"$(py 'd["density"] + " " + ",".join(d["hidden_sections"]) + " " + str(len(d["hero_pinned"])) + " [" + d["default_spotlight"] + "]"')" "compact talk 4 []"
req_json $V PUT $APP/api/personal/preferences '{}' >/dev/null
req $V GET $APP/api/personal/preferences >/dev/null
check "empty preferences restore the defaults"         "$(py 'd["density"] + " " + str(len(d["hero_pinned"]))')" "comfortable 0"
check "a user outside the allowed groups has no preferences" "$(req $U GET $APP/api/personal/preferences)" 403

check "the viewer opens the dashboard page"            "$(req $V GET $APP/ -H 'Accept: text/html')" 200
check "the page has the app's mount point"             "$(grep -c 'id="stats-collector-personal"' "$TMP/body")" 1
if docker exec "$CONTAINER" test -f /var/www/html/custom_apps/stats_collector/js/stats_collector-personal-settings.js; then
	script=$(grep -o 'src="[^"]*stats_collector-personal-settings[^"]*"' "$TMP/body" | head -1 | cut -d'"' -f2 | sed 's/&amp;/\&/g')
	check "the page's script is served"                "$(req $V GET "$script")" 200
else
	echo "NOTICE js/ is not built, the page's script is not checked (npm ci && npm run build)"
fi
check "the viewer has the navigation entry"            "$(in_navigation $V)" 1
check "a user outside the allowed groups has no navigation entry" "$(in_navigation $U)" 0

# --- pull API --------------------------------------------------------------
key=$(occ stats_collector:api-key create --label e2e --quiet-key)
check "occ creates an API key of 64 characters"        "${#key}" 64
check "occ lists the API key"                          "$(occ stats_collector:api-key list | grep -c ' e2e ')" 1
settle
check "the key lists the snapshots"                    "$(pull "$key" snapshots)" 200
check "the list has the snapshots and the label"       "$(py 'str(int(d["count"] >= 2)) + " " + d["instance_label"]')" "1 e2e-web"
snapshot=$(py 'd["snapshots"][0]["filename"]')
pull "$key" 'snapshots?include_payload=true&limit=1' >/dev/null
check "include_payload adds the payload"               "$(py 'int(len(d["snapshots"]) == 1 and "users" in d["snapshots"][0]["payload"]["collectors"])')" 1
pull "$key" 'snapshots?from=2000-01-01&to=2000-01-02' >/dev/null
check "a date range without snapshots is empty"        "$(py 'd["count"]')" 0
check "a non-numeric limit is refused"                 "$(pull "$key" 'snapshots?limit=abc')" 400
check "a malformed date is refused"                    "$(pull "$key" 'snapshots?from=yesterday')" 400
check "the key gets the latest snapshot"               "$(pull "$key" snapshots/latest)" 200
check "the latest snapshot has the collectors"         "$(py 'int("users" in d["collectors"])')" 1
check "the key gets a snapshot by name"                "$(pull "$key" "snapshots/$snapshot")" 200
etag=$(header etag)
check "a snapshot by name has an ETag"                 "$(echo "$etag" | grep -c '^"[0-9a-f]\{32\}"$')" 1
check "an unchanged snapshot is not sent again"        "$(pull "$key" "snapshots/$snapshot" -H "If-None-Match: $etag")" 304
check "an unknown snapshot is not found"               "$(pull "$key" snapshots/snapshot_2000-01-01_00-00-00.json)" 404
check "a name outside the snapshot pattern is not found" "$(pull "$key" snapshots/nextcloud.log)" 404

byo_key=$(python3 -c 'import secrets; print(secrets.token_hex(32))')
occ stats_collector:api-key create --label e2e-byo --key "$byo_key" >/dev/null
check "occ registers a key of your own"                "$?" 0
settle
check "the key of your own can pull"                   "$(pull "$byo_key" snapshots/latest)" 200

# --- background job --------------------------------------------------------
pull "$key" 'snapshots?limit=1000' >/dev/null
before=$(py 'd["count"]')
job=$(occ background-job:list --class='OCA\StatsCollector\BackgroundJob\CollectAndForwardJob' --output=json |
	python3 -c 'import json,sys; print(json.load(sys.stdin)[0]["id"])')
sleep 1
occ background-job:execute "$job" --force-execute >/dev/null
check "the background job runs"                        "$?" 0
pull "$key" 'snapshots?limit=1000' >/dev/null
check "the background job stores a snapshot"          "$(py 'd["count"]')" $((before + 1))

# --- refused keys ------------------------------------------------------------
# Last, because every refused key slows down the next pull requests (brute force protection)
check "a key revoked through the admin API can't pull" "$(pull "$web_key" snapshots/latest)" 401
occ stats_collector:api-key revoke --label e2e-byo >/dev/null
settle
check "a key revoked with occ can't pull"              "$(pull "$byo_key" snapshots/latest)" 401
check "a request without a key is refused"             "$(req - GET $APP/api/v1/snapshots/latest)" 401
check "a malformed key is refused"                     "$(pull not-a-key snapshots/latest)" 401

# --- reset -------------------------------------------------------------------
check "occ reset removes the settings"                 "$(occ stats_collector:reset --yes | grep -c '^Removed [1-9][0-9]* setting(s).$')" 1
settle
req $A GET $APP/api/settings >/dev/null
check "the settings are back to the defaults"          "$(py '"[" + d["instance_label"] + "] " + d["cron_interval"]')" "[] hourly"
check "the viewer group is no longer allowed"          "$(req $V GET $APP/api/personal/dashboard)" 403
check "the API keys are removed"                       "$(pull "$key" snapshots/latest)" 401

summary
