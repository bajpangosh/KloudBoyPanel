package services

import (
	"context"
	"database/sql"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strconv"
	"strings"
	"syscall"
	"time"

	"github.com/kloudboy/panel/backend/internal/config"
	"github.com/kloudboy/panel/backend/internal/models"
)

type ServerService struct {
	db  *sql.DB
	cfg config.Config
}

func NewServerService(db *sql.DB, cfg config.Config) *ServerService {
	return &ServerService{db: db, cfg: cfg}
}

type RestartServiceInput struct {
	Service string `json:"service"`
}

func (s *ServerService) DashboardOverview() (*models.DashboardOverview, error) {
	status, err := s.Status()
	if err != nil {
		return nil, err
	}

	recentSites, err := s.listRecentSites(5)
	if err != nil {
		return nil, err
	}

	recentBackups, err := s.listRecentBackups(5)
	if err != nil {
		return nil, err
	}

	alerts := s.buildAlerts(status)
	overview := &models.DashboardOverview{
		Metrics: []models.DashboardMetric{
			{Key: "cpu", Label: "CPU usage", Value: fmt.Sprintf("%.1f%%", status.CPUUsagePercent), Trend: "live"},
			{Key: "ram", Label: "RAM usage", Value: fmt.Sprintf("%d / %d MB", status.MemoryUsedMB, status.MemoryTotalMB), Trend: "live"},
			{Key: "disk", Label: "Disk usage", Value: fmt.Sprintf("%d / %d GB", status.DiskUsedGB, status.DiskTotalGB), Trend: "live"},
			{Key: "uptime", Label: "Server uptime", Value: humanUptime(status.UptimeSeconds), Trend: "stable"},
			{Key: "sites", Label: "Website count", Value: fmt.Sprintf("%d", status.WebsiteCount), Trend: "inventory"},
			{Key: "databases", Label: "Database count", Value: fmt.Sprintf("%d", status.DatabaseCount), Trend: "inventory"},
			{Key: "backups", Label: "Backup status", Value: fmt.Sprintf("%d recent", status.BackupCount), Trend: "safety"},
		},
		QuickActions: []string{
			"Create Website",
			"Create Database",
			"Backup Now",
			"Restart Services",
			"Run Malware Scan",
		},
		Services:     status.Services,
		RecentSites:  recentSites,
		RecentBackup: recentBackups,
		Alerts:       alerts,
	}

	return overview, nil
}

func (s *ServerService) Status() (*models.ServerStatus, error) {
	uptime := readUptimeSeconds()
	loadAverage := readLoadAverage()
	memoryTotal, memoryUsed := readMemory()
	diskTotal, diskUsed := readDiskUsage(s.cfg.DataDir)
	siteCount := s.count(`SELECT COUNT(1) FROM sites WHERE status != 'deleted'`)
	databaseCount := s.count(`SELECT COUNT(1) FROM databases`)
	backupCount := s.count(`SELECT COUNT(1) FROM backups`)

	status := &models.ServerStatus{
		UptimeSeconds:   uptime,
		LoadAverage:     loadAverage,
		CPUUsagePercent: estimateCPUUsage(loadAverage),
		MemoryTotalMB:   memoryTotal,
		MemoryUsedMB:    memoryUsed,
		DiskTotalGB:     diskTotal,
		DiskUsedGB:      diskUsed,
		WebsiteCount:    siteCount,
		DatabaseCount:   databaseCount,
		BackupCount:     backupCount,
		Services:        s.buildManagedServices(),
	}
	return status, nil
}

func (s *ServerService) Configuration() models.PanelConfiguration {
	return models.PanelConfiguration{
		PanelDomain:     s.cfg.PanelDomain,
		PanelPort:       s.cfg.PanelPort,
		HiddenLoginPath: s.cfg.PanelHiddenPath,
		Timezone:        s.cfg.Timezone,
		BackupLocation:  s.cfg.BackupsRoot,
	}
}

func (s *ServerService) RestartService(input RestartServiceInput) (*models.ServiceActionResult, error) {
	serviceKey := normalizeServiceKey(input.Service)
	if serviceKey == "" {
		return nil, fmt.Errorf("%w: service is required", ErrInvalidInput)
	}

	definition, ok := restartableServices()[serviceKey]
	if !ok {
		return nil, fmt.Errorf("%w: unsupported service %q", ErrInvalidInput, input.Service)
	}

	startedAt := time.Now().UTC()
	result := &models.ServiceActionResult{
		Service:   definition.DisplayName,
		Status:    "failed",
		Detail:    "No restart command succeeded.",
		Attempts:  make([]models.ServiceCommandAttempt, 0, len(definition.Commands)),
		StartedAt: startedAt.Format(time.RFC3339),
	}

	for _, candidate := range definition.Commands {
		commandString := strings.Join(candidate, " ")
		binary, err := exec.LookPath(candidate[0])
		if err != nil {
			result.Attempts = append(result.Attempts, models.ServiceCommandAttempt{
				Command: commandString,
				Status:  "unavailable",
				Output:  fmt.Sprintf("%s not found on this node", candidate[0]),
			})
			continue
		}

		ctx, cancel := context.WithTimeout(context.Background(), 20*time.Second)
		cmd := exec.CommandContext(ctx, binary, candidate[1:]...)
		output, runErr := cmd.CombinedOutput()
		cancel()

		attempt := models.ServiceCommandAttempt{
			Command: commandString,
			Output:  strings.TrimSpace(string(output)),
		}
		if runErr != nil {
			attempt.Status = "failed"
			if attempt.Output == "" {
				attempt.Output = runErr.Error()
			}
			result.Attempts = append(result.Attempts, attempt)
			continue
		}

		attempt.Status = "completed"
		if attempt.Output == "" {
			attempt.Output = "Restart command completed without output."
		}
		result.Attempts = append(result.Attempts, attempt)
		result.Status = "completed"
		result.Detail = fmt.Sprintf("Restarted %s using %s", definition.DisplayName, commandString)
		result.CompletedAt = time.Now().UTC().Format(time.RFC3339)
		return result, nil
	}

	result.CompletedAt = time.Now().UTC().Format(time.RFC3339)
	return result, nil
}

func (s *ServerService) DoctorChecks() ([]models.DoctorCheck, error) {
	checks := []models.DoctorCheck{
		{Name: "SQLite database", Status: "pass", Detail: s.cfg.DatabasePath},
		{Name: "Data directory", Status: directoryStatus(s.cfg.DataDir), Detail: s.cfg.DataDir},
		{Name: "Sites root", Status: directoryStatus(s.cfg.SitesRoot), Detail: s.cfg.SitesRoot},
		{Name: "Backups root", Status: directoryStatus(s.cfg.BackupsRoot), Detail: s.cfg.BackupsRoot},
		{Name: "Generated plans", Status: directoryStatus(filepath.Join(s.cfg.GeneratedRoot, "plans")), Detail: s.cfg.GeneratedRoot},
		{Name: "OpenLiteSpeed", Status: "warn", Detail: "Integration planned but not yet installed or verified"},
		{Name: "MariaDB", Status: "warn", Detail: "Database server provisioning not yet implemented"},
		{Name: "Redis", Status: "warn", Detail: "Redis integration not yet implemented"},
	}
	return checks, nil
}

func (s *ServerService) listRecentSites(limit int) ([]models.Site, error) {
	rows, err := s.db.Query(
		`SELECT id, domain, system_user, php_version, path, public_path, logs_path, database_name, database_user, status, ssl_status, redis_enabled, staging_domain, disk_usage_mb, cpu_usage_percent, provisioning_notes, created_at, updated_at
		 FROM sites
		 ORDER BY created_at DESC
		 LIMIT ?`,
		limit,
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

func (s *ServerService) listRecentBackups(limit int) ([]models.BackupRecord, error) {
	service := NewBackupService(s.db, s.cfg)
	return service.ListBackups(limit)
}

func (s *ServerService) buildAlerts(status *models.ServerStatus) []models.Alert {
	alerts := make([]models.Alert, 0)
	now := time.Now().UTC().Format(time.RFC3339)

	if status.DiskTotalGB > 0 && float64(status.DiskUsedGB)/float64(status.DiskTotalGB) > 0.85 {
		alerts = append(alerts, models.Alert{
			Level:   "warning",
			Title:   "Disk pressure rising",
			Detail:  "Disk usage is above 85% of the configured data volume.",
			Source:  "storage",
			Created: now,
		})
	}

	if status.CPUUsagePercent > 80 {
		alerts = append(alerts, models.Alert{
			Level:   "warning",
			Title:   "High CPU usage",
			Detail:  "Estimated CPU load is above the recommended threshold.",
			Source:  "performance",
			Created: now,
		})
	}

	if status.BackupCount == 0 {
		alerts = append(alerts, models.Alert{
			Level:   "info",
			Title:   "No backups yet",
			Detail:  "Run the first backup after provisioning your initial site.",
			Source:  "backups",
			Created: now,
		})
	}

	return alerts
}

func (s *ServerService) count(query string) int {
	var count int
	if err := s.db.QueryRow(query).Scan(&count); err != nil {
		return 0
	}
	return count
}

func directoryStatus(path string) string {
	if _, err := os.Stat(path); err != nil {
		return "warn"
	}
	return "pass"
}

func readUptimeSeconds() int64 {
	data, err := os.ReadFile("/proc/uptime")
	if err != nil {
		return 0
	}
	fields := strings.Fields(string(data))
	if len(fields) == 0 {
		return 0
	}

	value, err := strconv.ParseFloat(fields[0], 64)
	if err != nil {
		return 0
	}
	return int64(value)
}

func readLoadAverage() []float64 {
	data, err := os.ReadFile("/proc/loadavg")
	if err != nil {
		return []float64{0, 0, 0}
	}

	fields := strings.Fields(string(data))
	loads := []float64{0, 0, 0}
	for i := 0; i < 3 && i < len(fields); i++ {
		value, parseErr := strconv.ParseFloat(fields[i], 64)
		if parseErr != nil {
			continue
		}
		loads[i] = value
	}
	return loads
}

func readMemory() (totalMB int64, usedMB int64) {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return 0, 0
	}

	var totalKB int64
	var availableKB int64
	for _, line := range strings.Split(string(data), "\n") {
		if strings.HasPrefix(line, "MemTotal:") {
			fields := strings.Fields(line)
			if len(fields) >= 2 {
				totalKB, _ = strconv.ParseInt(fields[1], 10, 64)
			}
		}
		if strings.HasPrefix(line, "MemAvailable:") {
			fields := strings.Fields(line)
			if len(fields) >= 2 {
				availableKB, _ = strconv.ParseInt(fields[1], 10, 64)
			}
		}
	}

	totalMB = totalKB / 1024
	usedMB = (totalKB - availableKB) / 1024
	return totalMB, usedMB
}

func readDiskUsage(path string) (totalGB int64, usedGB int64) {
	stats := syscall.Statfs_t{}
	if err := syscall.Statfs(path, &stats); err != nil {
		return 0, 0
	}

	total := float64(stats.Blocks*uint64(stats.Bsize)) / (1024 * 1024 * 1024)
	free := float64(stats.Bavail*uint64(stats.Bsize)) / (1024 * 1024 * 1024)
	used := total - free
	return int64(total), int64(used)
}

func estimateCPUUsage(loads []float64) float64 {
	if len(loads) == 0 {
		return 0
	}

	cpus := runtime.NumCPU()
	if cpus == 0 {
		return 0
	}

	estimated := (loads[0] / float64(cpus)) * 100
	if estimated > 100 {
		return 100
	}
	if estimated < 0 {
		return 0
	}
	return estimated
}

func humanUptime(seconds int64) string {
	if seconds <= 0 {
		return "0m"
	}

	days := seconds / 86400
	hours := (seconds % 86400) / 3600
	minutes := (seconds % 3600) / 60

	if days > 0 {
		return fmt.Sprintf("%dd %dh", days, hours)
	}
	if hours > 0 {
		return fmt.Sprintf("%dh %dm", hours, minutes)
	}
	return fmt.Sprintf("%dm", minutes)
}

func (s *ServerService) buildManagedServices() []models.ServiceStatus {
	return []models.ServiceStatus{
		{
			Name:   "KloudBoy Panel",
			Status: "online",
			Detail: panelRuntimeDetail(s.cfg.HTTPPort),
		},
		{
			Name:   "SQLite",
			Status: "online",
			Detail: s.cfg.DatabasePath,
		},
		detectManagedService("OpenLiteSpeed", []string{"/usr/local/lsws/bin/lswsctrl", "lswsctrl", "lshttpd"}, "Restart commands are available on this node"),
		detectManagedService("MariaDB", []string{"mariadbd", "mysqld", "mysqladmin"}, "MariaDB tooling is available on this node"),
		detectManagedService("Redis", []string{"redis-server", "redis-cli"}, "Redis tooling is available on this node"),
	}
}

type serviceRestartDefinition struct {
	DisplayName string
	Commands    [][]string
}

func restartableServices() map[string]serviceRestartDefinition {
	panelCommands := [][]string{
		{"systemctl", "restart", "kloudboy"},
		{"service", "kloudboy", "restart"},
	}
	if runningInContainer() {
		panelCommands = append([][]string{
			{"sh", "-lc", "(sleep 1 && kill -TERM 1) >/tmp/kloudboy-panel-restart.log 2>&1 &"},
		}, panelCommands...)
	}

	return map[string]serviceRestartDefinition{
		"panel": {
			DisplayName: "KloudBoy Panel",
			Commands:    panelCommands,
		},
		"openlitespeed": {
			DisplayName: "OpenLiteSpeed",
			Commands: [][]string{
				{"systemctl", "restart", "lsws"},
				{"systemctl", "restart", "openlitespeed"},
				{"service", "lsws", "restart"},
			},
		},
		"mariadb": {
			DisplayName: "MariaDB",
			Commands: [][]string{
				{"systemctl", "restart", "mariadb"},
				{"systemctl", "restart", "mysql"},
				{"service", "mariadb", "restart"},
				{"service", "mysql", "restart"},
			},
		},
		"redis": {
			DisplayName: "Redis",
			Commands: [][]string{
				{"systemctl", "restart", "redis-server"},
				{"systemctl", "restart", "redis"},
				{"service", "redis-server", "restart"},
				{"service", "redis", "restart"},
			},
		},
	}
}

func normalizeServiceKey(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	replacer := strings.NewReplacer(" ", "", "-", "", "_", "")
	value = replacer.Replace(value)

	switch value {
	case "kloudboypanel", "panel":
		return "panel"
	case "openlitespeed", "ols", "lsws":
		return "openlitespeed"
	case "mariadb", "mysql":
		return "mariadb"
	case "redis", "redisserver":
		return "redis"
	default:
		return value
	}
}

func detectManagedService(name string, binaries []string, availableDetail string) models.ServiceStatus {
	for _, binary := range binaries {
		if fileExists(binary) {
			return models.ServiceStatus{
				Name:   name,
				Status: "online",
				Detail: availableDetail,
			}
		}

		if resolved, err := exec.LookPath(binary); err == nil {
			return models.ServiceStatus{
				Name:   name,
				Status: "online",
				Detail: fmt.Sprintf("Detected at %s", resolved),
			}
		}
	}

	return models.ServiceStatus{
		Name:   name,
		Status: "unavailable",
		Detail: "Not installed on this node",
	}
}

func panelRuntimeDetail(port int) string {
	if runningInContainer() {
		return fmt.Sprintf("Containerized panel runtime on port %d", port)
	}
	return fmt.Sprintf("API process listening on port %d", port)
}

func runningInContainer() bool {
	return fileExists("/.dockerenv") || fileExists("/run/.containerenv")
}

func fileExists(path string) bool {
	if path == "" {
		return false
	}
	_, err := os.Stat(path)
	return err == nil
}
