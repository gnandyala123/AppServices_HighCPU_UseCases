# CPU Chaos Lab

A fun Python Flask app designed to simulate high CPU scenarios on Azure App Services. Use it to practice monitoring, alerting, and troubleshooting CPU issues.

## Endpoints

| Endpoint | Severity | Description |
|---|---|---|
| `/` | None | Web UI dashboard with buttons for all endpoints |
| `/pi?iterations=5000000` | Low | Computes Pi using the Leibniz series in a pure-Python loop |
| `/primes?limit=200000` | Medium | Finds all primes up to N via trial division (no sieve) |
| `/hash-storm?rounds=3000000` | High | Chains millions of SHA-256 hashes sequentially |
| `/fibonacci?n=38` | High | Recursive Fibonacci with no memoization (exponential time) |
| `/matrix?size=250` | Medium | Triple-nested-loop NxN matrix multiplication in pure Python |
| `/burn-start?workers=4` | **Extreme** | Spawns background OS processes that pin CPU at 100% continuously |
| `/burn-stop` | — | Stops all background burn processes |
| `/health` | — | Health check endpoint |

## Run Locally

```bash
pip install -r requirements.txt
python app.py
```

The app starts on `http://localhost:8000`.

## Deploy to Azure App Service (Code)

1. Create a **Linux** App Service with **Python 3.11+** runtime.
2. Deploy the code using one of:
   - **VS Code** Azure App Service extension
   - **Azure CLI**: `az webapp up --name <app-name> --resource-group <rg> --runtime "PYTHON:3.11"`
   - **ZIP Deploy**: zip the project and deploy via the Kudu API
3. In the Azure Portal, go to **Configuration > General Settings** and set the **Startup Command** to:
   ```
   gunicorn --bind=0.0.0.0:8000 --timeout 600 --workers 2 app:app
   ```
4. Browse to your app URL and start clicking buttons.

## Deploy to Azure Web App for Containers (Docker)

### Build and run locally with Docker

```bash
docker build -t cpu-chaos-lab .
docker run -p 8000:8000 cpu-chaos-lab
```

The app will be available at `http://localhost:8000`.

### Push to Azure Container Registry (ACR)

```bash
# Log in to your ACR
az acr login --name <your-acr>

# Tag and push the image
docker tag cpu-chaos-lab <your-acr>.azurecr.io/cpu-chaos-lab:latest
docker push <your-acr>.azurecr.io/cpu-chaos-lab:latest
```

### Create the Web App for Containers

```bash
# Create the web app pointing to your ACR image
az webapp create \
  --name <app-name> \
  --resource-group <rg> \
  --plan <app-service-plan> \
  --container-image-name <your-acr>.azurecr.io/cpu-chaos-lab:latest

# Set the port (the container exposes 8000)
az webapp config appsettings set \
  --name <app-name> \
  --resource-group <rg> \
  --settings WEBSITES_PORT=8000
```

Alternatively, configure this through the Azure Portal:
1. Create a new **Web App** and select **Docker Container** as the publish method.
2. Point it to your ACR image `<your-acr>.azurecr.io/cpu-chaos-lab:latest`.
3. Under **Configuration > Application settings**, add `WEBSITES_PORT=8000`.

## Usage Tips

- Start with the **low severity** endpoints to see a small CPU bump in App Service metrics.
- Use **Infinite Burn** to sustain 100% CPU and trigger autoscale rules or alerts.
- Always hit **Stop Burn** (or call `/burn-stop`) when done — the processes run until explicitly stopped.
- Adjust query parameters (`iterations`, `limit`, `rounds`, `n`, `size`, `workers`) to dial the intensity up or down.

## Project Structure

```
├── app.py              # Flask application with all endpoints and inline UI
├── requirements.txt    # Python dependencies (Flask, Gunicorn)
├── startup.sh          # Gunicorn startup command for App Services
├── Dockerfile          # Container image for Web App for Containers
├── .dockerignore       # Files excluded from Docker build
└── README.md
```
