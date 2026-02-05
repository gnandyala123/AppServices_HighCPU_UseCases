# Azure CPU Demo (.NET)

A minimal ASP.NET Core app that simulates high CPU usage on Azure App Services. It exposes a `/cpu` endpoint that spins all available processor cores for a configurable duration using `Parallel.For`.

## Endpoints

| Endpoint | Description |
|---|---|
| `/` | Info page listing all available endpoints |
| `/health` | Health check (useful for App Service health probes) |
| `/info` | Environment info — machine name, PID, processor count, OS, .NET version |
| `/cpu?seconds=10` | Burns CPU on all cores for N seconds (default 10, max 300) |

## Run Locally

Requires [.NET 10 SDK (preview)](https://dotnet.microsoft.com/download/dotnet/10.0).

```bash
dotnet run
```

The app starts on `http://localhost:5202`. Hit `/cpu?seconds=10` to trigger a 10-second CPU burn.

## Deploy to Azure App Service (Code)

1. Create a **Linux** App Service with **.NET 10 (preview)** runtime.
2. Deploy using one of:
   - **VS Code** Azure App Service extension
   - **Azure CLI**:
     ```bash
     dotnet publish -c Release -o ./publish
     cd publish && zip -r ../app.zip .
     az webapp deploy --name <app-name> --resource-group <rg> --src-path ../app.zip
     ```
   - **GitHub Actions** or **Azure DevOps** CI/CD pipeline
3. Browse to your app URL and call `/cpu?seconds=30` to trigger high CPU.

## Deploy to Azure Web App for Containers (Docker)

### Build and run locally with Docker

```bash
docker build -t azure-cpu-demo-dotnet .
docker run -p 8080:8080 azure-cpu-demo-dotnet
```

The app will be available at `http://localhost:8080`.

### Push to Azure Container Registry (ACR)

```bash
# Log in to your ACR
az acr login --name <your-acr>

# Tag and push the image
docker tag azure-cpu-demo-dotnet <your-acr>.azurecr.io/azure-cpu-demo-dotnet:latest
docker push <your-acr>.azurecr.io/azure-cpu-demo-dotnet:latest
```

### Create the Web App for Containers

```bash
# Create the web app pointing to your ACR image
az webapp create \
  --name <app-name> \
  --resource-group <rg> \
  --plan <app-service-plan> \
  --container-image-name <your-acr>.azurecr.io/azure-cpu-demo-dotnet:latest

# Set the port (the container exposes 8080)
az webapp config appsettings set \
  --name <app-name> \
  --resource-group <rg> \
  --settings WEBSITES_PORT=8080
```

Alternatively, configure through the Azure Portal:
1. Create a new **Web App** and select **Docker Container** as the publish method.
2. Point it to your ACR image `<your-acr>.azurecr.io/azure-cpu-demo-dotnet:latest`.
3. Under **Configuration > Application settings**, add `WEBSITES_PORT=8080`.

## How It Works

The `/cpu` endpoint uses `Parallel.For` to spin up one tight loop per processor core:

```csharp
Parallel.For(0, coreCount, _ =>
{
    var end = DateTime.UtcNow.AddSeconds(duration);
    while (DateTime.UtcNow < end)
    {
        double dummy = Math.Sqrt(DateTime.UtcNow.Ticks);
    }
});
```

This pins all cores at 100% for the requested duration, making it easy to trigger CPU alerts, test autoscale rules, and practice troubleshooting in App Service diagnostics.

## Project Structure

```
├── Program.cs                      # Main app with all endpoints
├── AzureCpuDemo.csproj             # Project file targeting .NET 10
├── AzureCpuDemo_dotnet.sln         # Solution file
├── Dockerfile                      # Multi-stage Docker build
├── Properties/
│   └── launchSettings.json         # Local dev launch settings
├── appsettings.json                # App configuration
├── appsettings.Development.json    # Dev-specific configuration
└── README.md
```
