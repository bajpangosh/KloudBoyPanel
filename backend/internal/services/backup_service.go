package services

import (
	"archive/tar"
	"compress/gzip"
	"database/sql"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/kloudboy/panel/backend/internal/config"
	"github.com/kloudboy/panel/backend/internal/models"
)

type BackupService struct {
	db  *sql.DB
	cfg config.Config
}

func NewBackupService(db *sql.DB, cfg config.Config) *BackupService {
	return &BackupService{db: db, cfg: cfg}
}

func (s *BackupService) CreateBackup(domain string) (*models.BackupRecord, error) {
	siteService := NewSiteService(s.db, s.cfg)
	site, err := siteService.GetSiteByDomain(domain)
	if err != nil {
		return nil, err
	}

	if _, err := os.Stat(site.Path); err != nil {
		return nil, err
	}

	timestamp := time.Now().UTC().Format("20060102-150405")
	backupDir := filepath.Join(s.cfg.BackupsRoot, site.Domain)
	if err := os.MkdirAll(backupDir, 0o755); err != nil {
		return nil, err
	}

	archivePath := filepath.Join(backupDir, fmt.Sprintf("%s.tar.gz", timestamp))
	if err := archiveDirectory(site.Path, archivePath); err != nil {
		return nil, err
	}

	info, err := os.Stat(archivePath)
	if err != nil {
		return nil, err
	}

	createdAt := time.Now().UTC().Format(time.RFC3339)
	result, err := s.db.Exec(
		`INSERT INTO backups (site_id, type, storage, path, status, size_bytes, created_at, completed_at)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
		site.ID,
		"manual",
		"local",
		archivePath,
		"completed",
		info.Size(),
		createdAt,
		createdAt,
	)
	if err != nil {
		return nil, err
	}

	backupID, _ := result.LastInsertId()
	if _, err := s.db.Exec(
		`UPDATE databases SET last_backup_at = ?, updated_at = ? WHERE site_id = ?`,
		createdAt,
		createdAt,
		site.ID,
	); err != nil {
		return nil, err
	}

	return &models.BackupRecord{
		ID:          backupID,
		SiteID:      site.ID,
		Type:        "manual",
		Storage:     "local",
		Path:        archivePath,
		Status:      "completed",
		SizeBytes:   info.Size(),
		CreatedAt:   createdAt,
		CompletedAt: createdAt,
	}, nil
}

func (s *BackupService) ListBackups(limit int) ([]models.BackupRecord, error) {
	if limit <= 0 {
		limit = 20
	}

	rows, err := s.db.Query(
		`SELECT id, site_id, type, storage, path, status, size_bytes, created_at, completed_at
		 FROM backups
		 ORDER BY created_at DESC
		 LIMIT ?`,
		limit,
	)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	backups := make([]models.BackupRecord, 0)
	for rows.Next() {
		record, err := scanBackup(rows)
		if err != nil {
			return nil, err
		}
		backups = append(backups, *record)
	}

	return backups, rows.Err()
}

func archiveDirectory(source string, destination string) error {
	file, err := os.Create(destination)
	if err != nil {
		return err
	}
	defer file.Close()

	gzipWriter := gzip.NewWriter(file)
	defer gzipWriter.Close()

	tarWriter := tar.NewWriter(gzipWriter)
	defer tarWriter.Close()

	return filepath.Walk(source, func(path string, info os.FileInfo, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}

		header, err := tar.FileInfoHeader(info, info.Name())
		if err != nil {
			return err
		}

		relativePath := strings.TrimPrefix(strings.TrimPrefix(path, source), string(os.PathSeparator))
		if relativePath == "" {
			relativePath = info.Name()
		}
		header.Name = relativePath

		if err := tarWriter.WriteHeader(header); err != nil {
			return err
		}

		if !info.Mode().IsRegular() {
			return nil
		}

		in, err := os.Open(path)
		if err != nil {
			return err
		}
		defer in.Close()

		_, err = io.Copy(tarWriter, in)
		return err
	})
}

func scanBackup(scanner rowScanner) (*models.BackupRecord, error) {
	var record models.BackupRecord
	var completed sql.NullString
	if err := scanner.Scan(
		&record.ID,
		&record.SiteID,
		&record.Type,
		&record.Storage,
		&record.Path,
		&record.Status,
		&record.SizeBytes,
		&record.CreatedAt,
		&completed,
	); err != nil {
		return nil, err
	}

	if completed.Valid {
		record.CompletedAt = completed.String
	}
	return &record, nil
}

