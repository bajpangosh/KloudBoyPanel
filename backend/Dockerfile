FROM golang:1.22-alpine AS builder

WORKDIR /src

COPY go.mod ./
RUN go mod download

COPY . .

RUN go build -o /out/kloudboy-server ./cmd/server
RUN go build -o /out/kloudboy ./cmd/kloudboy

FROM alpine:3.20

RUN apk add --no-cache ca-certificates tzdata

WORKDIR /app

COPY --from=builder /out/kloudboy-server /usr/local/bin/kloudboy-server
COPY --from=builder /out/kloudboy /usr/local/bin/kloudboy

ENV KLOUDBOY_ENV=development
ENV KLOUDBOY_HTTP_HOST=0.0.0.0
ENV KLOUDBOY_HTTP_PORT=8443
ENV KLOUDBOY_DATA_DIR=/app/data
ENV KLOUDBOY_SITES_ROOT=/app/data/sites
ENV KLOUDBOY_BACKUPS_ROOT=/app/data/backups
ENV KLOUDBOY_LOGS_ROOT=/app/data/logs
ENV KLOUDBOY_GENERATED_ROOT=/app/data/generated
ENV KLOUDBOY_DB_PATH=/app/data/kloudboy.db
ENV KLOUDBOY_PANEL_DOMAIN=panel.local
ENV KLOUDBOY_PANEL_PORT=8443
ENV KLOUDBOY_PANEL_HIDDEN_PATH=/kb-admin-demo/
ENV KLOUDBOY_TIMEZONE=Asia/Kolkata

EXPOSE 8443

CMD ["kloudboy-server"]

