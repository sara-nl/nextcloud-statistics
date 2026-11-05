# Helpers for the end-to-end tests, sourced by e2e.sh and upgrade.sh.
# Expects BASE (the Nextcloud url) and CONTAINER (the Nextcloud container) to be set.
# Kept compatible with bash 3.2, the bash of macOS.

PW='Sc-e2e-Pw-2026-Qm7!'
A=sctestadmin   # member of the admin group
V=sctestviewer  # member of the group that is allowed to see the personal dashboard
U=sctestuser    # no special rights
G=sctest-viewers
APP=/index.php/apps/stats_collector
pass=0; fail=0
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

occ() { docker exec -e OC_PASS="$PW" -u www-data "$CONTAINER" php occ "$@"; }
# The web server caches app config for 3 seconds (AppConfig::LOCAL_CACHE_TTL), so it only
# sees a setting changed with occ after that
settle() { sleep 4; }
check() { if [ "$2" = "$3" ]; then echo "PASS  $1"; pass=$((pass+1)); else echo "FAIL  $1 (expected '$3', got '$2')"; fail=$((fail+1)); fi; }

# Request <path> as <user>, or without login for '-'. Prints the http status; the body is
# saved in $TMP/body and the response headers in $TMP/headers.
req() { # <user> <method> <path> [curl args]
	local user=$1 method=$2 path=$3; shift 3
	if [ "$user" != - ]; then set -- -u "$user:$PW" "$@"; fi
	curl -s -o "$TMP/body" -D "$TMP/headers" -w '%{http_code}' \
		-H 'OCS-APIRequest: true' -H 'Accept: application/json' -X "$method" "$@" "$BASE$path"
}
# Same, with a JSON request body
req_json() { # <user> <method> <path> <json>
	req "$1" "$2" "$3" -H 'Content-Type: application/json' --data "$4"
}
# Request to the pull API with <key> as Bearer token
pull() { # <key> <path below api/v1> [curl args]
	local key=$1 path=$2; shift 2
	req - GET "$APP/api/v1/$path" -H "Authorization: Bearer $key" "$@"
}
# Prints a python expression evaluated on the JSON body of the last request, bound to d
py() { python3 -c "import json,sys; d=json.load(open(sys.argv[1])); print($1)" "$TMP/body" 2>/dev/null || echo '<no json>'; }
header() { tr -d '\r' < "$TMP/headers" | awk -v h="$(echo "$1" | tr 'A-Z' 'a-z')" 'tolower($1) == h":" { print $2 }'; }
# 1 when <user> has this app in the navigation of a page, else 0. Read from the page and not
# from the OCS navigation API, because OCS requests boot the apps before the login.
in_navigation() { # <user>
	req "$1" GET /index.php/settings/user -H 'Accept: text/html' >/dev/null
	python3 -c 'import base64, json, re, sys
state = re.search(r"id=\"initial-state-core-apps\" value=\"([^\"]+)\"", open(sys.argv[1]).read())
apps = json.loads(base64.b64decode(state.group(1))) if state else []
print(int(any(app["id"] == "stats_collector" for app in apps)))' "$TMP/body"
}

put_file() { # <user> <path> <content>, prints the http status
	curl -s -o /dev/null -w '%{http_code}' -u "$1:$PW" -X PUT --data-binary "$3" "$BASE/remote.php/dav/files/$1/$2"
}
share() { # <owner> <path> <recipient>, prints the OCS status code
	curl -s -u "$1:$PW" -H 'OCS-APIRequest: true' -H 'Accept: application/json' -X POST \
		-d "path=$2" -d shareType=0 -d "shareWith=$3" \
		"$BASE/ocs/v2.php/apps/files_sharing/api/v1/shares" |
		python3 -c 'import json,sys; print(json.load(sys.stdin)["ocs"]["meta"]["statuscode"])'
}

# Deletes and creates the test users and group, so every run starts clean
create_users() {
	occ group:delete $G >/dev/null 2>&1
	for u in $A $V $U; do
		occ user:delete $u >/dev/null 2>&1
		occ user:add --password-from-env --display-name="$u" $u >/dev/null || { echo "Could not create user $u" >&2; exit 1; }
	done
	occ group:add $G >/dev/null
	occ group:adduser $G $V
	occ group:adduser admin $A
}

summary() {
	echo "== $pass passed, $fail failed"
	[ "$fail" = 0 ]
}
