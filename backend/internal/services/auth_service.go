package services

import (
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"database/sql"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/kloudboy/panel/backend/internal/config"
	"github.com/kloudboy/panel/backend/internal/models"
	"golang.org/x/crypto/bcrypt"
)

const (
	authSecretKey       = "auth_secret"
	sessionDuration     = 24 * time.Hour
	bootstrapFileName   = "initial-admin.txt"
	bootstrapNoticeName = "Initial admin generated"
)

type AuthService struct {
	db     *sql.DB
	cfg    config.Config
	secret []byte
}

type LoginInput struct {
	Email    string `json:"email"`
	Password string `json:"password"`
}

type BootstrapCredentials struct {
	Email           string
	Password        string
	LoginURL        string
	CredentialsPath string
}

type authClaims struct {
	AdminID int64  `json:"adminId"`
	Email   string `json:"email"`
	Exp     int64  `json:"exp"`
	Iat     int64  `json:"iat"`
}

func NewAuthService(db *sql.DB, cfg config.Config) *AuthService {
	return &AuthService{db: db, cfg: cfg}
}

func (s *AuthService) Initialize() (*BootstrapCredentials, error) {
	if err := s.ensureSecret(); err != nil {
		return nil, err
	}

	var adminCount int
	if err := s.db.QueryRow(`SELECT COUNT(1) FROM admins`).Scan(&adminCount); err != nil {
		return nil, err
	}
	if adminCount > 0 {
		return nil, nil
	}

	email := strings.ToLower(strings.TrimSpace(s.cfg.BootstrapEmail))
	if email == "" {
		email = "admin@kloudboy.local"
	}

	password, err := randomToken(18)
	if err != nil {
		return nil, err
	}

	hash, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
	if err != nil {
		return nil, err
	}

	now := time.Now().UTC().Format(time.RFC3339)
	if _, err := s.db.Exec(
		`INSERT INTO admins (email, password_hash, role, created_at, updated_at)
		 VALUES (?, ?, ?, ?, ?)`,
		email,
		string(hash),
		"owner",
		now,
		now,
	); err != nil {
		return nil, err
	}

	credentials := &BootstrapCredentials{
		Email:           email,
		Password:        password,
		LoginURL:        s.localLoginURL(),
		CredentialsPath: filepath.Join(s.cfg.GeneratedRoot, bootstrapFileName),
	}
	if err := s.writeBootstrapCredentials(credentials); err != nil {
		return nil, err
	}

	log.Printf("[kloudboy] %s", bootstrapNoticeName)
	log.Printf("[kloudboy] Login URL: %s", credentials.LoginURL)
	log.Printf("[kloudboy] Email: %s", credentials.Email)
	log.Printf("[kloudboy] Password: %s", credentials.Password)
	log.Printf("[kloudboy] Saved credentials: %s", credentials.CredentialsPath)

	return credentials, nil
}

func (s *AuthService) Login(input LoginInput) (*models.AuthSession, error) {
	email := strings.ToLower(strings.TrimSpace(input.Email))
	password := strings.TrimSpace(input.Password)
	if email == "" || password == "" {
		return nil, fmt.Errorf("%w: email and password are required", ErrInvalidInput)
	}

	admin, passwordHash, err := s.getAdminByEmail(email)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, ErrUnauthorized
		}
		return nil, err
	}

	if err := bcrypt.CompareHashAndPassword([]byte(passwordHash), []byte(password)); err != nil {
		return nil, ErrUnauthorized
	}

	token, expiresAt, err := s.issueToken(admin)
	if err != nil {
		return nil, err
	}

	return &models.AuthSession{
		Token:     token,
		ExpiresAt: expiresAt.Format(time.RFC3339),
		Admin:     *admin,
	}, nil
}

func (s *AuthService) CurrentAdmin(token string) (*models.Admin, error) {
	claims, err := s.parseToken(token)
	if err != nil {
		return nil, err
	}

	admin, err := s.getAdminByID(claims.AdminID)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, ErrUnauthorized
		}
		return nil, err
	}

	return admin, nil
}

func (s *AuthService) ensureSecret() error {
	if len(s.secret) > 0 {
		return nil
	}

	if s.cfg.AuthSecret != "" {
		s.secret = []byte(s.cfg.AuthSecret)
		return nil
	}

	var stored string
	err := s.db.QueryRow(`SELECT value FROM server_settings WHERE key = ?`, authSecretKey).Scan(&stored)
	if err == nil {
		s.secret = []byte(stored)
		return nil
	}
	if err != nil && err != sql.ErrNoRows {
		return err
	}

	generated, err := randomToken(48)
	if err != nil {
		return err
	}

	if _, err := s.db.Exec(
		`INSERT INTO server_settings (key, value, updated_at)
		 VALUES (?, ?, datetime('now'))
		 ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at`,
		authSecretKey,
		generated,
	); err != nil {
		return err
	}

	s.secret = []byte(generated)
	return nil
}

func (s *AuthService) getAdminByEmail(email string) (*models.Admin, string, error) {
	row := s.db.QueryRow(
		`SELECT id, email, password_hash, role, created_at, updated_at
		 FROM admins
		 WHERE email = ?`,
		email,
	)

	var admin models.Admin
	var passwordHash string
	if err := row.Scan(
		&admin.ID,
		&admin.Email,
		&passwordHash,
		&admin.Role,
		&admin.CreatedAt,
		&admin.UpdatedAt,
	); err != nil {
		return nil, "", err
	}

	return &admin, passwordHash, nil
}

func (s *AuthService) getAdminByID(id int64) (*models.Admin, error) {
	row := s.db.QueryRow(
		`SELECT id, email, role, created_at, updated_at
		 FROM admins
		 WHERE id = ?`,
		id,
	)

	var admin models.Admin
	if err := row.Scan(
		&admin.ID,
		&admin.Email,
		&admin.Role,
		&admin.CreatedAt,
		&admin.UpdatedAt,
	); err != nil {
		return nil, err
	}

	return &admin, nil
}

func (s *AuthService) issueToken(admin *models.Admin) (string, time.Time, error) {
	if err := s.ensureSecret(); err != nil {
		return "", time.Time{}, err
	}

	now := time.Now().UTC()
	expiresAt := now.Add(sessionDuration)
	claims := authClaims{
		AdminID: admin.ID,
		Email:   admin.Email,
		Iat:     now.Unix(),
		Exp:     expiresAt.Unix(),
	}

	payload, err := json.Marshal(claims)
	if err != nil {
		return "", time.Time{}, err
	}

	encodedPayload := base64.RawURLEncoding.EncodeToString(payload)
	signature := s.sign(encodedPayload)
	return fmt.Sprintf("%s.%s", encodedPayload, signature), expiresAt, nil
}

func (s *AuthService) parseToken(token string) (*authClaims, error) {
	if err := s.ensureSecret(); err != nil {
		return nil, err
	}

	parts := strings.Split(token, ".")
	if len(parts) != 2 {
		return nil, ErrUnauthorized
	}

	if !hmac.Equal([]byte(s.sign(parts[0])), []byte(parts[1])) {
		return nil, ErrUnauthorized
	}

	payload, err := base64.RawURLEncoding.DecodeString(parts[0])
	if err != nil {
		return nil, ErrUnauthorized
	}

	var claims authClaims
	if err := json.Unmarshal(payload, &claims); err != nil {
		return nil, ErrUnauthorized
	}

	if time.Now().UTC().Unix() > claims.Exp {
		return nil, ErrUnauthorized
	}

	return &claims, nil
}

func (s *AuthService) sign(payload string) string {
	mac := hmac.New(sha256.New, s.secret)
	mac.Write([]byte(payload))
	return base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
}

func (s *AuthService) writeBootstrapCredentials(credentials *BootstrapCredentials) error {
	if err := os.MkdirAll(filepath.Dir(credentials.CredentialsPath), 0o755); err != nil {
		return err
	}

	content := fmt.Sprintf(
		"KloudBoy Panel bootstrap credentials\n\nLogin URL: %s\nEmail: %s\nPassword: %s\n",
		credentials.LoginURL,
		credentials.Email,
		credentials.Password,
	)

	return os.WriteFile(credentials.CredentialsPath, []byte(content), 0o600)
}

func (s *AuthService) localLoginURL() string {
	basePath := s.cfg.PanelBasePath()
	return fmt.Sprintf("http://localhost:%d%slogin", s.cfg.HTTPPort, basePath)
}

func randomToken(length int) (string, error) {
	bytes := make([]byte, length)
	if _, err := rand.Read(bytes); err != nil {
		return "", err
	}

	token := base64.RawURLEncoding.EncodeToString(bytes)
	if len(token) > length {
		token = token[:length]
	}
	return token, nil
}
