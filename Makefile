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

.PHONY: docker-single-build docker-single-up docker-single-down

docker-single-build:
	docker compose -f docker-compose.single.yml build

docker-single-up:
	docker compose -f docker-compose.single.yml up --build

docker-single-down:
	docker compose -f docker-compose.single.yml down
