BACKEND_DIR := backend
FRONTEND_DIR := frontend

.PHONY: backend-run frontend-install frontend-dev frontend-build

backend-run:
	cd $(BACKEND_DIR) && go run ./cmd/server

frontend-install:
	cd $(FRONTEND_DIR) && npm install

frontend-dev:
	cd $(FRONTEND_DIR) && npm run dev

frontend-build:
	cd $(FRONTEND_DIR) && npm run build

.PHONY: docker-up docker-down docker-build

docker-build:
	docker compose build

docker-up:
	docker compose up --build

docker-down:
	docker compose down
