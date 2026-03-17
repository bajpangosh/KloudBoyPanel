package api

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/kloudboy/panel/backend/internal/models"
	"github.com/kloudboy/panel/backend/internal/services"
)

const adminContextKey = "authenticated_admin"

func (h *handler) login(c *gin.Context) {
	var input services.LoginInput
	if err := c.ShouldBindJSON(&input); err != nil {
		writeError(c, err)
		return
	}

	session, err := h.app.AuthService.Login(input)
	if err != nil {
		writeError(c, err)
		return
	}

	c.JSON(http.StatusOK, session)
}

func (h *handler) currentAdmin(c *gin.Context) {
	admin, ok := c.MustGet(adminContextKey).(*models.Admin)
	if !ok || admin == nil {
		c.JSON(http.StatusUnauthorized, gin.H{"error": "unauthorized"})
		return
	}

	c.JSON(http.StatusOK, gin.H{"admin": admin})
}

func (h *handler) logout(c *gin.Context) {
	token := bearerToken(c.GetHeader("Authorization"))
	if err := h.app.AuthService.Logout(token); err != nil {
		writeError(c, err)
		return
	}

	c.JSON(http.StatusOK, gin.H{"message": "logged out"})
}

func (h *handler) requireAuth() gin.HandlerFunc {
	return func(c *gin.Context) {
		token := bearerToken(c.GetHeader("Authorization"))
		if token == "" {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "missing authorization token"})
			c.Abort()
			return
		}

		admin, err := h.app.AuthService.CurrentAdmin(token)
		if err != nil {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "unauthorized"})
			c.Abort()
			return
		}

		c.Set(adminContextKey, admin)
		c.Next()
	}
}

func bearerToken(header string) string {
	header = strings.TrimSpace(header)
	if header == "" {
		return ""
	}

	const prefix = "Bearer "
	if strings.HasPrefix(header, prefix) {
		return strings.TrimSpace(strings.TrimPrefix(header, prefix))
	}

	return header
}
