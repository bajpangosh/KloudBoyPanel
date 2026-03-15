package api

import (
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"github.com/gin-gonic/gin"
)

func mountFrontend(router *gin.Engine, staticDir string) {
	info, err := os.Stat(staticDir)
	if err != nil || !info.IsDir() {
		return
	}

	indexPath := filepath.Join(staticDir, "index.html")
	if _, err := os.Stat(indexPath); err != nil {
		return
	}

	router.Static("/assets", filepath.Join(staticDir, "assets"))

	router.GET("/", func(c *gin.Context) {
		c.File(indexPath)
	})

	router.NoRoute(func(c *gin.Context) {
		path := c.Request.URL.Path
		if strings.HasPrefix(path, "/api") || path == "/healthz" {
			c.JSON(http.StatusNotFound, gin.H{"error": "route not found"})
			return
		}

		c.File(indexPath)
	})
}

