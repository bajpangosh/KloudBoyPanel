# Implementation Roadmap

## Coverage snapshot

### Implemented foundations

- Product structure for the Go backend, Vue frontend, installer script, and CLI.
- SQLite bootstrapping for `admins`, `sites`, `databases`, `backups`, `api_tokens`, and `server_settings`.
- Site creation flow that validates domains, derives names, prepares site directories, and records provisioning metadata.
- Backup flow that archives site files into timestamped `.tar.gz` bundles.
- Server status and dashboard overview endpoints for the frontend.
- Dashboard shell with all pages from the spec represented in navigation and page content.

### Stubbed for next phases

- OpenLiteSpeed vhost generation and service reloads.
- Real MariaDB database and user provisioning.
- SSL issuance and renewal.
- Per-site PHP handler installation and switching on the host machine.
- WordPress installation via WP-CLI.
- Redis, LSCache, Brotli, HTTP/3, and WooCommerce tuning automation.
- Fail2Ban, malware scanning, firewall integration, and multi-server agents.

## Recommended delivery order

1. Productionize Phase 1 provisioning.
2. Add authentication and token management.
3. Connect frontend forms to the API for create/list actions.
4. Add queueing and audit logs around privileged tasks.
5. Expand monitoring, security tooling, and remote backup integrations.

## Spec mapping

### Core system

- Goals, stack, architecture, and directory layout are represented in the codebase structure and configuration defaults.
- Panel access settings are captured in backend config and frontend environment variables.

### Dashboard and managers

- Every page listed in the spec has a routed frontend destination and curated content block.
- Dashboard widgets, quick actions, recent sites, alerts, and service health are wired to the overview API.

### Provisioning and operations

- Site creation, database metadata, backup metadata, PHP version changes, and server health endpoints are implemented as initial service methods.
- CLI commands mirror the spec's command list and reuse the same service layer as the API.

### Remaining work

- Security center, cron automation, update delivery, installer hardening, and multi-server orchestration remain planned rather than completed.

