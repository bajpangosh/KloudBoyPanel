FROM node:20-alpine AS frontend-builder

WORKDIR /frontend

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend ./

ARG VITE_API_BASE_URL=
ARG VITE_PANEL_BASE_PATH=/kb-admin-demo/
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
ENV VITE_PANEL_BASE_PATH=${VITE_PANEL_BASE_PATH}

RUN npm run build

FROM golang:1.22-alpine AS backend-builder

WORKDIR /backend

COPY backend/go.mod ./
RUN go mod download

COPY backend ./

RUN go build -o /out/kloudboy-server ./cmd/server
RUN go build -o /out/kloudboy ./cmd/kloudboy

FROM alpine:3.20

RUN apk add --no-cache ca-certificates tzdata

WORKDIR /app

COPY --from=backend-builder /out/kloudboy-server /usr/local/bin/kloudboy-server
COPY --from=backend-builder /out/kloudboy /usr/local/bin/kloudboy
COPY --from=frontend-builder /frontend/dist /app/frontend

ENV KLOUDBOY_ENV=development
ENV KLOUDBOY_HTTP_HOST=0.0.0.0
ENV KLOUDBOY_HTTP_PORT=8443
ENV KLOUDBOY_DATA_DIR=/app/data
ENV KLOUDBOY_SITES_ROOT=/app/data/sites
ENV KLOUDBOY_BACKUPS_ROOT=/app/data/backups
ENV KLOUDBOY_LOGS_ROOT=/app/data/logs
ENV KLOUDBOY_GENERATED_ROOT=/app/data/generated
ENV KLOUDBOY_DB_PATH=/app/data/kloudboy.db
ENV KLOUDBOY_STATIC_DIR=/app/frontend
ENV KLOUDBOY_PANEL_DOMAIN=panel.local
ENV KLOUDBOY_PANEL_PORT=8443
ENV KLOUDBOY_PANEL_HIDDEN_PATH=/kb-admin-demo/
ENV KLOUDBOY_TIMEZONE=Asia/Kolkata

EXPOSE 8443

CMD ["kloudboy-server"]
