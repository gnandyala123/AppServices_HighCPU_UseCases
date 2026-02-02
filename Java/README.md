# Azure App Service - High CPU Demo (Java 21) — Event/Delegate Pattern

This Spring Boot application demonstrates a high CPU usage scenario on Azure App Service using the **event/delegate model** (Spring `ApplicationEvent` + `@EventListener`).

## How It Works

The application uses Spring's built-in event publishing mechanism to implement the event/delegate pattern:

1. **Controller** (`CpuStressController`) receives an HTTP request and publishes a `CpuStressEvent` via `ApplicationEventPublisher`.
2. **Delegate/Listener** (`CpuStressListener`) reacts to the event by spawning multiple platform threads that perform tight CPU-bound math loops (`Math.sqrt`, `Math.sin`, `Math.cos`) for a configurable duration, driving CPU utilization to 100%.
3. Once the work completes, a `CpuStressCompletedEvent` is published.
4. **Completion Delegate** (`CpuStressCompletionLogger`) listens for the completion event and logs summary statistics (thread count, total iterations, elapsed time).

```
HTTP Request
    │
    ▼
CpuStressController ──publishes──► CpuStressEvent
                                        │
                                        ▼
                                  CpuStressListener (delegate)
                                  ├── Spawns N threads
                                  ├── Each thread runs tight math loop
                                  └── Publishes CpuStressCompletedEvent
                                              │
                                              ▼
                                     CpuStressCompletionLogger (delegate)
                                     └── Logs results
```

## Project Structure

```
Java/
├── pom.xml
├── README.md
└── src/main/java/com/azure/demo/highcpu/
    ├── HighCpuDemoApplication.java
    ├── controller/
    │   └── CpuStressController.java
    ├── events/
    │   ├── CpuStressEvent.java
    │   └── CpuStressCompletedEvent.java
    └── listeners/
        ├── CpuStressListener.java
        └── CpuStressCompletionLogger.java
```

## Prerequisites

- Java 21
- Maven 3.8+
- Docker (for container deployment)
- Azure CLI (for Azure deployment)

## Build and Run Locally

```bash
mvn clean package
java -jar target/highcpu-event-delegate-1.0.0.jar
```

The application starts on port **8080**.

## Build and Run with Docker

```bash
# Build the Docker image
docker build -t highcpu-event-delegate .

# Run the container locally
docker run -p 8080:8080 highcpu-event-delegate
```

## Endpoints

| Endpoint | Description |
|---|---|
| `GET /` | Info page with available endpoints and parameters |
| `GET /stress?threads=4&duration=30` | Triggers high CPU via event/delegate model |

### Parameters for `/stress`

| Parameter | Default | Description |
|---|---|---|
| `threads` | 4 | Number of CPU-burning threads to spawn |
| `duration` | 30 | Duration in seconds to sustain high CPU |

### Example

```bash
# Trigger 4 threads burning CPU for 30 seconds
curl http://localhost:8080/stress?threads=4&duration=30
```

## Deploy to Azure App Service (JAR)

```bash
# Login to Azure
az login

# Create a resource group
az group create --name myResourceGroup --location eastus

# Create an App Service plan (Linux, B1 or higher)
az appservice plan create --name myPlan --resource-group myResourceGroup --sku B1 --is-linux

# Create the web app with Java 21
az webapp create --name <your-app-name> --resource-group myResourceGroup --plan myPlan --runtime "JAVA:21-java21"

# Deploy the JAR
az webapp deploy --resource-group myResourceGroup --name <your-app-name> --src-path target/highcpu-event-delegate-1.0.0.jar --type jar
```

## Deploy to Azure App Service (Docker Container)

### Option 1: Using Azure Container Registry (ACR)

```bash
# Login to Azure
az login

# Create a resource group (skip if already created)
az group create --name myResourceGroup --location eastus

# Create an Azure Container Registry
az acr create --resource-group myResourceGroup --name <your-acr-name> --sku Basic

# Build and push the image using ACR Build (no local Docker needed)
az acr build --registry <your-acr-name> --image highcpu-event-delegate:latest .

# Create an App Service plan (Linux, B1 or higher — skip if already created)
az appservice plan create --name myPlan --resource-group myResourceGroup --sku B1 --is-linux

# Create the web app from the container image
az webapp create --resource-group myResourceGroup --plan myPlan \
  --name <your-app-name> \
  --deployment-container-image-name <your-acr-name>.azurecr.io/highcpu-event-delegate:latest

# Enable ACR admin credentials so App Service can pull the image
az acr update --name <your-acr-name> --admin-enabled true

# Get ACR credentials and configure the web app
ACR_PASSWORD=$(az acr credential show --name <your-acr-name> --query "passwords[0].value" -o tsv)

az webapp config container set --resource-group myResourceGroup --name <your-app-name> \
  --container-image-name <your-acr-name>.azurecr.io/highcpu-event-delegate:latest \
  --container-registry-url https://<your-acr-name>.azurecr.io \
  --container-registry-user <your-acr-name> \
  --container-registry-password $ACR_PASSWORD

# Set WEBSITES_PORT so App Service knows which port the container listens on
az webapp config appsettings set --resource-group myResourceGroup --name <your-app-name> \
  --settings WEBSITES_PORT=8080
```

### Option 2: Using Docker Hub

```bash
# Build and tag the image
docker build -t <your-dockerhub-username>/highcpu-event-delegate:latest .

# Push to Docker Hub
docker login
docker push <your-dockerhub-username>/highcpu-event-delegate:latest

# Create the web app from the Docker Hub image
az webapp create --resource-group myResourceGroup --plan myPlan \
  --name <your-app-name> \
  --deployment-container-image-name <your-dockerhub-username>/highcpu-event-delegate:latest

# Set WEBSITES_PORT
az webapp config appsettings set --resource-group myResourceGroup --name <your-app-name> \
  --settings WEBSITES_PORT=8080
```

### Trigger high CPU after deployment

```
https://<your-app-name>.azurewebsites.net/stress?threads=4&duration=30
```

## Diagnosing High CPU on Azure App Service

Once high CPU is triggered, use these Azure tools to investigate:

- **Diagnose and Solve Problems** > "High CPU" detector in the Azure Portal
- **Metrics** > Monitor `CPU Percentage` under the App Service Plan
- **App Service Diagnostics** > "Availability and Performance" for automated analysis
- **Kudu Console** > Process explorer at `https://<your-app-name>.scm.azurewebsites.net`
