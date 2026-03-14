package main

import (
	"fmt"
	"log"

	"github.com/kloudboy/panel/backend/internal/app"
	"github.com/kloudboy/panel/backend/internal/services"
	"github.com/spf13/cobra"
)

func main() {
	application, err := app.Bootstrap()
	if err != nil {
		log.Fatalf("bootstrap failed: %v", err)
	}
	defer application.Close()

	rootCmd := &cobra.Command{
		Use:   "kloudboy",
		Short: "KloudBoy Panel command line interface",
	}

	rootCmd.AddCommand(siteCommand(application))
	rootCmd.AddCommand(backupCommand(application))
	rootCmd.AddCommand(updateCommand())
	rootCmd.AddCommand(doctorCommand(application))

	if err := rootCmd.Execute(); err != nil {
		log.Fatal(err)
	}
}

func siteCommand(application *app.Application) *cobra.Command {
	command := &cobra.Command{
		Use:   "site",
		Short: "Manage hosted sites",
	}

	var createInput services.CreateSiteInput
	create := &cobra.Command{
		Use:   "create",
		Short: "Create a new site record and filesystem scaffold",
		RunE: func(cmd *cobra.Command, args []string) error {
			result, err := application.SiteService.CreateSite(createInput)
			if err != nil {
				return err
			}

			fmt.Printf("Created site %s with PHP %s\n", result.Site.Domain, result.Site.PHPVersion)
			fmt.Printf("Database: %s / user: %s\n", result.Database.Name, result.Database.Username)
			for _, step := range result.Steps {
				fmt.Printf("- [%s] %s: %s\n", step.Status, step.Name, step.Detail)
			}
			return nil
		},
	}
	create.Flags().StringVar(&createInput.Domain, "domain", "", "Primary site domain")
	create.Flags().StringVar(&createInput.PHPVersion, "php", "8.3", "PHP version")
	create.Flags().BoolVar(&createInput.InstallWordPress, "wordpress", true, "Include WordPress install in the plan")
	create.Flags().BoolVar(&createInput.EnableRedis, "redis", true, "Enable Redis cache in the plan")
	create.Flags().StringVar(&createInput.Template, "template", "standard-wordpress", "Provisioning template")
	_ = create.MarkFlagRequired("domain")

	var domain string
	deleteCmd := &cobra.Command{
		Use:   "delete",
		Short: "Mark a site as deleted",
		RunE: func(cmd *cobra.Command, args []string) error {
			result, err := application.SiteService.DeleteSite(domain)
			if err != nil {
				return err
			}
			fmt.Println(result.Message)
			return nil
		},
	}
	deleteCmd.Flags().StringVar(&domain, "domain", "", "Primary site domain")
	_ = deleteCmd.MarkFlagRequired("domain")

	list := &cobra.Command{
		Use:   "list",
		Short: "List known sites",
		RunE: func(cmd *cobra.Command, args []string) error {
			sites, err := application.SiteService.ListSites()
			if err != nil {
				return err
			}
			for _, site := range sites {
				fmt.Printf("%s\t%s\t%s\tredis=%t\n", site.Domain, site.PHPVersion, site.Status, site.RedisEnabled)
			}
			return nil
		},
	}

	command.AddCommand(create, deleteCmd, list)
	return command
}

func backupCommand(application *app.Application) *cobra.Command {
	command := &cobra.Command{
		Use:   "backup",
		Short: "Run or inspect backups",
	}

	var domain string
	run := &cobra.Command{
		Use:   "run",
		Short: "Create a local backup archive for a site",
		RunE: func(cmd *cobra.Command, args []string) error {
			record, err := application.BackupService.CreateBackup(domain)
			if err != nil {
				return err
			}
			fmt.Printf("Backup created: %s (%d bytes)\n", record.Path, record.SizeBytes)
			return nil
		},
	}
	run.Flags().StringVar(&domain, "domain", "", "Primary site domain")
	_ = run.MarkFlagRequired("domain")

	command.AddCommand(run)
	return command
}

func updateCommand() *cobra.Command {
	return &cobra.Command{
		Use:   "update",
		Short: "Show the planned update workflow",
		Run: func(cmd *cobra.Command, args []string) {
			fmt.Println("kloudboy update")
			fmt.Println("1. Check latest version")
			fmt.Println("2. Backup panel")
			fmt.Println("3. Download update")
			fmt.Println("4. Apply update")
			fmt.Println("5. Restart services")
		},
	}
}

func doctorCommand(application *app.Application) *cobra.Command {
	return &cobra.Command{
		Use:   "doctor",
		Short: "Run bootstrap and environment checks",
		RunE: func(cmd *cobra.Command, args []string) error {
			checks, err := application.ServerService.DoctorChecks()
			if err != nil {
				return err
			}

			for _, check := range checks {
				fmt.Printf("[%s] %s: %s\n", check.Status, check.Name, check.Detail)
			}
			return nil
		},
	}
}
