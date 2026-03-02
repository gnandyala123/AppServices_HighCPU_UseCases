<?php
/**
 * PHP CPU Stress Test - Azure Web App Diagnostic Tool
 * Replicates high CPU scenarios to diagnose App Service behavior
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP CPU Stress Test - Azure Web App Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f4f4f4; }
        h1 { color: #0078d4; border-bottom: 2px solid #0078d4; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 20px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; border-radius: 4px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; font-size: 14px; }
        .btn-primary  { background: #0078d4; color: white; }
        .btn-warning  { background: #ff8c00; color: white; }
        .btn-danger   { background: #d13438; color: white; }
        .btn-success  { background: #107c10; color: white; }
        .btn:hover { opacity: 0.85; }
        label { display: block; margin: 8px 0 3px; font-weight: bold; }
        input[type=number] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; width: 100px; }
        .info { background: #e6f2fb; border-left: 4px solid #0078d4; padding: 10px 15px; border-radius: 4px; margin: 10px 0; font-size: 13px; }
        .warn { background: #fff4ce; border-left: 4px solid #ff8c00; padding: 10px 15px; border-radius: 4px; margin: 10px 0; font-size: 13px; }
        .danger { background: #fde7e9; border-left: 4px solid #d13438; padding: 10px 15px; border-radius: 4px; margin: 10px 0; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #eee; }
        th { background: #f0f0f0; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media(max-width:600px){ .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<h1>PHP CPU Stress Test</h1>
<p>Azure Web App Diagnostic Tool — use the scenarios below to replicate high CPU and crash conditions for App Service analysis.</p>

<div class="warn">
    <strong>Warning:</strong> These tests intentionally spike CPU on the server. Run only on a <strong>dedicated diagnostic/test App Service</strong>, never on production.
</div>

<!-- ── Server Info ── -->
<div class="card">
    <h2>Server Info</h2>
    <table>
        <tr><th>Property</th><th>Value</th></tr>
        <tr><td>PHP Version</td><td><?= PHP_VERSION ?></td></tr>
        <tr><td>Server OS</td><td><?= PHP_OS ?></td></tr>
        <tr><td>Server Software</td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></td></tr>
        <tr><td>Max Execution Time</td><td><?= ini_get('max_execution_time') ?>s</td></tr>
        <tr><td>Memory Limit</td><td><?= ini_get('memory_limit') ?></td></tr>
        <tr><td>CPU Cores (PHP)</td><td><?= (function_exists('cpu_count') ? cpu_count() : (is_readable('/proc/cpuinfo') ? substr_count(file_get_contents('/proc/cpuinfo'), 'processor') : 'N/A')) ?></td></tr>
        <tr><td>Current Time (UTC)</td><td><?= gmdate('Y-m-d H:i:s') ?></td></tr>
    </table>
</div>

<!-- ── Quick Fire Tests ── -->
<div class="card">
    <h2>Quick Stress Tests</h2>
    <div class="info">Click a button to immediately trigger that CPU scenario. Results open in a new tab.</div>
    <div class="grid">
        <div>
            <strong>Low — Warm-up (5s)</strong>
            <p style="font-size:13px;color:#555">Runs prime sieve for 5 seconds. Causes ~40-60% CPU on a single core.</p>
            <a class="btn btn-success" href="cpu-stress.php?mode=primes&duration=5" target="_blank">Run 5s Prime Sieve</a>
        </div>
        <div>
            <strong>Medium — Sustained (30s)</strong>
            <p style="font-size:13px;color:#555">Fibonacci + hashing loop. Pushes CPU to ~80-90% for 30 seconds.</p>
            <a class="btn btn-primary" href="cpu-stress.php?mode=fibonacci&duration=30" target="_blank">Run 30s Fibonacci</a>
        </div>
        <div>
            <strong>High — Spike (60s)</strong>
            <p style="font-size:13px;color:#555">Nested math + string ops. Spikes CPU to 95-100% for 60 seconds.</p>
            <a class="btn btn-warning" href="cpu-stress.php?mode=combined&duration=60" target="_blank">Run 60s Combined</a>
        </div>
        <div>
            <strong>Critical — Crash Simulation</strong>
            <p style="font-size:13px;color:#555">Infinite CPU burn. Process will be killed by App Service / OS when limits are hit.</p>
            <a class="btn btn-danger" href="cpu-stress.php?mode=infinite" target="_blank" onclick="return confirm('This will crash/freeze the worker process. Continue?')">Run Infinite Burn</a>
        </div>
    </div>
</div>

<!-- ── Custom Test ── -->
<div class="card">
    <h2>Custom Stress Test</h2>
    <form action="cpu-stress.php" method="GET" target="_blank">
        <div class="grid">
            <div>
                <label>Mode</label>
                <select name="mode" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                    <option value="primes">Prime Number Sieve</option>
                    <option value="fibonacci">Fibonacci (recursive)</option>
                    <option value="hashing">Hashing (SHA-256 loop)</option>
                    <option value="sorting">Array Sort (large dataset)</option>
                    <option value="regex">Regex Catastrophic Backtrack</option>
                    <option value="combined" selected>Combined (all above)</option>
                    <option value="infinite">Infinite Burn (no timeout)</option>
                </select>
            </div>
            <div>
                <label>Duration (seconds)</label>
                <input type="number" name="duration" value="30" min="1" max="600">
            </div>
            <div>
                <label>Parallel Workers (fork)</label>
                <input type="number" name="workers" value="1" min="1" max="16">
                <small style="color:#888">Requires proc_open enabled</small>
            </div>
            <div>
                <label>Disable PHP Time Limit</label>
                <select name="no_limit" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                    <option value="0">No (respect max_execution_time)</option>
                    <option value="1">Yes (set_time_limit(0))</option>
                </select>
            </div>
        </div>
        <br>
        <button type="submit" class="btn btn-primary">Run Custom Test</button>
    </form>
</div>

<!-- ── Multi-tab Parallel Load ── -->
<div class="card">
    <h2>Parallel Tab Attack (simulate concurrent requests)</h2>
    <div class="info">Opens N browser tabs simultaneously, each running a stress test — simulates concurrent web requests to the App Service to max out CPU across all workers.</div>
    <div class="danger"><strong>Tip:</strong> Combine this with Azure Load Testing or Apache Bench (<code>ab -n 500 -c 50 https://yourapp.azurewebsites.net/cpu-stress.php?mode=combined&duration=60</code>) for a true load replication.</div>
    <label>Number of tabs to open</label>
    <input type="number" id="tabCount" value="4" min="1" max="20" style="margin-bottom:10px;">
    <br>
    <button class="btn btn-warning" onclick="openTabs()">Open Parallel Stress Tabs</button>
    <script>
    function openTabs() {
        var n = parseInt(document.getElementById('tabCount').value, 10);
        for (var i = 0; i < n; i++) {
            window.open('cpu-stress.php?mode=combined&duration=60&worker=' + i, '_blank');
        }
    }
    </script>
</div>

<!-- ── Monitoring Tips ── -->
<div class="card">
    <h2>Azure Monitoring — What to Watch</h2>
    <table>
        <tr><th>Metric / Tool</th><th>Where to Find</th></tr>
        <tr><td>CPU Percentage</td><td>App Service → Metrics → CPU Percentage</td></tr>
        <tr><td>HTTP Queue Length</td><td>App Service Plan → Metrics → Http Queue Length</td></tr>
        <tr><td>Requests</td><td>App Service → Metrics → Requests</td></tr>
        <tr><td>Live Metrics Stream</td><td>App Insights → Live Metrics (if enabled)</td></tr>
        <tr><td>Process Explorer</td><td>Kudu (SCM) → <code>https://&lt;app&gt;.scm.azurewebsites.net/ProcessExplorer/</code></td></tr>
        <tr><td>Memory Dump on High CPU</td><td>Diagnose &amp; Solve Problems → High CPU Analysis</td></tr>
        <tr><td>Auto-Heal trigger</td><td>App Service → Configuration → General → Auto Heal</td></tr>
    </table>
</div>

</body>
</html>
