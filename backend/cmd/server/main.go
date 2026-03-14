package main

import (
	"log"

	"github.com/kloudboy/panel/backend/internal/api"
	"github.com/kloudboy/panel/backend/internal/app"
)

func main() {
	application, err := app.Bootstrap()
	if err != nil {
		log.Fatalf("bootstrap failed: %v", err)
	}
	defer application.Close()

	router := api.NewRouter(application)
	if err := router.Run(application.Config.Address()); err != nil {
		log.Fatalf("server failed: %v", err)
	}
}

