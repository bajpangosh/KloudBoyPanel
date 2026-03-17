package services

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"

	"github.com/kloudboy/panel/backend/internal/config"
	"github.com/kloudboy/panel/backend/internal/models"
)

var databaseNamePattern = regexp.MustCompile(`^[a-z0-9_]+$`)

type DatabaseService struct {
	db  *sql.DB
	cfg config.Config
}

type CreateDatabaseInput struct {
	Name       string `json:"name"`
	SiteDomain string `json:"siteDomain"`
	Username   string `json:"username"`
}

func NewDatabaseService(db *sql.DB, cfg config.Config) *DatabaseService {
	return &DatabaseService{db: db, cfg: cfg}
}

func (s *DatabaseService) CreateDatabase(input CreateDatabaseInput) (*models.DatabaseCreateResult, error) {
	name, username, siteID, err := s.resolveDatabaseIdentity(input)
	if err != nil {
		return nil, err
	}

	filePath := filepath.Join(s.cfg.DatabasesRoot, fmt.Sprintf("%s.db", name))
	if err := os.MkdirAll(s.cfg.DatabasesRoot, 0o755); err != nil {
		return nil, err
	}

	if _, err := os.Stat(filePath); err == nil {
		return nil, fmt.Errorf("%w: database file already exists for %q", ErrInvalidInput, name)
	}

	sqliteDB, err := sql.Open("sqlite", filePath)
	if err != nil {
		return nil, err
	}
	defer sqliteDB.Close()

	setupStatements := []string{
		`PRAGMA journal_mode = WAL;`,
		`PRAGMA foreign_keys = ON;`,
		`CREATE TABLE IF NOT EXISTS kloudboy_metadata (
			key TEXT PRIMARY KEY,
			value TEXT NOT NULL
		);`,
	}
	for _, statement := range setupStatements {
		if _, err := sqliteDB.Exec(statement); err != nil {
			_ = os.Remove(filePath)
			return nil, err
		}
	}

	now := time.Now().UTC().Format(time.RFC3339)
	result, err := s.db.Exec(
		`INSERT INTO databases (site_id, name, username, engine, status, last_backup_at, created_at, updated_at)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
		nullableSiteID(siteID),
		name,
		username,
		"sqlite",
		"ready",
		"",
		now,
		now,
	)
	if err != nil {
		_ = os.Remove(filePath)
		return nil, err
	}

	databaseID, _ := result.LastInsertId()
	record := models.DatabaseRecord{
		ID:           databaseID,
		SiteID:       siteID,
		Name:         name,
		Username:     username,
		Engine:       "sqlite",
		Status:       "ready",
		CreatedAt:    now,
		UpdatedAt:    now,
		LastBackupAt: "",
	}

	return &models.DatabaseCreateResult{
		Database:     record,
		DatabasePath: filePath,
		Message:      fmt.Sprintf("Created SQLite database %s", name),
	}, nil
}

func (s *DatabaseService) resolveDatabaseIdentity(input CreateDatabaseInput) (string, string, int64, error) {
	siteDomain := normalizeDomain(input.SiteDomain)
	name := sanitizeDatabaseIdentifier(input.Name)
	username := sanitizeDatabaseIdentifier(input.Username)
	var siteID int64

	if siteDomain != "" {
		site, err := NewSiteService(s.db, s.cfg).GetSiteByDomain(siteDomain)
		if err != nil {
			if err == sql.ErrNoRows {
				return "", "", 0, fmt.Errorf("%w: unknown site %q", ErrInvalidInput, input.SiteDomain)
			}
			return "", "", 0, err
		}
		siteID = site.ID

		if name == "" {
			base := sanitizeDatabaseIdentifier(strings.ReplaceAll(siteDomain, ".", "_"))
			name = base + "_db"
		}
		if username == "" {
			base := sanitizeDatabaseIdentifier(strings.ReplaceAll(siteDomain, ".", "_"))
			username = base + "_usr"
		}
	}

	if name == "" {
		return "", "", 0, fmt.Errorf("%w: database name is required", ErrInvalidInput)
	}
	if username == "" {
		username = name + "_usr"
	}

	name, err := s.makeUniqueDatabaseName(limitIdentifier(name, 32))
	if err != nil {
		return "", "", 0, err
	}
	username = limitIdentifier(username, 32)
	if !databaseNamePattern.MatchString(name) || !databaseNamePattern.MatchString(username) {
		return "", "", 0, fmt.Errorf("%w: only lowercase letters, numbers, and underscores are allowed", ErrInvalidInput)
	}

	return name, username, siteID, nil
}

func (s *DatabaseService) makeUniqueDatabaseName(base string) (string, error) {
	candidate := base
	for suffix := 1; suffix < 1000; suffix++ {
		var count int
		if err := s.db.QueryRow(`SELECT COUNT(1) FROM databases WHERE name = ?`, candidate).Scan(&count); err != nil {
			return "", err
		}
		if count == 0 {
			return candidate, nil
		}

		next := fmt.Sprintf("%s_%d", base, suffix)
		candidate = limitIdentifier(next, 32)
	}

	return "", fmt.Errorf("could not find an available database name for %q", base)
}

func sanitizeDatabaseIdentifier(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	replacer := strings.NewReplacer(" ", "_", "-", "_", ".", "_")
	value = replacer.Replace(value)

	builder := strings.Builder{}
	for _, char := range value {
		if (char >= 'a' && char <= 'z') || (char >= '0' && char <= '9') || char == '_' {
			builder.WriteRune(char)
		}
	}

	return strings.Trim(builder.String(), "_")
}

func limitIdentifier(value string, max int) string {
	if len(value) <= max {
		return value
	}
	return strings.TrimRight(value[:max], "_")
}

func nullableSiteID(siteID int64) any {
	if siteID == 0 {
		return nil
	}
	return siteID
}
