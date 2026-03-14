#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="/opt/kloudboy"
SITES_DIR="/home/sites"
BACKUPS_DIR="/backups"
LOGS_DIR="/logs"

echo "[kloudboy] installer foundation"
echo "[kloudboy] target root: ${ROOT_DIR}"

mkdir -p "${ROOT_DIR}" "${SITES_DIR}" "${BACKUPS_DIR}" "${LOGS_DIR}"

cat <<'INFO'
This installer stub prepares the target directory layout described in the spec.

Planned production steps:
1. Install OpenLiteSpeed, MariaDB, Redis, and PHP LSAPI packages.
2. Copy the KloudBoy backend and frontend artifacts into /opt/kloudboy.
3. Create the systemd service and bootstrap the SQLite database.
4. Generate admin credentials and persist them to /root/kloudboy-login.txt.
5. Configure the firewall and panel SSL endpoint.
INFO
