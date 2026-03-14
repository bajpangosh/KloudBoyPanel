package api

import (
	"database/sql"
	"errors"
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/kloudboy/panel/backend/internal/app"
	"github.com/kloudboy/panel/backend/internal/services"
)

type handler struct {
	app *app.Application
}

func NewRouter(application *app.Application) *gin.Engine {
	router := gin.Default()
	router.Use(corsMiddleware())
	h := &handler{app: application}

	router.GET("/healthz", h.healthz)

	api := router.Group("/api")
	{
		api.GET("/dashboard/overview", h.dashboardOverview)
		api.GET("/sites", h.listSites)
		api.GET("/databases", h.listDatabases)
		api.GET("/backups", h.listBackups)
		api.GET("/server/status", h.serverStatus)
		api.GET("/panel/configuration", h.panelConfiguration)
		api.POST("/sites/create", h.createSite)
		api.POST("/sites/delete", h.deleteSite)
		api.POST("/site/create", h.createSite)
		api.POST("/site/delete", h.deleteSite)
		api.POST("/backup", h.createBackup)
		api.POST("/php/change", h.changePHPVersion)
	}

	return router
}

func (h *handler) healthz(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{"status": "ok"})
}

func (h *handler) dashboardOverview(c *gin.Context) {
	payload, err := h.app.ServerService.DashboardOverview()
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusOK, payload)
}

func (h *handler) listSites(c *gin.Context) {
	payload, err := h.app.SiteService.ListSites()
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusOK, payload)
}

func (h *handler) listDatabases(c *gin.Context) {
	payload, err := h.app.SiteService.ListDatabases()
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusOK, payload)
}

func (h *handler) listBackups(c *gin.Context) {
	payload, err := h.app.BackupService.ListBackups(20)
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusOK, payload)
}

func (h *handler) serverStatus(c *gin.Context) {
	payload, err := h.app.ServerService.Status()
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusOK, payload)
}

func (h *handler) panelConfiguration(c *gin.Context) {
	c.JSON(http.StatusOK, h.app.ServerService.Configuration())
}

func (h *handler) createSite(c *gin.Context) {
	var input services.CreateSiteInput
	if err := c.ShouldBindJSON(&input); err != nil {
		writeError(c, err)
		return
	}

	payload, err := h.app.SiteService.CreateSite(input)
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusCreated, payload)
}

func (h *handler) deleteSite(c *gin.Context) {
	var input struct {
		Domain string `json:"domain"`
	}
	if err := c.ShouldBindJSON(&input); err != nil {
		writeError(c, err)
		return
	}

	payload, err := h.app.SiteService.DeleteSite(input.Domain)
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusOK, payload)
}

func (h *handler) createBackup(c *gin.Context) {
	var input struct {
		Domain string `json:"domain"`
	}
	if err := c.ShouldBindJSON(&input); err != nil {
		writeError(c, err)
		return
	}

	payload, err := h.app.BackupService.CreateBackup(input.Domain)
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusCreated, payload)
}

func (h *handler) changePHPVersion(c *gin.Context) {
	var input services.ChangePHPInput
	if err := c.ShouldBindJSON(&input); err != nil {
		writeError(c, err)
		return
	}

	payload, err := h.app.PHPService.ChangeVersion(input)
	if err != nil {
		writeError(c, err)
		return
	}
	c.JSON(http.StatusOK, payload)
}

func writeError(c *gin.Context, err error) {
	status := http.StatusInternalServerError
	if errors.Is(err, sql.ErrNoRows) {
		status = http.StatusNotFound
	} else if errors.Is(err, services.ErrInvalidInput) {
		status = http.StatusBadRequest
	}

	c.JSON(status, gin.H{
		"error": err.Error(),
	})
}
