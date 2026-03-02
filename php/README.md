# PHP CPU Stress Test — Azure Web App Diagnostic Tool

Replicate high-CPU and crash conditions on an Azure App Service (PHP) for diagnostics, Auto-Heal testing, and monitoring validation.

---

## Files

| File | Purpose |
|------|---------|
| `index.php` | Web dashboard — all stress tests accessible via browser |
| `cpu-stress.php` | Stress engine — called directly with query parameters |
| `web.config` | IIS/FastCGI config for **Windows** App Service (removes 110s timeout) |
| `.htaccess` | Apache config for **Linux** App Service |
| `.user.ini` | PHP INI overrides (both platforms) — sets `max_execution_time = 600` |

---

## Prerequisites

- An Azure App Service with **PHP 7.4 / 8.x** runtime
- A **test/diagnostic** App Service — **never run on production**
- (Optional) Azure Monitor / Application Insights enabled on the App Service

---

## Deploy as Docker Container (Azure Web App for Containers)

### Build & Run Locally First

```bash
cd php-cpu-stress

# Build the image
docker build -t php-cpu-stress .

# Run locally on port 8080
docker run -p 8080:80 php-cpu-stress

# Open http://localhost:8080
```

### Push to Azure Container Registry (ACR) and Deploy

```bash
# 1. Create ACR (skip if you already have one)
az acr create \
  --resource-group <your-rg> \
  --name <acr-name> \
  --sku Basic \
  --admin-enabled true

# 2. Login and push image
az acr login --name <acr-name>
docker tag php-cpu-stress <acr-name>.azurecr.io/php-cpu-stress:latest
docker push <acr-name>.azurecr.io/php-cpu-stress:latest

# 3. Create App Service Plan (Linux required for containers)
az appservice plan create \
  --name <plan-name> \
  --resource-group <your-rg> \
  --is-linux \
  --sku B2           # B2 = 2 vCores — good for stress testing

# 4. Create the Web App for Containers
az webapp create \
  --resource-group <your-rg> \
  --plan <plan-name> \
  --name <app-name> \
  --deployment-container-image-name <acr-name>.azurecr.io/php-cpu-stress:latest

# 5. Link ACR credentials
az webapp config container set \
  --name <app-name> \
  --resource-group <your-rg> \
  --docker-custom-image-name <acr-name>.azurecr.io/php-cpu-stress:latest \
  --docker-registry-server-url https://<acr-name>.azurecr.io \
  --docker-registry-server-user <acr-name> \
  --docker-registry-server-password $(az acr credential show --name <acr-name> --query passwords[0].value -o tsv)

# 6. Open the app
az webapp browse --name <app-name> --resource-group <your-rg>
```

### Quick Deploy with Docker Hub (no ACR needed)

```bash
# Push to Docker Hub
docker login
docker tag php-cpu-stress <dockerhub-username>/php-cpu-stress:latest
docker push <dockerhub-username>/php-cpu-stress:latest

# Deploy directly from Docker Hub
az webapp create \
  --resource-group <your-rg> \
  --plan <plan-name> \
  --name <app-name> \
  --deployment-container-image-name <dockerhub-username>/php-cpu-stress:latest
```

---

## Deploy to Azure (PHP native — no container)

### Option 1 — Azure CLI (ZIP deploy, fastest)

```bash
# 1. Zip the app
cd php-cpu-stress
zip -r ../cpu-stress.zip .

# 2. Deploy via az cli
az webapp deploy \
  --resource-group <your-rg> \
  --name <your-app-name> \
  --src-path ../cpu-stress.zip \
  --type zip
```

### Option 2 — FTP / Kudu drag-and-drop

1. Go to **App Service → Deployment Center → FTPS credentials**
2. Connect with any FTP client (FileZilla, WinSCP)
3. Upload all files to `/site/wwwroot/`

### Option 3 — VS Code Azure Extension

1. Install the **Azure App Service** VS Code extension
2. Right-click the `php-cpu-stress` folder → **Deploy to Web App**
3. Select your subscription and App Service

### Option 4 — GitHub Actions (CI/CD)

```yaml
# .github/workflows/deploy.yml
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: azure/webapps-deploy@v2
        with:
          app-name: ${{ secrets.AZURE_APP_NAME }}
          publish-profile: ${{ secrets.AZURE_PUBLISH_PROFILE }}
          package: .
```

---

## Remove PHP Execution Time Limit (Critical)

By default Azure App Service kills PHP scripts after **~110 seconds (IIS)** or **30 seconds (Apache)**. The `.user.ini` and `web.config`/`.htaccess` files handle this, but verify:

1. **Kudu console** → `https://<app>.scm.azurewebsites.net/DebugConsole`
2. Run: `php -r "echo ini_get('max_execution_time');"`
3. Should return `600`

If still limited, set via **App Service → Configuration → Application Settings**:
```
PHP_INI_SCAN_DIR = d:\home\site\wwwroot
```

---

## Usage

### Browser Dashboard

Navigate to `https://<your-app>.azurewebsites.net/`

The dashboard provides:
- **Server info** (PHP version, memory limit, etc.)
- **Quick tests** — pre-configured low / medium / high / infinite scenarios
- **Custom test** — choose mode, duration, and worker count
- **Parallel tabs** — open N browser tabs simultaneously to simulate concurrent load

### Direct URL (for scripted/load-tool use)

```
https://<app>.azurewebsites.net/cpu-stress.php?mode=combined&duration=60
```

#### Query Parameters

| Parameter | Values | Default | Description |
|-----------|--------|---------|-------------|
| `mode` | `primes`, `fibonacci`, `hashing`, `sorting`, `regex`, `combined`, `infinite` | `combined` | CPU stress algorithm |
| `duration` | integer (seconds) | `30` | How long to run (ignored for `infinite`) |
| `no_limit` | `0` or `1` | `0` | Call `set_time_limit(0)` to disable PHP timeout |
| `workers` | 1–16 | `1` | Fork additional child processes (requires `proc_open`) |

#### Mode Reference

| Mode | Algorithm | CPU Profile |
|------|-----------|-------------|
| `primes` | Sieve of Eratosthenes to 200,000 | Steady ~60–80% |
| `fibonacci` | Recursive fib(36) | Spiky ~90–100% |
| `hashing` | SHA-256 × 150,000 | Sustained ~70–85% |
| `sorting` | Sort 300,000 random ints | Memory + CPU burst |
| `regex` | ReDoS catastrophic backtrack | Thread-locking ~100% |
| `combined` | Rotates through all above | High, variable |
| `infinite` | Continuous primes, no exit | 100% until killed |

---

## Replicating a High-CPU Crash in Azure

### Step 1 — Single-threaded ramp

Start with a moderate load to confirm monitoring is working:

```
https://<app>.azurewebsites.net/cpu-stress.php?mode=primes&duration=120
```

Watch: **App Service → Metrics → CPU Percentage** — should climb to 80–100%.

### Step 2 — Saturate all workers

Open 4–8 browser tabs each hitting:

```
https://<app>.azurewebsites.net/cpu-stress.php?mode=infinite&no_limit=1
```

Or use Apache Bench:

```bash
ab -n 1000 -c 8 "https://<app>.azurewebsites.net/cpu-stress.php?mode=combined&duration=120&no_limit=1"
```

Or Azure Load Testing (recommended — no local machine needed):

```yaml
# load-test.yaml (Azure Load Testing)
testName: PHP CPU Stress
engineInstances: 2
testPlan:
  duration: 5m
  concurrentUsers: 20
  targetUrl: https://<app>.azurewebsites.net/cpu-stress.php?mode=infinite&no_limit=1
```

### Step 3 — Trigger Auto-Heal / App crash

Use the `infinite` mode with multiple concurrent requests. The App Service worker process(es) will be consumed at 100% CPU. Depending on your App Service Plan SKU:
- **Free/Shared** — App Service throttles CPU → HTTP 429 / slow responses
- **Basic/Standard** — Worker processes accumulate → HTTP queue grows → 503s
- **Auto-Heal configured** — Triggers recycle when CPU threshold is met

### Step 4 — Capture diagnostics during the event

1. **Kudu Process Explorer** — `https://<app>.scm.azurewebsites.net/ProcessExplorer/`
   - Shows live PHP-CGI processes and CPU %
   - Click a PID → **Create Dump** for a memory/CPU dump

2. **App Service Diagnostics** → **Diagnose and Solve Problems** → **High CPU Analysis**

3. **Activity Log** — captures auto-scale events, restarts, allocation failures

4. **Application Insights Live Metrics** — real-time request rate, CPU, failure rate

---

## Recommended Azure Monitoring Setup (before running tests)

```bash
# Enable Application Insights
az monitor app-insights component create \
  --app <app-name>-insights \
  --location eastus \
  --resource-group <rg> \
  --application-type web

# Link to App Service
az webapp config appsettings set \
  --name <app-name> \
  --resource-group <rg> \
  --settings APPINSIGHTS_INSTRUMENTATIONKEY=<key>

# Create CPU alert (notify when CPU > 90% for 5 minutes)
az monitor metrics alert create \
  --name "High CPU Alert" \
  --resource-group <rg> \
  --scopes /subscriptions/<sub>/resourceGroups/<rg>/providers/Microsoft.Web/sites/<app-name> \
  --condition "avg CpuPercentage > 90" \
  --window-size 5m \
  --evaluation-frequency 1m \
  --action <action-group-id>
```

---

## Configure Auto-Heal to Recycle on High CPU

In Azure Portal: **App Service → Configuration → General settings → Auto Heal → On**

Or via ARM / Bicep:

```json
{
  "autoHealEnabled": true,
  "autoHealRules": {
    "triggers": {
      "privateBytesInKB": 0,
      "requests": null,
      "slowRequests": null,
      "statusCodes": null
    },
    "actions": {
      "actionType": "Recycle",
      "minProcessExecutionTime": "00:02:00"
    }
  }
}
```

> Note: CPU-based Auto-Heal triggers require **Standard** tier or above.

---

## Cleanup

After testing, **stop or delete the App Service** to avoid unexpected charges, especially if you used a Standard/Premium SKU.

```bash
# Stop the app (keeps configuration)
az webapp stop --name <app-name> --resource-group <rg>

# Or delete entirely
az webapp delete --name <app-name> --resource-group <rg>
```

---

## Security Note

This tool intentionally causes resource exhaustion. **Never deploy to a production environment or a shared App Service Plan.**
