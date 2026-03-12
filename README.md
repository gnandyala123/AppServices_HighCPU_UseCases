# AppServices HighCPU Use Cases

A multi-language collection of CPU stress testing tools designed for Azure App Service diagnostics. Use these to validate CPU monitoring, autoscaling policies, auto-healing rules, and Application Insights alerting across different technology stacks.

> **Warning:** Never deploy these tools to production or a shared App Service Plan. Use isolated diagnostic instances only.

---

## Languages Covered

| Language | Framework | Docker | Key Stress Modes |
|----------|-----------|--------|------------------|
| [Java](Java/) | Spring Boot 3.2 | Yes | Multi-threaded math (sqrt, sin, cos) |
| [.NET](dotnet/) | ASP.NET Core / .NET 10 | Yes | `Parallel.For` across all CPU cores |
| [Go](go/) | stdlib only | Yes | Primes, Fibonacci, Hashing, Sorting, Monte Carlo, Infinite |
| [Node.js](nodejs/) | Express 5 + React 19 | No | Server-side blocking loops + browser Web Workers |
| [PHP](php/) | PHP 8.2 + Apache | Yes | Primes, Hashing, Fibonacci, Regex, Matrix, Streaming, Infinite |
| [Python](python/) | Flask 3.1 + Gunicorn | Yes | Pi series, Primes, Hash storm, Fibonacci, Matrix, OS burn |

---

## Quick Start

Each language folder contains its own `README.md` with full setup instructions. The general workflow is:

### Option 1 — Deploy directly to Azure App Service

```bash
# Example: Python
cd python
pip install -r requirements.txt
# Deploy via VS Code, Azure CLI, or ZIP deploy
```

### Option 2 — Run via Docker

```bash
# Build and run any implementation
cd <language-folder>
docker build -t cpu-stress .
docker run -p 8080:8080 cpu-stress
```

Then call the stress endpoint from your browser or `curl`:

```bash
curl "http://localhost:8080/stress?duration=30"
```

---

## Repository Structure

```
AppServices_HighCPU_UseCases/
├── Java/               # Spring Boot — event-driven thread pool stress
│   ├── src/
│   ├── Dockerfile
│   ├── pom.xml
│   └── README.md
├── dotnet/             # ASP.NET Core — Parallel.For CPU burn
│   ├── Program.cs
│   ├── Dockerfile
│   └── README.md
├── go/                 # Pure Go — 7 goroutine-based stress modes
│   ├── main.go
│   ├── Dockerfile
│   ├── web.config
│   └── README.md
├── nodejs/             # Express + React — dual client/server stress
│   ├── server.cjs
│   ├── index.html
│   ├── web.config
│   └── README.md
├── php/                # PHP 8.2 — 7 stress modes with browser dashboard
│   ├── cpu-stress.php
│   ├── index.php
│   ├── Dockerfile
│   └── README.md
└── python/             # Flask — 6 worst-case computational workloads
    ├── app.py
    ├── Dockerfile
    ├── requirements.txt
    └── README.md
```

---

## What Each Implementation Tests

### Java
Uses Spring's `ApplicationEventPublisher` to delegate CPU work across threads. Tests how JVM thread management behaves under load and how Azure detects Java process CPU spikes.

### .NET
`Parallel.For` spawns one task per logical CPU core, achieving near-100% utilization instantly. Tests .NET thread pool behavior and how Azure App Service auto-healing triggers on sustained load.

### Go
Goroutines run 7 distinct algorithms (prime sieve, recursive Fibonacci, SHA-256 hashing, array sorting, Monte Carlo π estimation, combined rotation, and infinite burn). Go's scheduler makes this the easiest implementation for achieving consistent multi-core saturation.

### Node.js
Two stress surfaces: server-side blocking loops (via Express) and browser-side parallel workers (via React + Web Workers). Useful for distinguishing between V8 event loop starvation and true parallel CPU usage visible to Azure's process monitor.

### PHP
Includes extended timeout configuration (`max_execution_time = 600`, Apache timeout 700s) to work around Azure's default PHP request limits. Interactive browser dashboard lets you trigger modes without `curl`.

### Python
Deliberately uses worst-case implementations (recursive Fibonacci without memoization, pure-Python matrix multiplication, unchained SHA-256) to maximize CPU impact per unit of work. Background `/burn-start` spawns OS-level processes for sustained 100% CPU.

---

## Diagnostic Workflow

1. **Deploy** the chosen language app to an isolated Azure App Service instance.
2. **Trigger** a stress endpoint with your desired duration and intensity.
3. **Observe** in the Azure Portal:
   - **Metrics** blade → CPU Percentage
   - **Diagnose and solve problems** → High CPU Analysis
   - **Kudu** (`https://<app>.scm.azurewebsites.net`) → Process Explorer / Memory Dump
   - **Application Insights** → Live Metrics, Performance, Alerts
4. **Validate** that autoscale rules, auto-heal triggers, and alert notifications fire as expected.
5. **Stop** the stress (call `/burn-stop` if using Python's background mode, or let duration expire).

---

## Common Endpoint Patterns

Most implementations follow these conventions (check each `README.md` for exact paths):

| Endpoint | Purpose |
|----------|---------|
| `GET /` | Info page or interactive dashboard |
| `GET /health` | Health check (for App Service probe) |
| `GET /stress?duration=N` | Trigger CPU stress for N seconds |
| `GET /info` | Runtime details (cores, version, hostname) |

---

## Safety Checklist

- [ ] Deployed to a **non-production** App Service Plan
- [ ] App Service Plan is **not shared** with other apps
- [ ] Autoscale / auto-heal rules are configured and ready to test
- [ ] Monitoring alerts are set up before triggering stress
- [ ] You know how to stop the stress (endpoint, Kudu kill process, or app restart)

---

## Contributing

Each language folder is self-contained. To add a new stress mode or language:
1. Create a new folder with the runtime name.
2. Include a `Dockerfile`, `web.config` (if IIS-hosted), and a `README.md` following the existing pattern.
3. Implement at least a `/health` and a stress trigger endpoint.
4. Document the expected CPU behavior and any runtime-specific configuration required for Azure App Service.

---

## License

See [LICENSE](LICENSE) for details.
