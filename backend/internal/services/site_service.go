package services

import (
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"

	"github.com/kloudboy/panel/backend/internal/config"
	"github.com/kloudboy/panel/backend/internal/models"
)

var domainPattern = regexp.MustCompile(`^[a-z0-9.-]+\.[a-z]{2,}$`)

type CreateSiteInput struct {
	Domain           string `json:"domain"`
	PHPVersion       string `json:"phpVersion"`
	InstallWordPress bool   `json:"installWordpress"`
	EnableRedis      bool   `json:"enableRedis"`
	Template         string `json:"template"`
}

type CreateSiteResult struct {
	Site     models.Site           `json:"site"`
	Database models.DatabaseRecord `json:"database"`
	Steps    []ProvisionStep       `json:"steps"`
}

type ProvisionStep struct {
	Name   string `json:"name"`
	Status string `json:"status"`
	Detail string `json:"detail"`
}

type SiteService struct {
	db  *sql.DB
	cfg config.Config
}

type rowScanner interface {
	Scan(dest ...any) error
}

func NewSiteService(db *sql.DB, cfg config.Config) *SiteService {
	return &SiteService{db: db, cfg: cfg}
}

func (s *SiteService) CreateSite(input CreateSiteInput) (*CreateSiteResult, error) {
	domain := normalizeDomain(input.Domain)
	if !domainPattern.MatchString(domain) {
		return nil, fmt.Errorf("%w: invalid domain %q", ErrInvalidInput, input.Domain)
	}

	if input.PHPVersion == "" {
		input.PHPVersion = "8.3"
	}
	if input.Template == "" {
		input.Template = "standard-wordpress"
	}

	exists, err := s.siteExists(domain)
	if err != nil {
		return nil, err
	}
	if exists {
		return nil, fmt.Errorf("site already exists: %s", domain)
	}

	now := time.Now().UTC().Format(time.RFC3339)
	systemUser := buildSystemUser(domain)
	databaseName, databaseUser := deriveDatabaseNames(domain)
	siteRoot := filepath.Join(s.cfg.SitesRoot, domain)
	publicPath := filepath.Join(siteRoot, "public_html")
	logsPath := filepath.Join(siteRoot, "logs")

	for _, path := range []string{siteRoot, publicPath, logsPath} {
		if err := os.MkdirAll(path, 0o755); err != nil {
			return nil, err
		}
	}

	planPath, note, err := s.writeProvisionPlan(domain, systemUser, input)
	if err != nil {
		return nil, err
	}

	if _, err := s.db.Exec(
		`INSERT INTO sites
		 (domain, system_user, php_version, path, public_path, logs_path, database_name, database_user, status, ssl_status, redis_enabled, staging_domain, disk_usage_mb, cpu_usage_percent, provisioning_notes, created_at, updated_at)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		domain,
		systemUser,
		input.PHPVersion,
		siteRoot,
		publicPath,
		logsPath,
		databaseName,
		databaseUser,
		"provisioned",
		"pending",
		input.EnableRedis,
		fmt.Sprintf("staging.%s", domain),
		0,
		0.0,
		note,
		now,
		now,
	); err != nil {
		return nil, err
	}

	site, err := s.GetSiteByDomain(domain)
	if err != nil {
		return nil, err
	}

	result, err := s.db.Exec(
		`INSERT INTO databases (site_id, name, username, engine, status, last_backup_at, created_at, updated_at)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
		site.ID,
		databaseName,
		databaseUser,
		"mariadb",
		"planned",
		"",
		now,
		now,
	)
	if err != nil {
		return nil, err
	}

	databaseID, _ := result.LastInsertId()
	record := models.DatabaseRecord{
		ID:           databaseID,
		SiteID:       site.ID,
		Name:         databaseName,
		Username:     databaseUser,
		Engine:       "mariadb",
		Status:       "planned",
		CreatedAt:    now,
		UpdatedAt:    now,
		LastBackupAt: "",
	}

	site.ProvisioningNote = fmt.Sprintf("%s (plan: %s)", note, planPath)

	steps := []ProvisionStep{
		{Name: "generate Linux user", Status: "planned", Detail: fmt.Sprintf("system user %s reserved", systemUser)},
		{Name: "create website directory", Status: "completed", Detail: publicPath},
		{Name: "set file permissions", Status: "completed", Detail: "folders 755, files to be managed during provisioning"},
		{Name: "create database", Status: "planned", Detail: databaseName},
		{Name: "create database user", Status: "planned", Detail: databaseUser},
		{Name: "generate OpenLiteSpeed vhost", Status: "planned", Detail: fmt.Sprintf("plan saved to %s", planPath)},
		{Name: "assign PHP handler", Status: "planned", Detail: fmt.Sprintf("PHP %s", input.PHPVersion)},
		{Name: "issue SSL certificate", Status: "pending", Detail: "Let's Encrypt integration not wired yet"},
		{Name: "install WordPress", Status: statusForToggle(input.InstallWordPress), Detail: "WP-CLI workflow reserved"},
		{Name: "enable Redis cache", Status: statusForToggle(input.EnableRedis), Detail: "Redis object cache hook reserved"},
	}

	return &CreateSiteResult{
		Site:     *site,
		Database: record,
		Steps:    steps,
	}, nil
}

func (s *SiteService) DeleteSite(domain string) (*models.ActionResponse, error) {
	domain = normalizeDomain(domain)
	if domain == "" {
		return nil, fmt.Errorf("%w: domain is required", ErrInvalidInput)
	}

	result, err := s.db.Exec(`UPDATE sites SET status = ?, updated_at = ? WHERE domain = ?`, "deleted", time.Now().UTC().Format(time.RFC3339), domain)
	if err != nil {
		return nil, err
	}

	affected, _ := result.RowsAffected()
	if affected == 0 {
		return nil, sql.ErrNoRows
	}

	return &models.ActionResponse{
		Message: fmt.Sprintf("Site %s marked as deleted. Files were left in place for safe recovery.", domain),
	}, nil
}

func (s *SiteService) ListSites() ([]models.Site, error) {
	rows, err := s.db.Query(
		`SELECT id, domain, system_user, php_version, path, public_path, logs_path, database_name, database_user, status, ssl_status, redis_enabled, staging_domain, disk_usage_mb, cpu_usage_percent, provisioning_notes, created_at, updated_at
		 FROM sites
		 ORDER BY created_at DESC`,
	)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	sites := make([]models.Site, 0)
	for rows.Next() {
		site, err := scanSite(rows)
		if err != nil {
			return nil, err
		}
		sites = append(sites, *site)
	}
	return sites, rows.Err()
}

func (s *SiteService) ListDatabases() ([]models.DatabaseRecord, error) {
	rows, err := s.db.Query(
		`SELECT id, site_id, name, username, engine, status, last_backup_at, created_at, updated_at
		 FROM databases
		 ORDER BY created_at DESC`,
	)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	databases := make([]models.DatabaseRecord, 0)
	for rows.Next() {
		record, err := scanDatabase(rows)
		if err != nil {
			return nil, err
		}
		databases = append(databases, *record)
	}
	return databases, rows.Err()
}

func (s *SiteService) GetSiteByDomain(domain string) (*models.Site, error) {
	row := s.db.QueryRow(
		`SELECT id, domain, system_user, php_version, path, public_path, logs_path, database_name, database_user, status, ssl_status, redis_enabled, staging_domain, disk_usage_mb, cpu_usage_percent, provisioning_notes, created_at, updated_at
		 FROM sites
		 WHERE domain = ?`,
		normalizeDomain(domain),
	)
	return scanSite(row)
}

func (s *SiteService) siteExists(domain string) (bool, error) {
	var count int
	if err := s.db.QueryRow(`SELECT COUNT(1) FROM sites WHERE domain = ?`, domain).Scan(&count); err != nil {
		return false, err
	}
	return count > 0, nil
}

func (s *SiteService) writeProvisionPlan(domain string, systemUser string, input CreateSiteInput) (string, string, error) {
	planDir := filepath.Join(s.cfg.GeneratedRoot, "plans")
	if err := os.MkdirAll(planDir, 0o755); err != nil {
		return "", "", err
	}

	planPath := filepath.Join(planDir, fmt.Sprintf("%s.json", domain))
	payload := map[string]any{
		"domain":            domain,
		"systemUser":        systemUser,
		"phpVersion":        input.PHPVersion,
		"installWordPress":  input.InstallWordPress,
		"enableRedis":       input.EnableRedis,
		"template":          input.Template,
		"generatedAt":       time.Now().UTC().Format(time.RFC3339),
		"openLiteSpeedRoot": filepath.Join("/usr/local/lsws/conf/vhosts", domain),
	}

	data, err := json.MarshalIndent(payload, "", "  ")
	if err != nil {
		return "", "", err
	}

	if err := os.WriteFile(planPath, data, 0o644); err != nil {
		return "", "", err
	}

	return planPath, "Provisioning plan recorded for host automation", nil
}

func normalizeDomain(domain string) string {
	return strings.Trim(strings.ToLower(strings.TrimSpace(domain)), ".")
}

func buildSystemUser(domain string) string {
	replacer := strings.NewReplacer(".", "_", "-", "_")
	base := replacer.Replace(domain)
	base = strings.Trim(base, "_")
	if base == "" {
		base = "site"
	}
	user := "kb_" + base
	if len(user) > 24 {
		user = user[:24]
	}
	return user
}

func deriveDatabaseNames(domain string) (string, string) {
	base := strings.NewReplacer(".", "_", "-", "_").Replace(domain)
	base = strings.Trim(base, "_")
	if len(base) > 24 {
		base = base[:24]
	}

	database := base + "_wp"
	user := base + "_usr"

	if len(database) > 32 {
		database = database[:32]
	}
	if len(user) > 32 {
		user = user[:32]
	}

	return database, user
}

func statusForToggle(enabled bool) string {
	if enabled {
		return "planned"
	}
	return "skipped"
}

func scanSite(scanner rowScanner) (*models.Site, error) {
	var site models.Site
	var redisEnabled int
	var stagingDomain sql.NullString
	if err := scanner.Scan(
		&site.ID,
		&site.Domain,
		&site.SystemUser,
		&site.PHPVersion,
		&site.Path,
		&site.PublicPath,
		&site.LogsPath,
		&site.DatabaseName,
		&site.DatabaseUser,
		&site.Status,
		&site.SSLStatus,
		&redisEnabled,
		&stagingDomain,
		&site.DiskUsageMB,
		&site.CPUUsagePercent,
		&site.ProvisioningNote,
		&site.CreatedAt,
		&site.UpdatedAt,
	); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, err
		}
		return nil, err
	}

	site.RedisEnabled = redisEnabled == 1
	if stagingDomain.Valid {
		site.StagingDomain = stagingDomain.String
	}
	return &site, nil
}

func scanDatabase(scanner rowScanner) (*models.DatabaseRecord, error) {
	var record models.DatabaseRecord
	var lastBackup sql.NullString
	if err := scanner.Scan(
		&record.ID,
		&record.SiteID,
		&record.Name,
		&record.Username,
		&record.Engine,
		&record.Status,
		&lastBackup,
		&record.CreatedAt,
		&record.UpdatedAt,
	); err != nil {
		return nil, err
	}

	if lastBackup.Valid {
		record.LastBackupAt = lastBackup.String
	}
	return &record, nil
}
