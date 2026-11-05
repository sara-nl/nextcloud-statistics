#!/usr/bin/env bash
# Checks that the settings of an older version of the app survive the upgrade to this one.
#
# usage: tests/e2e/upgrade.sh before <base_url> <container>   (with the older version enabled)
#        tests/e2e/upgrade.sh after <base_url> <container>    (after occ upgrade to this checkout)
#
# "before" stores every kind of setting through the older version: app config, a user
# preference, an API key and a cached snapshot. "after" reads them back through this one.
# tests/e2e/run-docker.sh runs both when UPGRADE_FROM is set.
set -uo pipefail
PHASE=$1; BASE=$2; CONTAINER=$3
. "$(dirname "$0")/lib.sh"
# Fixed, so the "after" phase knows it. Only ever used on a throwaway instance.
KEY=$(printf 'e2e%061d' 1)

if [ "$PHASE" = before ]; then
	create_users
	occ stats_collector:configure --instance-label=upgrade --retention-days=45 --cron-interval=daily >/dev/null
	check "the old version stores settings"            "$?" 0
	occ stats_collector:metrics --enable-all-collectors >/dev/null
	occ stats_collector:groups add $G >/dev/null
	check "the old version allows the viewer group"    "$?" 0
	occ stats_collector:api-key create --label upgrade --key $KEY >/dev/null
	check "the old version stores an API key"          "$?" 0
	check "the old version collects"                   "$(occ stats_collector:collect | grep -c '^Snapshot stored.$')" 1
	settle
	check "the old version stores a user preference" \
		"$(req_json $V PUT $APP/api/personal/preferences '{"density":"dense"}')" 200
	summary
	exit
fi

echo "== After the upgrade to $(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$(dirname "$0")/../../appinfo/info.xml")"
check "the settings survive"                           "$(req $A GET $APP/api/settings)" 200
check "the settings have the old values" \
	"$(py 'd["instance_label"] + " " + d["snapshot_retention_days"] + " " + d["cron_interval"] + " " + ",".join(d["allowed_groups"])')" \
	"upgrade 45 daily $G"
check "the enabled metrics survive"                    "$(req $A GET $APP/api/collectors)" 200
check "every available collector keeps its metrics"    "$(py 'int(all(c["enabled_metrics"] for c in d if c["available"]))')" 1
check "the cached snapshot survives"                   "$(req $V GET $APP/api/personal/dashboard)" 200
check "the cached snapshot has the stats"              "$(py 'd["instance_label"] + " " + str(int("users" in d["collectors"]))')" "upgrade 1"
old_snapshot=$(py 'd["timestamp"]')
check "the user preference survives"                   "$(req $V GET $APP/api/personal/preferences)" 200
check "the user preference has the old value"          "$(py 'd["density"]')" dense
check "the API key survives"                           "$(pull $KEY snapshots/latest)" 200
check "the allowed group keeps its navigation entry"   "$(in_navigation $V)" 1

# Settings written by the old version must also be writable by the new one
sleep 1
check "the new version collects over the old snapshot" "$(occ stats_collector:collect | grep -c '^Snapshot stored.$')" 1
settle
req $V GET $APP/api/personal/dashboard >/dev/null
check "the dashboard has the new snapshot"             "$(py 'int(d["timestamp"] > "'"$old_snapshot"'")')" 1
check "the new version changes the old preference" \
	"$(req_json $V PUT $APP/api/personal/preferences '{"density":"compact"}')" 200
req $V GET $APP/api/personal/preferences >/dev/null
check "the changed preference is read back"            "$(py 'd["density"]')" compact
check "the new version changes the old settings" \
	"$(req_json $A PUT $APP/api/settings '{"instance_label":"upgraded","allowed_groups":[]}')" 200
req $A GET $APP/api/settings >/dev/null
check "the changed settings are read back"             "$(py 'd["instance_label"] + " [" + ",".join(d["allowed_groups"]) + "]"')" "upgraded []"
check "the viewer group is no longer allowed"          "$(req $V GET $APP/api/personal/dashboard)" 403
summary
