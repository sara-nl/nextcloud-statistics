# Stats Collector

A Nextcloud app that collects per-instance statistics (users, files, shares, system, Talk, Deck, Mail, Calendar, Activity, Forms, Contacts, Richdocuments) and serves them over a public REST API for external monitoring systems to pull on demand. Also ships an admin dashboard and a per-group personal dashboard.

**Pull-only.** Each Nextcloud install is autonomous: it collects locally, stores snapshots in appdata, and exposes them on request. There is no central instance and no push-forward.

**Supported:** Nextcloud 30-33, PHP 8.1+.

## How it works

1. A background `TimedJob` runs on a configurable interval (5min / 15min / hourly / daily / weekly).
2. Each run loops over enabled collectors, gathers their metrics, and stores a JSON snapshot in Nextcloud appdata.
3. External monitoring systems authenticate with a Bearer API key and pull the latest or a date-range of snapshots.
4. Users in authorized groups can view a read-only personal dashboard inside Nextcloud.

## Install

### From source

```bash
git clone https://github.com/sara-nl/nextcloud-statistics.git stats_collector
cd stats_collector
npm install
npm run build
mv stats_collector /var/www/nextcloud/apps/
occ app:enable stats_collector
```

### From the App Store

(Not yet published; install from source.)

## Setup

After enabling the app, run the interactive wizard:

```bash
occ stats_collector:setup
```

Or configure non-interactively:

```bash
occ stats_collector:configure \
  --cron-interval=hourly \
  --instance-label="Production NC"

occ stats_collector:metrics --enable-all-collectors
occ stats_collector:collect   # test it once
```

## Generating an API key for the pull endpoint

External monitoring systems need a Bearer token. Generate one in the admin UI:

1. Go to **Admin settings -> Administration -> Stats Collector**.
2. Open the **API Keys** tab.
3. Click **Generate API key**, give it a label, and copy the key shown **once** (it is hashed at rest and cannot be recovered).
4. Use it with any HTTP client:

   ```bash
   curl -H "Authorization: Bearer YOUR_KEY" \
        https://nc.example.com/index.php/apps/stats_collector/api/v1/snapshots/latest
   ```

Keys can be revoked from the same tab. Brute-force protection is enabled on the pull endpoints.

## Automation for ops

Everything that can be done in the UI is also exposed via `occ` and the public REST API. Below are recipes for the patterns that come up most.

### Headless install + configure (no browser needed)

```bash
# 1) Install
git -C /var/www/nextcloud/apps clone https://github.com/sara-nl/nextcloud-statistics.git stats_collector
cd /var/www/nextcloud/apps/stats_collector
npm ci && npm run build
chown -R www-data:www-data /var/www/nextcloud/apps/stats_collector

OCC="sudo -u www-data php /var/www/nextcloud/occ"

# 2) Enable + configure
$OCC app:enable stats_collector
$OCC stats_collector:configure \
  --cron-interval=hourly \
  --instance-label="$(hostname -f)" \
  --retention-days=90

# 3) Pick metrics (see "Choosing metrics" below for the per-metric API)
$OCC stats_collector:metrics --enable-all-collectors

# 4) Sanity check
$OCC stats_collector:status
$OCC stats_collector:collect --preview | head -40
```

### Creating API keys non-interactively

API keys are generated server-side with `random_bytes(32)` and the plaintext is returned exactly once. Pipe `--quiet-key` straight into your secret store:

```bash
OCC="sudo -u www-data php /var/www/nextcloud/occ"

# Create — returns ONLY the plaintext key on stdout, ready to pipe
NEW_KEY=$($OCC stats_collector:api-key create --label="prometheus-prd" --quiet-key)
echo "$NEW_KEY" | vault kv put secret/monitoring/nc-statscollector key=-

# Or human-friendly output (full info, key shown once)
$OCC stats_collector:api-key create --label="grafana"

# List existing keys (preview prefix only)
$OCC stats_collector:api-key list

# Revoke by label or by id
$OCC stats_collector:api-key revoke --label="grafana"
$OCC stats_collector:api-key revoke --id="key_abc123..."
```

Labels must be unique. Create is idempotent at the label level: re-running with the same label exits with an error rather than silently issuing a duplicate.

### Choosing metrics

Three granularity levels, all non-interactive:

```bash
OCC="sudo -u www-data php /var/www/nextcloud/occ"

# All available collectors, all of their metrics (broadest)
$OCC stats_collector:metrics --enable-all-collectors

# One collector, all of its metrics
$OCC stats_collector:metrics users --enable-all
$OCC stats_collector:metrics talk --disable-all

# Specific metrics within a collector (comma-separated)
$OCC stats_collector:metrics users --enable=total_users,active_24h,active_7d
$OCC stats_collector:metrics files --enable=total_files,total_storage_bytes
$OCC stats_collector:metrics shares --disable=federated_shares
```

Discover available metric ids per collector:

```bash
$OCC stats_collector:metrics                    # list all collectors + how many active
$OCC stats_collector:metrics users --list       # list metric ids for the 'users' collector
```

`--enable` is additive (existing enabled metrics stay enabled). `--disable` removes ids from the active set. Unknown ids fail with a clear error and a hint to run `--list`.

A typical Ansible task to pin an exact metric set across a fleet:

```yaml
- name: Stats Collector — minimal Prometheus set
  command: >
    php /var/www/nextcloud/occ stats_collector:metrics {{ item.collector }}
      --disable-all
  become_user: www-data
  loop:
    - { collector: users }
    - { collector: files }
    - { collector: system }
  changed_when: false

- name: Stats Collector — enable picked metrics
  command: >
    php /var/www/nextcloud/occ stats_collector:metrics {{ item.collector }}
      --enable={{ item.metrics | join(',') }}
  become_user: www-data
  loop:
    - { collector: users,  metrics: [total_users, active_24h, active_7d] }
    - { collector: files,  metrics: [total_files, total_storage_bytes] }
    - { collector: system, metrics: [nc_version, php_version, db_size_bytes] }
  changed_when: true
```

This pattern (`disable-all` then `enable=<exact ids>`) is the safest way to keep config reproducible: it converges to the declared set regardless of prior state.

### Ansible role snippet

```yaml
- name: Enable Stats Collector
  command: php /var/www/nextcloud/occ app:enable stats_collector
  become_user: www-data
  changed_when: false

- name: Configure Stats Collector
  command: >
    php /var/www/nextcloud/occ stats_collector:configure
      --cron-interval={{ statscollector_cron_interval | default('hourly') }}
      --instance-label="{{ inventory_hostname }}"
      --retention-days={{ statscollector_retention_days | default(90) }}
  become_user: www-data
  changed_when: false

- name: Mint API key
  command: >
    php /var/www/nextcloud/occ stats_collector:api-key create
      --label="{{ statscollector_api_key_label | default('prometheus') }}"
      --quiet-key
  become_user: www-data
  register: sc_apikey
  no_log: true
  changed_when: true
  failed_when: sc_apikey.rc != 0 and "already exists" not in sc_apikey.stderr

- name: Push API key to vault
  community.hashi_vault.vault_write:
    path: "secret/data/monitoring/nc-statscollector/{{ inventory_hostname }}"
    data:
      data:
        key: "{{ sc_apikey.stdout }}"
  no_log: true
  when: sc_apikey.stdout | length > 0
```

Pass `statscollector_api_key` from `ansible-vault` or a HashiCorp Vault lookup.

### Kubernetes init/job sidecar

If your Nextcloud runs in k8s with a shared volume, run setup as a one-shot Job:

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: stats-collector-bootstrap
spec:
  template:
    spec:
      restartPolicy: OnFailure
      containers:
        - name: occ
          image: nextcloud:30-fpm
          command: ["/bin/sh", "-c"]
          args:
            - |
              set -e
              php occ app:enable stats_collector
              php occ stats_collector:configure --cron-interval=hourly --instance-label="$HOSTNAME"
              php occ stats_collector:metrics --enable-all-collectors
              php occ stats_collector:configure --add-api-key="$SC_API_KEY" --api-key-label="prometheus"
          env:
            - name: SC_API_KEY
              valueFrom:
                secretKeyRef: { name: stats-collector-secrets, key: api_key }
          volumeMounts:
            - { name: nc-data, mountPath: /var/www/html }
          securityContext:
            runAsUser: 33   # www-data
      volumes:
        - { name: nc-data, persistentVolumeClaim: { claimName: nextcloud-data } }
```

### Prometheus / monitoring pull

Hook the snapshot endpoint into whatever you already use:

```bash
# Healthcheck — fail if no snapshot in the last 2h
LAST=$(curl -fsS -H "Authorization: Bearer $SC_API_KEY" \
  https://nc.example.com/index.php/apps/stats_collector/api/v1/snapshots/latest \
  | jq -r '.timestamp')
AGE=$(( $(date -u +%s) - $(date -u -d "$LAST" +%s) ))
[ "$AGE" -lt 7200 ] || exit 1
```

For Prometheus, a thin exporter that calls `/snapshots/latest` and re-emits the JSON as gauges works well; ship one per environment.

### Cron interval reference

`--cron-interval` accepts: `5min`, `15min`, `hourly`, `daily`, `weekly`. Snapshots are written to Nextcloud appdata; retention is enforced before each collection.

## Public REST API

All endpoints return plain JSON (no OCS wrapper). Authentication is `Authorization: Bearer <key>`.

| Method | Path | Description |
|---|---|---|
| GET | `/index.php/apps/stats_collector/api/v1/snapshots` | List snapshot metadata. Query: `from`, `to` (ISO 8601), `include_payload=true` |
| GET | `/index.php/apps/stats_collector/api/v1/snapshots/latest` | Latest complete snapshot |
| GET | `/index.php/apps/stats_collector/api/v1/snapshots/{filename}` | Specific snapshot by filename |

Example:

```bash
curl -H "Authorization: Bearer YOUR_KEY" \
     "https://nc.example.com/index.php/apps/stats_collector/api/v1/snapshots?from=2026-06-01T00:00:00Z&to=2026-06-15T00:00:00Z&include_payload=true"
```

Snapshot retention defaults to 90 days; configurable via `--retention` on `stats_collector:configure` (0 = forever).

## Personal dashboard

Users in groups added to **Admin settings -> Stats Collector -> Access** see a top-level navigation entry "Stats Collector" inside Nextcloud. The dashboard is read-only, refreshes on the cron schedule, and supports per-user preferences (density, hidden sections, section order, pinned hero metrics, default spotlight metric).

Users without access do not see the navigation icon at all.

## OCC commands

| Command | Purpose |
|---|---|
| `stats_collector:setup` | Interactive setup wizard |
| `stats_collector:configure` | Non-interactive: set cron interval, label, retention, etc. |
| `stats_collector:status` | Show current config + enabled collectors + snapshot info |
| `stats_collector:metrics` | Enable/disable metrics per collector |
| `stats_collector:collect` | Manual run (`--preview` to inspect JSON, `--no-store` to skip persistence) |
| `stats_collector:reset` | Wipe all app config (`--yes` to skip prompt) |

## Collectors

| Collector | Required app | Examples |
|---|---|---|
| Users | core | Total, active (24h/7d/30d), disabled, per group |
| Files | core | Total files, storage bytes, mimetypes, created 24h |
| Shares | core | By type (user/group/link/email/federated/room) |
| System | core | NC + PHP version, DB type/size, installed apps |
| Talk | spreed | Rooms, messages, participants, calls |
| Deck | deck | Boards, cards, overdue, created 7d |
| Mail | mail | Accounts, messages, mailboxes |
| Calendar | calendar | Calendars, events, upcoming |
| Activity | activity | Total, today, by type, active users |
| Forms | forms | Forms, submissions, active, shared |
| Contacts | contacts | Address books, contacts |
| Richdocuments | richdocuments | Documents, conversions |

Collectors only run when their required app is installed. Add a new one by implementing `OCA\StatsCollector\Collector\ICollector` and registering it in `CollectorRegistry`.

## Development

```bash
npm install
npm run dev      # development build
npm run watch    # watch mode
npm run build    # production build
```

- Frontend: Vue 3 Options API, single-file components, ApexCharts for charts, inline Lucide-style SVG icons (no font icon dependencies).
- PHP namespace: `OCA\StatsCollector` (PSR-4 mapped to `lib/`).
- No PHP tests, no composer install needed (Nextcloud's autoloader handles it).

## Security

- API keys are stored encrypted at rest via `OCP\Security\ICrypto` and never returned in plaintext after creation.
- The public pull endpoints have brute-force protection (`#[BruteForceProtection]`) and strict input validation (ctype_digit on ids, ISO 8601 on dates, strict filename regex).
- Sessions are closed on pull requests to keep them stateless.
- Personal dashboard access is gated by group membership (admin can always see it).

Report security issues via the contact in [SECURITY.md](SECURITY.md).

## License

AGPL-3.0
