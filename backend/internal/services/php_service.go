package services

import (
	"database/sql"
	"fmt"
	"time"

	"github.com/kloudboy/panel/backend/internal/models"
)

var supportedPHPVersions = map[string]struct{}{
	"8.1": {},
	"8.2": {},
	"8.3": {},
}

type PHPService struct {
	db *sql.DB
}

type ChangePHPInput struct {
	Domain     string `json:"domain"`
	PHPVersion string `json:"phpVersion"`
}

func NewPHPService(db *sql.DB) *PHPService {
	return &PHPService{db: db}
}

func (s *PHPService) ChangeVersion(input ChangePHPInput) (*models.Site, error) {
	if normalizeDomain(input.Domain) == "" {
		return nil, fmt.Errorf("%w: domain is required", ErrInvalidInput)
	}
	if _, ok := supportedPHPVersions[input.PHPVersion]; !ok {
		return nil, fmt.Errorf("%w: unsupported PHP version %q", ErrInvalidInput, input.PHPVersion)
	}

	result, err := s.db.Exec(
		`UPDATE sites SET php_version = ?, updated_at = ? WHERE domain = ?`,
		input.PHPVersion,
		time.Now().UTC().Format(time.RFC3339),
		normalizeDomain(input.Domain),
	)
	if err != nil {
		return nil, err
	}

	affected, _ := result.RowsAffected()
	if affected == 0 {
		return nil, sql.ErrNoRows
	}

	row := s.db.QueryRow(
		`SELECT id, domain, system_user, php_version, path, public_path, logs_path, database_name, database_user, status, ssl_status, redis_enabled, staging_domain, disk_usage_mb, cpu_usage_percent, provisioning_notes, created_at, updated_at
		 FROM sites
		 WHERE domain = ?`,
		normalizeDomain(input.Domain),
	)
	return scanSite(row)
}
