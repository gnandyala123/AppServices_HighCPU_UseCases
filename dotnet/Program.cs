using System.Diagnostics;

var builder = WebApplication.CreateBuilder(args);
var app = builder.Build();

// Home - lists available endpoints
app.MapGet("/", () => Results.Ok(new
{
    status = "running",
    endpoints = new[]
    {
        "GET  /                    - this info page",
        "GET  /health              - health check",
        "GET  /info                - environment info",
        "GET  /cpu?seconds=10      - burn CPU for N seconds (default 10, max 300)",
    }
}));

// Health check endpoint (useful for Azure App Service health probes)
app.MapGet("/health", () => Results.Ok(new { status = "healthy", timestamp = DateTime.UtcNow }));

// Environment info endpoint
app.MapGet("/info", () => Results.Ok(new
{
    machineName = Environment.MachineName,
    processId = Environment.ProcessId,
    processorCount = Environment.ProcessorCount,
    osVersion = Environment.OSVersion.ToString(),
    dotnetVersion = Environment.Version.ToString(),
    timestamp = DateTime.UtcNow
}));

// High CPU endpoint - spins all available cores for the requested duration
app.MapGet("/cpu", (int? seconds) =>
{
    var duration = Math.Clamp(seconds ?? 10, 1, 300);
    var sw = Stopwatch.StartNew();
    var coreCount = Environment.ProcessorCount;

    Parallel.For(0, coreCount, _ =>
    {
        var end = DateTime.UtcNow.AddSeconds(duration);
        while (DateTime.UtcNow < end)
        {
            double dummy = Math.Sqrt(DateTime.UtcNow.Ticks);
        }
    });

    sw.Stop();
    return Results.Ok(new
    {
        message = $"Burned CPU on {coreCount} cores for ~{duration}s",
        actualElapsed = sw.Elapsed.ToString(),
        timestamp = DateTime.UtcNow
    });
});

app.Run();
