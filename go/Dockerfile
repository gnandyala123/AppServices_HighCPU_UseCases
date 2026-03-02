# ─────────────────────────────────────────────────────────────────────────────
# Go CPU Stress Test — Azure Web App for Containers
# Multi-stage build: compile in golang image, run in distroless/minimal image
# ─────────────────────────────────────────────────────────────────────────────

# ── Stage 1: Build ────────────────────────────────────────────────────────────
FROM golang:1.22-alpine AS builder

WORKDIR /app

# Copy module files first for layer caching
COPY go.mod ./

# Download dependencies (none for this app — stdlib only)
RUN go mod download

# Copy source and build a statically-linked binary
COPY main.go ./

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -ldflags="-s -w" -o /cpu-stress-server .

# ── Stage 2: Run ──────────────────────────────────────────────────────────────
FROM alpine:3.19

# curl for health check
RUN apk add --no-cache curl

WORKDIR /app

COPY --from=builder /cpu-stress-server /app/cpu-stress-server

# Azure App Service uses PORT env var (default 8080)
EXPOSE 8080

# Health check — Azure also does its own liveness probes
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:${PORT:-8080}/ || exit 1

ENV PORT=8080

ENTRYPOINT ["/app/cpu-stress-server"]
