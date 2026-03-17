package config

import (
	"fmt"
	"os"
	"path/filepath"
	"strconv"
)

type Config struct {
	Environment     string
	HTTPHost        string
	HTTPPort        int
	DataDir         string
	DatabasePath    string
	DatabasesRoot   string
	SitesRoot       string
	BackupsRoot     string
	LogsRoot        string
	GeneratedRoot   string
	StaticDir       string
	AuthSecret      string
	BootstrapEmail  string
	PanelDomain     string
	PanelPort       int
	PanelHiddenPath string
	Timezone        string
}

func Load() Config {
	dataDir := env("KLOUDBOY_DATA_DIR", "./data")

	return Config{
		Environment:     env("KLOUDBOY_ENV", "development"),
		HTTPHost:        env("KLOUDBOY_HTTP_HOST", "0.0.0.0"),
		HTTPPort:        envInt("KLOUDBOY_HTTP_PORT", 8443),
		DataDir:         dataDir,
		DatabasePath:    env("KLOUDBOY_DB_PATH", filepath.Join(dataDir, "kloudboy.db")),
		DatabasesRoot:   env("KLOUDBOY_DATABASES_ROOT", filepath.Join(dataDir, "databases")),
		SitesRoot:       env("KLOUDBOY_SITES_ROOT", filepath.Join(dataDir, "sites")),
		BackupsRoot:     env("KLOUDBOY_BACKUPS_ROOT", filepath.Join(dataDir, "backups")),
		LogsRoot:        env("KLOUDBOY_LOGS_ROOT", filepath.Join(dataDir, "logs")),
		GeneratedRoot:   env("KLOUDBOY_GENERATED_ROOT", filepath.Join(dataDir, "generated")),
		StaticDir:       env("KLOUDBOY_STATIC_DIR", ""),
		AuthSecret:      env("KLOUDBOY_AUTH_SECRET", ""),
		BootstrapEmail:  env("KLOUDBOY_BOOTSTRAP_ADMIN_EMAIL", "admin@kloudboy.local"),
		PanelDomain:     env("KLOUDBOY_PANEL_DOMAIN", "panel.example.com"),
		PanelPort:       envInt("KLOUDBOY_PANEL_PORT", 8443),
		PanelHiddenPath: env("KLOUDBOY_PANEL_HIDDEN_PATH", "/kb-admin-demo/"),
		Timezone:        env("KLOUDBOY_TIMEZONE", "UTC"),
	}
}

func (c Config) Address() string {
	return fmt.Sprintf("%s:%d", c.HTTPHost, c.HTTPPort)
}

func (c Config) PanelBasePath() string {
	path := c.PanelHiddenPath
	if path == "" || path == "/" {
		return "/"
	}
	if path[0] != '/' {
		path = "/" + path
	}
	if path[len(path)-1] != '/' {
		path += "/"
	}
	return path
}

func env(key string, fallback string) string {
	if value, ok := os.LookupEnv(key); ok && value != "" {
		return value
	}
	return fallback
}

func envInt(key string, fallback int) int {
	raw, ok := os.LookupEnv(key)
	if !ok || raw == "" {
		return fallback
	}

	value, err := strconv.Atoi(raw)
	if err != nil {
		return fallback
	}
	return value
}
