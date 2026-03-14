package database

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"

	"github.com/kloudboy/panel/backend/internal/config"
	_ "modernc.org/sqlite"
)

func Open(cfg config.Config) (*sql.DB, error) {
	if err := os.MkdirAll(filepath.Dir(cfg.DatabasePath), 0o755); err != nil {
		return nil, err
	}

	db, err := sql.Open("sqlite", cfg.DatabasePath)
	if err != nil {
		return nil, err
	}

	statements := []string{
		`PRAGMA foreign_keys = ON;`,
		schemaSQL,
	}

	for _, statement := range statements {
		if _, err := db.Exec(statement); err != nil {
			return nil, fmt.Errorf("database bootstrap failed: %w", err)
		}
	}

	if err := seedSettings(db, cfg); err != nil {
		return nil, err
	}

	return db, nil
}

const schemaSQL = `
CREATE TABLE IF NOT EXISTS admins (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	email TEXT NOT NULL UNIQUE,
	password_hash TEXT NOT NULL,
	role TEXT NOT NULL DEFAULT 'owner',
	created_at TEXT NOT NULL,
	updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sites (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	domain TEXT NOT NULL UNIQUE,
	system_user TEXT NOT NULL,
	php_version TEXT NOT NULL,
	path TEXT NOT NULL,
	public_path TEXT NOT NULL,
	logs_path TEXT NOT NULL,
	database_name TEXT NOT NULL,
	database_user TEXT NOT NULL,
	status TEXT NOT NULL,
	ssl_status TEXT NOT NULL,
	redis_enabled INTEGER NOT NULL DEFAULT 0,
	staging_domain TEXT,
	disk_usage_mb INTEGER NOT NULL DEFAULT 0,
	cpu_usage_percent REAL NOT NULL DEFAULT 0,
	provisioning_notes TEXT NOT NULL,
	created_at TEXT NOT NULL,
	updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS databases (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	site_id INTEGER,
	name TEXT NOT NULL UNIQUE,
	username TEXT NOT NULL,
	engine TEXT NOT NULL DEFAULT 'mariadb',
	status TEXT NOT NULL,
	last_backup_at TEXT,
	created_at TEXT NOT NULL,
	updated_at TEXT NOT NULL,
	FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS backups (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	site_id INTEGER,
	type TEXT NOT NULL,
	storage TEXT NOT NULL,
	path TEXT NOT NULL,
	status TEXT NOT NULL,
	size_bytes INTEGER NOT NULL DEFAULT 0,
	created_at TEXT NOT NULL,
	completed_at TEXT,
	FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS api_tokens (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL,
	token_prefix TEXT NOT NULL,
	scopes TEXT NOT NULL,
	last_used_at TEXT,
	expires_at TEXT,
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS server_settings (
	key TEXT PRIMARY KEY,
	value TEXT NOT NULL,
	updated_at TEXT NOT NULL
);
`

func seedSettings(db *sql.DB, cfg config.Config) error {
	settings := map[string]string{
		"panel_domain":      cfg.PanelDomain,
		"panel_port":        fmt.Sprintf("%d", cfg.PanelPort),
		"hidden_login_path": cfg.PanelHiddenPath,
		"backup_location":   cfg.BackupsRoot,
		"timezone":          cfg.Timezone,
	}

	for key, value := range settings {
		if _, err := db.Exec(
			`INSERT INTO server_settings (key, value, updated_at)
			 VALUES (?, ?, datetime('now'))
			 ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at`,
			key,
			value,
		); err != nil {
			return err
		}
	}

	return nil
}

