package app

import (
	"database/sql"
	"os"

	"github.com/kloudboy/panel/backend/internal/config"
	"github.com/kloudboy/panel/backend/internal/database"
	"github.com/kloudboy/panel/backend/internal/services"
)

type Application struct {
	Config        config.Config
	DB            *sql.DB
	SiteService   *services.SiteService
	BackupService *services.BackupService
	PHPService    *services.PHPService
	ServerService *services.ServerService
}

func Bootstrap() (*Application, error) {
	cfg := config.Load()
	if err := ensureDirectories(cfg); err != nil {
		return nil, err
	}

	db, err := database.Open(cfg)
	if err != nil {
		return nil, err
	}

	siteService := services.NewSiteService(db, cfg)
	backupService := services.NewBackupService(db, cfg)
	phpService := services.NewPHPService(db)
	serverService := services.NewServerService(db, cfg)

	return &Application{
		Config:        cfg,
		DB:            db,
		SiteService:   siteService,
		BackupService: backupService,
		PHPService:    phpService,
		ServerService: serverService,
	}, nil
}

func (a *Application) Close() error {
	if a.DB == nil {
		return nil
	}
	return a.DB.Close()
}

func ensureDirectories(cfg config.Config) error {
	paths := []string{
		cfg.DataDir,
		cfg.SitesRoot,
		cfg.BackupsRoot,
		cfg.LogsRoot,
		cfg.GeneratedRoot,
	}

	for _, path := range paths {
		if err := os.MkdirAll(path, 0o755); err != nil {
			return err
		}
	}

	return nil
}

