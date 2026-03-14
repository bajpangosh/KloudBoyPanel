package models

type Site struct {
	ID               int64   `json:"id"`
	Domain           string  `json:"domain"`
	SystemUser       string  `json:"systemUser"`
	PHPVersion       string  `json:"phpVersion"`
	Path             string  `json:"path"`
	PublicPath       string  `json:"publicPath"`
	LogsPath         string  `json:"logsPath"`
	DatabaseName     string  `json:"databaseName"`
	DatabaseUser     string  `json:"databaseUser"`
	Status           string  `json:"status"`
	SSLStatus        string  `json:"sslStatus"`
	RedisEnabled     bool    `json:"redisEnabled"`
	StagingDomain    string  `json:"stagingDomain,omitempty"`
	DiskUsageMB      int64   `json:"diskUsageMB"`
	CPUUsagePercent  float64 `json:"cpuUsagePercent"`
	ProvisioningNote string  `json:"provisioningNote"`
	CreatedAt        string  `json:"createdAt"`
	UpdatedAt        string  `json:"updatedAt"`
}

type DatabaseRecord struct {
	ID           int64  `json:"id"`
	SiteID       int64  `json:"siteId"`
	Name         string `json:"name"`
	Username     string `json:"username"`
	Engine       string `json:"engine"`
	Status       string `json:"status"`
	LastBackupAt string `json:"lastBackupAt,omitempty"`
	CreatedAt    string `json:"createdAt"`
	UpdatedAt    string `json:"updatedAt"`
}

type BackupRecord struct {
	ID          int64  `json:"id"`
	SiteID      int64  `json:"siteId"`
	Type        string `json:"type"`
	Storage     string `json:"storage"`
	Path        string `json:"path"`
	Status      string `json:"status"`
	SizeBytes   int64  `json:"sizeBytes"`
	CreatedAt   string `json:"createdAt"`
	CompletedAt string `json:"completedAt,omitempty"`
}

type ServiceStatus struct {
	Name   string `json:"name"`
	Status string `json:"status"`
	Detail string `json:"detail"`
}

type Alert struct {
	Level   string `json:"level"`
	Title   string `json:"title"`
	Detail  string `json:"detail"`
	Source  string `json:"source"`
	Created string `json:"created"`
}

type DashboardMetric struct {
	Key   string `json:"key"`
	Label string `json:"label"`
	Value string `json:"value"`
	Trend string `json:"trend"`
}

type DashboardOverview struct {
	Metrics      []DashboardMetric `json:"metrics"`
	QuickActions []string          `json:"quickActions"`
	Services     []ServiceStatus   `json:"services"`
	RecentSites  []Site            `json:"recentSites"`
	RecentBackup []BackupRecord    `json:"recentBackups"`
	Alerts       []Alert           `json:"alerts"`
}

type ServerStatus struct {
	UptimeSeconds   int64           `json:"uptimeSeconds"`
	LoadAverage     []float64       `json:"loadAverage"`
	CPUUsagePercent float64         `json:"cpuUsagePercent"`
	MemoryTotalMB   int64           `json:"memoryTotalMB"`
	MemoryUsedMB    int64           `json:"memoryUsedMB"`
	DiskTotalGB     int64           `json:"diskTotalGB"`
	DiskUsedGB      int64           `json:"diskUsedGB"`
	WebsiteCount    int             `json:"websiteCount"`
	DatabaseCount   int             `json:"databaseCount"`
	BackupCount     int             `json:"backupCount"`
	Services        []ServiceStatus `json:"services"`
}

type PanelConfiguration struct {
	PanelDomain     string `json:"panelDomain"`
	PanelPort       int    `json:"panelPort"`
	HiddenLoginPath string `json:"hiddenLoginPath"`
	Timezone        string `json:"timezone"`
	BackupLocation  string `json:"backupLocation"`
}

type DoctorCheck struct {
	Name   string `json:"name"`
	Status string `json:"status"`
	Detail string `json:"detail"`
}

type ActionResponse struct {
	Message string `json:"message"`
}

