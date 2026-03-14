# KloudBoy Panel

KloudBoy Panel is a WordPress-focused hosting control panel designed around OpenLiteSpeed, site isolation, and a lightweight operator experience. This repository now contains the first implementation foundation derived from the product spec in [kloudboy-panel-spec.md](./kloudboy-panel-spec.md).

## What exists today

- Go backend scaffold with Gin-based APIs, SQLite schema bootstrapping, site/database/backup services, and a `kloudboy` CLI entrypoint.
- Vue 3 + Tailwind frontend shell covering every dashboard area named in the spec.
- Shared configuration, dev tooling, and docs to keep implementation aligned with the original product definition.

## Repo layout

- [backend](./backend): API server, CLI, SQLite models, and service layer.
- [frontend](./frontend): Vue dashboard shell with routed pages and backend integration points.
- [docs/implementation-roadmap.md](./docs/implementation-roadmap.md): spec-to-implementation mapping and next milestones.
- [scripts/install.sh](./scripts/install.sh): installer foundation aligned to the Ubuntu/OpenLiteSpeed target environment.

## Quick start

### Frontend

```bash
cd frontend
npm install
npm run dev
```

### Backend

```bash
cd backend
go run ./cmd/server
```

The backend expects Go to be installed locally. The current workspace does not have a Go toolchain, so backend compilation could not be validated yet.

## Docker test flow

If you have Docker Desktop or Docker Engine available on your machine, you can run the panel without installing Go locally:

```bash
cd /Users/bajpangosh/Documents/GitHub/KloudBoyPanel
docker compose up --build
```

Then open:

- Frontend: `http://localhost:5173`
- Backend health: `http://localhost:8443/healthz`

Example API test:

```bash
curl -X POST http://localhost:8443/api/sites/create \
  -H 'Content-Type: application/json' \
  -d '{
    "domain":"example.com",
    "phpVersion":"8.3",
    "installWordpress":true,
    "enableRedis":true,
    "template":"standard-wordpress"
  }'
```

To stop the stack:

```bash
docker compose down
```

## Current scope

This first pass focuses on the platform foundation:

- project structure
- schema bootstrapping
- API and CLI surfaces
- dashboard information architecture
- implementation roadmap

Privileged server automation such as OpenLiteSpeed vhost writes, MariaDB provisioning, Redis toggling, SSL issuance, and installer hardening are modeled but not fully productionized yet.
