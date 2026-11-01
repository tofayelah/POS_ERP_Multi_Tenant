package main

import (
	"log"
	"os"
	"os/signal"
	"syscall"

	"bridge/pkg/config"
	"bridge/pkg/server"
)

func main() {
	cfg := config.DefaultConfig()

	// Environment variable overrides
	if token := os.Getenv("BRIDGE_TOKEN"); token != "" {
		cfg.BridgeToken = token
	}

	srv := server.NewServer(cfg)

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)

	go func() {
		log.Printf("[BRIDGE] Starting hardware print & fiscal bridge on %s...", cfg.ListenAddr)
		if err := srv.Start(); err != nil && err != server.ErrServerClosed {
			log.Fatalf("[BRIDGE] Server failed: %v", err)
		}
	}()

	<-stop
	log.Println("[BRIDGE] Shutting down bridge gracefully...")
	srv.Close()
}
