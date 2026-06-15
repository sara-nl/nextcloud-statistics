# Security Documentation — Stats Collector

## Overview

Stats Collector is designed for multi-instance Nextcloud monitoring. Remote instances collect statistics and forward them to a central instance via an authenticated API. This document describes the security model, data handling, and hardening measures.

## Authentication

### API Key Authentication (Receive Endpoint)

The only public endpoint is `POST /ocs/v2.php/apps/stats_collector/api/receive`. All other endpoints require Nextcloud admin authentication.

- API keys are 64-character hex strings generated with `random_bytes(32)`
- Keys are stored **encrypted at rest** using Nextcloud's `ICrypto` (AES-CBC with the instance secret)
- Keys are transmitted via the `Authorization: Bearer <key>` HTTP header only — query parameter authentication is not supported to prevent key leakage in server logs and Referer headers
- Key validation uses `hash_equals()` for timing-safe comparison
- Key IDs use `random_bytes(8)` (unpredictable, not `uniqid()`)
- The plaintext key is shown exactly once upon creation and never stored or retrievable

### Admin Authentication (All Other Endpoints)

All admin API routes extend Nextcloud's `Controller` class, which enforces:
- Nextcloud session authentication (logged-in admin user)
- CSRF token validation on all state-changing requests
- Admin group membership check via Nextcloud middleware

### OCC Commands

OCC commands run as the web server user (`www-data`) and require shell access to the server. They use the same `ICrypto` encryption for sensitive values as the web interface.

## Credential Storage

All sensitive credentials are encrypted at rest using Nextcloud's `ICrypto`:

| Credential | Storage Key | Encrypted |
|---|---|---|
| Stats Collector API key (sender) | `sc_api_key` | Yes |
| HTTP endpoint auth value (bearer/basic) | `endpoint_auth_value` | Yes |
| WebDAV password | `nc_password` | Yes |
| API keys (receiver, per remote instance) | `api_keys` (JSON array, `key_encrypted` field) | Yes |

Credentials are encrypted consistently whether set via the admin UI, `occ stats_collector:configure`, or `occ stats_collector:setup`.

API responses mask all sensitive fields as `********`. The actual values are never returned to the frontend.

A `decryptValue()` fallback exists for legacy plaintext values from before encryption was implemented. These are decrypted transparently and will be re-encrypted upon the next save.

## Network Security

### Receive Endpoint

- **Payload size limit**: Maximum 1 MB per request, enforced before JSON parsing
- **Payload validation**: Required fields (`instance_id`, `timestamp`, `collectors`) are validated. Invalid timestamps are rejected.
- **Error handling**: Generic error messages are returned to callers. Internal exception details are logged server-side only, never exposed.
- **Rate limiting**: Nextcloud's built-in brute-force protection applies to failed authentication attempts.

### Forwarding (Sender Side)

- Supports three forwarding methods: Stats Collector API, HTTP POST, and Nextcloud WebDAV
- All outgoing requests use Nextcloud's `IClientService` HTTP client with a 30-second timeout
- The background job includes retry logic (up to 3 attempts) with increasing delays, but does not retry on 4xx client errors (except 429)

### Logo Proxy

The `/api/settings/logo` endpoint proxies an external image URL server-side to avoid CORS issues in the browser. This endpoint:
- Is admin-only (requires Nextcloud admin authentication)
- Fetches the URL configured in `chart_logo_url` (admin-set only)
- Uses Nextcloud's `IClientService` with a 10-second timeout
- Note: This endpoint can reach internal network addresses if an admin configures such a URL. This is acceptable as only admin users can set the logo URL.

## Data Storage

### Appdata Sandboxing

Received snapshots are stored in Nextcloud's appdata directory:

```
data/appdata_<instance_id>/stats_collector/<sanitized_instance_id>/stats_YYYY-MM-DD_HH-mm-ss.json
```

- Storage uses Nextcloud's `IAppData` interface, which is sandboxed to the app's own directory
- Instance IDs are sanitized with `preg_replace('/[^a-zA-Z0-9_-]/', '_', ...)` before use as folder names, preventing path traversal
- The same sanitization is applied on folder deletion
- A valid API key cannot access or modify data outside the `stats_collector` appdata folder
- No access to the Nextcloud filesystem, user files, or other apps' data

### Configuration Storage

All settings are stored in Nextcloud's `oc_appconfig` table under app ID `stats_collector`. Access to this table requires database-level access.

### Input Validation

- `endpoint_type` is validated against: `http`, `nextcloud`, `stats_collector`
- `cron_interval` is validated against: `5min`, `15min`, `hourly`, `daily`, `weekly`
- `endpoint_auth_type` is validated against: `none`, `bearer`, `basic`, `header`
- Invalid values are silently rejected (not stored)

## Frontend Security

- All user-controlled data is rendered via Vue's `{{ }}` text interpolation, which auto-escapes HTML (no XSS)
- No `v-html` directives are used anywhere in the codebase
- All API calls use `@nextcloud/axios` which automatically includes CSRF tokens
- The CSP policy allows `data:` and `blob:` image domains (required for chart rendering and export)

## SQL Injection Prevention

All database queries use either:
- Nextcloud's `QueryBuilder` with `createNamedParameter()`
- Parameterized queries with `?` placeholders and bound parameters

No raw string concatenation is used in SQL queries.

## Scope of Access

A summary of what each authentication level can do:

| Actor | Can Do | Cannot Do |
|---|---|---|
| **Remote instance (API key)** | Send JSON stats to receive endpoint | Access files, other apps, admin functions, user data, or any other API |
| **Admin user (web UI)** | Configure settings, manage API keys, view stats, export charts | Access other users' files (beyond what Nextcloud admin already allows) |
| **OCC commands (shell)** | All of the above, plus reset all data | N/A (shell access implies full server access) |

## Recommendations for Deployment

1. **Use HTTPS** for all communication between instances
2. **Rotate API keys** periodically — revoke old keys and generate new ones
3. **Limit admin access** — only trusted administrators should have access to the Stats Collector settings
4. **Monitor forwarding history** — the History tab shows all forwarding attempts, including failures
5. **Review collected metrics** — the Collectors tab lets you disable metrics you don't want to collect or forward
