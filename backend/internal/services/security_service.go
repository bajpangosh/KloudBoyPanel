package services

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/kloudboy/panel/backend/internal/config"
	"github.com/kloudboy/panel/backend/internal/models"
)

type SecurityService struct {
	db  *sql.DB
	cfg config.Config
}

type MalwareScanInput struct {
	SiteDomain string `json:"siteDomain"`
}

type malwareRule struct {
	Name       string
	Severity   string
	Needle     string
	Extensions map[string]struct{}
}

func NewSecurityService(db *sql.DB, cfg config.Config) *SecurityService {
	return &SecurityService{db: db, cfg: cfg}
}

func (s *SecurityService) RunMalwareScan(input MalwareScanInput) (*models.MalwareScanResult, error) {
	startedAt := time.Now().UTC()
	sites, err := s.sitesForScan(input.SiteDomain)
	if err != nil {
		return nil, err
	}

	result := &models.MalwareScanResult{
		Status:       "completed",
		Scope:        scanScope(input.SiteDomain),
		SitesScanned: make([]string, 0, len(sites)),
		FilesScanned: 0,
		Findings:     make([]models.MalwareFinding, 0),
		StartedAt:    startedAt.Format(time.RFC3339),
	}

	rules := []malwareRule{
		newMalwareRule("base64 payload execution", "high", "eval(base64_decode(", ".php", ".phtml"),
		newMalwareRule("compressed payload execution", "high", "gzinflate(base64_decode(", ".php", ".phtml"),
		newMalwareRule("shell execution", "medium", "shell_exec(", ".php"),
		newMalwareRule("passthru execution", "medium", "passthru(", ".php"),
		newMalwareRule("assert post execution", "high", "assert($_POST", ".php"),
		newMalwareRule("suspicious iframe injection", "medium", "<iframe style=\"display:none\"", ".php", ".html", ".js"),
	}

	for _, site := range sites {
		result.SitesScanned = append(result.SitesScanned, site.Domain)
		_ = filepath.Walk(site.Path, func(path string, info os.FileInfo, walkErr error) error {
			if walkErr != nil || info == nil || info.IsDir() {
				return walkErr
			}

			result.FilesScanned++
			if info.Mode().Perm()&0o022 != 0 {
				result.Findings = append(result.Findings, models.MalwareFinding{
					SiteDomain: site.Domain,
					FilePath:   path,
					Rule:       "world-writable file",
					Severity:   "medium",
					Detail:     fmt.Sprintf("Permissions are %04o", info.Mode().Perm()),
				})
			}

			extension := strings.ToLower(filepath.Ext(path))
			if info.Size() > 2*1024*1024 || extension == "" {
				return nil
			}

			data, err := os.ReadFile(path)
			if err != nil {
				return nil
			}

			content := strings.ToLower(string(data))
			for _, rule := range rules {
				if !rule.matchesExtension(extension) {
					continue
				}
				if strings.Contains(content, rule.Needle) {
					result.Findings = append(result.Findings, models.MalwareFinding{
						SiteDomain: site.Domain,
						FilePath:   path,
						Rule:       rule.Name,
						Severity:   rule.Severity,
						Detail:     fmt.Sprintf("Matched signature %q", rule.Needle),
					})
				}
			}

			return nil
		})
	}

	result.CompletedAt = time.Now().UTC().Format(time.RFC3339)
	if len(result.Findings) > 0 {
		result.Status = "warning"
	}

	reportPath, err := s.writeScanReport(result)
	if err != nil {
		return nil, err
	}
	result.ReportPath = reportPath
	return result, nil
}

func (s *SecurityService) sitesForScan(siteDomain string) ([]models.Site, error) {
	if normalizeDomain(siteDomain) != "" {
		site, err := NewSiteService(s.db, s.cfg).GetSiteByDomain(siteDomain)
		if err != nil {
			if err == sql.ErrNoRows {
				return nil, fmt.Errorf("%w: unknown site %q", ErrInvalidInput, siteDomain)
			}
			return nil, err
		}
		return []models.Site{*site}, nil
	}

	return NewSiteService(s.db, s.cfg).ListSites()
}

func (s *SecurityService) writeScanReport(result *models.MalwareScanResult) (string, error) {
	reportDir := filepath.Join(s.cfg.GeneratedRoot, "scans")
	if err := os.MkdirAll(reportDir, 0o755); err != nil {
		return "", err
	}

	reportPath := filepath.Join(reportDir, fmt.Sprintf("scan-%s.json", time.Now().UTC().Format("20060102-150405")))
	data, err := json.MarshalIndent(result, "", "  ")
	if err != nil {
		return "", err
	}

	if err := os.WriteFile(reportPath, data, 0o644); err != nil {
		return "", err
	}

	return reportPath, nil
}

func scanScope(siteDomain string) string {
	if normalizeDomain(siteDomain) != "" {
		return "site"
	}
	return "all-sites"
}

func newMalwareRule(name string, severity string, needle string, extensions ...string) malwareRule {
	set := make(map[string]struct{}, len(extensions))
	for _, extension := range extensions {
		set[strings.ToLower(extension)] = struct{}{}
	}
	return malwareRule{Name: name, Severity: severity, Needle: strings.ToLower(needle), Extensions: set}
}

func (r malwareRule) matchesExtension(extension string) bool {
	_, ok := r.Extensions[extension]
	return ok
}
