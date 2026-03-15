package api

import (
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"github.com/gin-gonic/gin"
)

func mountFrontend(router *gin.Engine, staticDir string, basePath string) {
	info, err := os.Stat(staticDir)
	if err != nil || !info.IsDir() {
		return
	}

	indexPath := filepath.Join(staticDir, "index.html")
	if _, err := os.Stat(indexPath); err != nil {
		return
	}

	basePath = normalizeBasePath(basePath)
	assetsPath := "/assets"
	if basePath != "/" {
		assetsPath = strings.TrimSuffix(basePath, "/") + "/assets"
	}
	router.Static(assetsPath, filepath.Join(staticDir, "assets"))

	if basePath == "/" {
		router.GET("/", func(c *gin.Context) {
			c.File(indexPath)
		})
	} else {
		trimmed := strings.TrimSuffix(basePath, "/")
		router.GET("/", func(c *gin.Context) {
			c.Redirect(http.StatusTemporaryRedirect, basePath)
		})
		router.GET(trimmed, func(c *gin.Context) {
			c.Redirect(http.StatusTemporaryRedirect, basePath)
		})
		router.GET(basePath, func(c *gin.Context) {
			c.File(indexPath)
		})
	}

	router.NoRoute(func(c *gin.Context) {
		path := c.Request.URL.Path
		if strings.HasPrefix(path, "/api") || path == "/healthz" {
			c.JSON(http.StatusNotFound, gin.H{"error": "route not found"})
			return
		}

		if basePath != "/" {
			prefix := strings.TrimSuffix(basePath, "/")
			if path != prefix && !strings.HasPrefix(path, prefix+"/") {
				c.Redirect(http.StatusTemporaryRedirect, basePath)
				return
			}
		}

		c.File(indexPath)
	})
}

func normalizeBasePath(path string) string {
	if path == "" || path == "/" {
		return "/"
	}
	if path[0] != '/' {
		path = "/" + path
	}
	if path[len(path)-1] != '/' {
		path += "/"
	}
	return path
}
