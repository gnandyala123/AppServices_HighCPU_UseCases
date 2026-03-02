# Go CPU Stress Test — Azure App Service Diagnostic Tool

Replicates high-CPU and crash conditions on an Azure App Service (Go) for diagnostics, Auto-Heal testing, and monitoring validation.

---

## Files

| File | Purpose |
|------|---------|
| `main.go` | Go HTTP server — dashboard + stress engine (stdlib only, no dependencies) |
| `go.mod` | Go module definition |
| `Dockerfile` | Multi-stage build → minimal Alpine image for Web App for Containers |
| `web.config` | HttpPlatformHandler config for **Windows** App Service |
| `.dockerignore` | Docker build exclusions |

---

## Stress Modes

| Mode | Algorithm | CPU Profile |
|------|-----------|-------------|
| `primes` | Sieve of Eratosthenes to 300,000 | Steady ~60–80% |
| `fibonacci` | Recursive fib(36) | Spiky ~90–100% |
| `hashing` | SHA-256 × 200,000 | Sustained ~70–85% |
| `sorting` | Sort 500,000 random ints | Memory + CPU burst |
| `montecarlo` | Monte Carlo π (5M samples) | Float-point intensive |
| `combined` | Rotates through all above | High, variable |
| `infinite` | Continuous primes, no exit | 100% until killed |

**Key Go advantage:** set `workers` = number of vCPUs to saturate **all cores simultaneously** using goroutines — much harder to achieve with single-threaded PHP/Python.

---

## Query Parameters (`/stress`)

| Parameter | Values | Default | Description |
|-----------|--------|---------|-------------|
| `mode` | see table above | `combined` | Stress algorithm |
| `duration` | integer (seconds), `0` = infinite | `30` | How long to run |
| `workers` | 1–64 | `1` | Number of parallel goroutines |

---

## Run Locally

### With `go run` (fastest)

```bash
# Requires Go 1.22+
go run main.go
# Open http://localhost:8080
```

### With Docker

```bash
docker build -t go-cpu-stress .
docker run -p 8080:80 -e PORT=80 go-cpu-stress
# Open http://localhost:8080
```

---

## Deploy to Azure

### Option A — Web App for Containers (Docker)

```bash
# 1. Build and push to ACR
az acr create \
  --resource-group <rg> \
  --name <acr-name> \
  --sku Basic \
  --admin-enabled true

az acr login --name <acr-name>
docker build -t <acr-name>.azurecr.io/go-cpu-stress:latest .
docker push <acr-name>.azurecr.io/go-cpu-stress:latest

# 2. Create Linux App Service Plan
az appservice plan create \
  --name <plan-name> \
  --resource-group <rg> \
  --is-linux \
  --sku B2           # 2 vCores — good for goroutine parallelism testing

# 3. Create Web App for Containers
az webapp create \
  --resource-group <rg> \
  --plan <plan-name> \
  --name <app-name> \
  --deployment-container-image-name <acr-name>.azurecr.io/go-cpu-stress:latest

# 4. Configure PORT (Azure injects this, but set explicitly for clarity)
az webapp config appsettings set \
  --name <app-name> \
  --resource-group <rg> \
  --settings PORT=8080 WEBSITES_PORT=8080

# 5. Open
az webapp browse --name <app-name> --resource-group <rg>
```

### Option B — Windows App Service (pre-compiled binary)

```bash
# 1. Cross-compile for Windows
GOOS=windows GOARCH=amd64 go build -o cpu-stress-server.exe .

# 2. Zip and deploy
zip cpu-stress-win.zip cpu-stress-server.exe web.config

az webapp deploy \
  --resource-group <rg> \
  --name <app-name> \
  --src-path cpu-stress-win.zip \
  --type zip
```

### Option C — Linux App Service (pre-compiled binary)

```bash
# 1. Compile for Linux
GOOS=linux GOARCH=amd64 CGO_ENABLED=0 go build -o cpu-stress-server .

# 2. Create startup script
echo './cpu-stress-server' > startup.sh

# 3. Zip and deploy
zip cpu-stress-linux.zip cpu-stress-server startup.sh

az webapp deploy \
  --resource-group <rg> \
  --name <app-name> \
  --src-path cpu-stress-linux.zip \
  --type zip

# 4. Set startup command
az webapp config set \
  --name <app-name> \
  --resource-group <rg> \
  --startup-file './cpu-stress-server'
```

---

## Replicating High-CPU Crash in Azure

### Step 1 — Single goroutine baseline

```
https://<app>.azurewebsites.net/stress?mode=primes&duration=120&workers=1
```

Watch **App Service → Metrics → CPU Percentage** climb to ~50–70%.

### Step 2 — Saturate all vCPUs with goroutines

```
https://<app>.azurewebsites.net/stress?mode=combined&duration=120&workers=<num-vcpus>
```

For a B2 (2 vCores): `workers=2`. For P2v3 (4 vCores): `workers=4`.
CPU should hit 95–100% across all cores.

### Step 3 — Infinite multi-core burn

```
https://<app>.azurewebsites.net/stress?mode=infinite&workers=<num-vcpus>
```

Open this in 4–8 tabs or use a load tool:

```bash
# hey (Go HTTP load tester — install: go install github.com/rakyll/hey@latest)
hey -n 200 -c 8 \
  "https://<app>.azurewebsites.net/stress?mode=infinite&workers=4"

# Apache Bench
ab -n 500 -c 8 \
  "https://<app>.azurewebsites.net/stress?mode=combined&duration=120&workers=4"
```

### Step 4 — Capture diagnostics during the event

| Tool | URL / Location |
|------|---------------|
| **Kudu Process Explorer** | `https://<app>.scm.azurewebsites.net/ProcessExplorer/` |
| **High CPU Analysis** | Portal → Diagnose and Solve Problems → High CPU Analysis |
| **Memory dump** | Kudu → Process Explorer → click PID → Create Dump |
| **App Insights Live Metrics** | Portal → Application Insights → Live Metrics |
| **Activity Log** | Portal → App Service → Activity Log (restarts, recycles) |

---

## Configure Auto-Heal on High CPU

```bash
az webapp config set \
  --name <app-name> \
  --resource-group <rg> \
  --auto-heal-enabled true

# ARM patch for CPU-based trigger (Standard tier+)
az rest --method PATCH \
  --url "https://management.azure.com/subscriptions/<sub>/resourceGroups/<rg>/providers/Microsoft.Web/sites/<app-name>/config/web?api-version=2022-03-01" \
  --body '{
    "properties": {
      "autoHealEnabled": true,
      "autoHealRules": {
        "triggers": { "privateBytesInKB": 0 },
        "actions": {
          "actionType": "Recycle",
          "minProcessExecutionTime": "00:02:00"
        }
      }
    }
  }'
```

---

## Cleanup

```bash
az webapp stop --name <app-name> --resource-group <rg>
# or
az webapp delete --name <app-name> --resource-group <rg>
```

---

## Security Note

This tool intentionally causes resource exhaustion. **Never deploy to production or a shared App Service Plan.**
