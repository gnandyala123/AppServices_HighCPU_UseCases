// Go CPU Stress Test — Azure App Service Diagnostic Tool
// Replicates high-CPU and crash conditions for App Service analysis.
//
// Usage:
//   PORT=8080 go run main.go
//
// Endpoints:
//   GET /                        — dashboard HTML
//   GET /stress?mode=X&duration=Y&workers=Z
//
// Modes: primes | fibonacci | hashing | sorting | montecarlo | combined | infinite

package main

import (
	"crypto/sha256"
	"fmt"
	"log"
	"math"
	"math/rand"
	"net/http"
	"os"
	"runtime"
	"sort"
	"strconv"
	"sync"
	"sync/atomic"
	"time"
)

// ── helpers ──────────────────────────────────────────────────────────────────

func envOr(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func memUsageMB() float64 {
	var m runtime.MemStats
	runtime.ReadMemStats(&m)
	return float64(m.Alloc) / 1024 / 1024
}

// ── CPU stress functions ──────────────────────────────────────────────────────

// Sieve of Eratosthenes — integer-heavy, cache-unfriendly at large n.
func stressPrimes(limit int) int {
	sieve := make([]bool, limit+1)
	for i := range sieve {
		sieve[i] = true
	}
	sieve[0], sieve[1] = false, false
	for i := 2; i*i <= limit; i++ {
		if sieve[i] {
			for j := i * i; j <= limit; j += i {
				sieve[j] = false
			}
		}
	}
	count := 0
	for _, v := range sieve {
		if v {
			count++
		}
	}
	return count
}

// Recursive Fibonacci — exponential call tree, hammers goroutine stack.
func stressFibonacci(n int) int64 {
	if n <= 1 {
		return int64(n)
	}
	return stressFibonacci(n-1) + stressFibonacci(n-2)
}

// SHA-256 hashing loop — tests crypto throughput & memory bandwidth.
func stressHashing(iterations int) int {
	data := []byte("AzureCPUStressTestGoLang")
	for i := 0; i < iterations; i++ {
		h := sha256.New()
		h.Write(data)
		h.Write([]byte(strconv.Itoa(i)))
		h.Sum(nil)
	}
	return iterations
}

// Sort a large random slice — tests memory + compare operations.
func stressSorting(size int) float64 {
	arr := make([]int, size)
	for i := range arr {
		arr[i] = rand.Int()
	}
	sort.Ints(arr)
	if len(arr) == 0 {
		return 0
	}
	return float64(arr[0]) / float64(arr[len(arr)-1]+1)
}

// Monte Carlo π estimation — floating-point heavy, vectorisation-friendly.
func stressMonteCarlo(samples int) float64 {
	inside := 0
	for i := 0; i < samples; i++ {
		x := rand.Float64()
		y := rand.Float64()
		if x*x+y*y <= 1.0 {
			inside++
		}
	}
	return 4.0 * float64(inside) / float64(samples)
}

// ── per-iteration runner ──────────────────────────────────────────────────────

type iterResult struct {
	label  string
	result string
}

func runIteration(mode string, iter int) iterResult {
	switch mode {
	case "primes":
		r := stressPrimes(300000)
		return iterResult{"primes_found", strconv.Itoa(r)}
	case "fibonacci":
		r := stressFibonacci(36)
		return iterResult{"fib(36)", strconv.FormatInt(r, 10)}
	case "hashing":
		r := stressHashing(200000)
		return iterResult{"hashes", strconv.Itoa(r)}
	case "sorting":
		r := stressSorting(500000)
		return iterResult{"sort_result", fmt.Sprintf("%.6f", r)}
	case "montecarlo":
		r := stressMonteCarlo(5000000)
		return iterResult{"pi_approx", fmt.Sprintf("%.6f (err %.6f)", r, math.Abs(r-math.Pi))}
	case "infinite":
		r := stressPrimes(400000)
		return iterResult{"primes", strconv.Itoa(r)}
	default: // combined — rotate
		sub := iter % 5
		switch sub {
		case 0:
			r := stressPrimes(200000)
			return iterResult{"primes", strconv.Itoa(r)}
		case 1:
			r := stressFibonacci(35)
			return iterResult{"fib(35)", strconv.FormatInt(r, 10)}
		case 2:
			r := stressHashing(100000)
			return iterResult{"hashes", strconv.Itoa(r)}
		case 3:
			r := stressSorting(300000)
			return iterResult{"sort", fmt.Sprintf("%.4f", r)}
		default:
			r := stressMonteCarlo(2000000)
			return iterResult{"pi_approx", fmt.Sprintf("%.6f", r)}
		}
	}
}

// ── HTTP handlers ─────────────────────────────────────────────────────────────

func handleIndex(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}
	w.Header().Set("Content-Type", "text/html; charset=UTF-8")
	fmt.Fprintf(w, `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Go CPU Stress Test — Azure App Service Diagnostic</title>
<style>
  body{font-family:Arial,sans-serif;max-width:920px;margin:40px auto;padding:20px;background:#f4f4f4}
  h1{color:#0078d4;border-bottom:2px solid #0078d4;padding-bottom:10px}
  h2{color:#333;margin-top:30px}
  .card{background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin:15px 0;box-shadow:0 2px 4px rgba(0,0,0,.1)}
  .btn{display:inline-block;padding:10px 20px;margin:5px;border-radius:4px;text-decoration:none;font-weight:bold;cursor:pointer;border:none;font-size:14px}
  .btn-primary{background:#0078d4;color:#fff}
  .btn-warning{background:#ff8c00;color:#fff}
  .btn-danger{background:#d13438;color:#fff}
  .btn-success{background:#107c10;color:#fff}
  .btn:hover{opacity:.85}
  label{display:block;margin:8px 0 3px;font-weight:bold}
  select,input[type=number]{padding:6px 10px;border:1px solid #ccc;border-radius:4px}
  .info{background:#e6f2fb;border-left:4px solid #0078d4;padding:10px 15px;border-radius:4px;margin:10px 0;font-size:13px}
  .warn{background:#fff4ce;border-left:4px solid #ff8c00;padding:10px 15px;border-radius:4px;margin:10px 0;font-size:13px}
  .danger{background:#fde7e9;border-left:4px solid #d13438;padding:10px 15px;border-radius:4px;margin:10px 0;font-size:13px}
  table{width:100%%;border-collapse:collapse}
  th,td{text-align:left;padding:8px 12px;border-bottom:1px solid #eee}
  th{background:#f0f0f0}
  code{background:#f0f0f0;padding:2px 6px;border-radius:3px;font-family:monospace}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
  @media(max-width:600px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<h1>Go CPU Stress Test</h1>
<p>Azure App Service Diagnostic Tool — trigger high-CPU and crash conditions for monitoring &amp; Auto-Heal validation.</p>

<div class="warn"><strong>Warning:</strong> These tests intentionally spike CPU. Run only on a <strong>dedicated diagnostic/test App Service</strong>, never on production.</div>

<div class="card">
  <h2>Server Info</h2>
  <table>
    <tr><th>Property</th><th>Value</th></tr>
    <tr><td>Go Version</td><td>%s</td></tr>
    <tr><td>OS / Arch</td><td>%s / %s</td></tr>
    <tr><td>GOMAXPROCS</td><td>%d (logical CPUs available: %d)</td></tr>
    <tr><td>Current Time (UTC)</td><td>%s</td></tr>
  </table>
</div>

<div class="card">
  <h2>Quick Stress Tests</h2>
  <div class="info">Each button opens a live-streaming stress run in a new tab.</div>
  <div class="grid">
    <div>
      <strong>Low — Warm-up (5s)</strong>
      <p style="font-size:13px;color:#555">Prime sieve × 5 seconds. ~40–60%% CPU, single goroutine.</p>
      <a class="btn btn-success" href="/stress?mode=primes&duration=5" target="_blank">Run 5s Prime Sieve</a>
    </div>
    <div>
      <strong>Medium — Sustained (30s)</strong>
      <p style="font-size:13px;color:#555">Fibonacci + hashing for 30 seconds. ~80–90%% CPU.</p>
      <a class="btn btn-primary" href="/stress?mode=fibonacci&duration=30" target="_blank">Run 30s Fibonacci</a>
    </div>
    <div>
      <strong>High — Multi-core Spike (60s)</strong>
      <p style="font-size:13px;color:#555">Combined mode × all logical CPUs (GOMAXPROCS goroutines). Near 100%% across all cores.</p>
      <a class="btn btn-warning" href="/stress?mode=combined&duration=60&workers=%d" target="_blank">Run 60s Combined (%d workers)</a>
    </div>
    <div>
      <strong>Critical — Infinite Burn</strong>
      <p style="font-size:13px;color:#555">No exit condition. Runs until the process is killed or the App Service recycles.</p>
      <a class="btn btn-danger" href="/stress?mode=infinite&workers=%d" target="_blank" onclick="return confirm('This will consume 100%% CPU indefinitely. Continue?')">Run Infinite Burn (%d workers)</a>
    </div>
  </div>
</div>

<div class="card">
  <h2>Custom Stress Test</h2>
  <form action="/stress" method="GET" target="_blank">
    <div class="grid">
      <div>
        <label>Mode</label>
        <select name="mode">
          <option value="primes">Prime Number Sieve</option>
          <option value="fibonacci">Fibonacci (recursive)</option>
          <option value="hashing">SHA-256 Hashing Loop</option>
          <option value="sorting">Array Sort (large dataset)</option>
          <option value="montecarlo">Monte Carlo π</option>
          <option value="combined" selected>Combined (all above)</option>
          <option value="infinite">Infinite Burn (no stop)</option>
        </select>
      </div>
      <div>
        <label>Duration (seconds, 0 = infinite)</label>
        <input type="number" name="duration" value="30" min="0" max="600">
      </div>
      <div>
        <label>Parallel Workers (goroutines)</label>
        <input type="number" name="workers" value="%d" min="1" max="64">
        <small style="color:#888">Each worker runs on its own goroutine. Set to GOMAXPROCS (%d) to saturate all cores.</small>
      </div>
    </div>
    <br>
    <button type="submit" class="btn btn-primary">Run Custom Test</button>
  </form>
</div>

<div class="card">
  <h2>Parallel Tab Attack</h2>
  <div class="info">Opens N browser tabs simultaneously — simulates concurrent HTTP requests hitting the App Service.</div>
  <div class="danger"><strong>Tip:</strong> Combine with <code>hey</code> or <code>ab</code>:<br>
  <code>hey -n 500 -c 20 "https://&lt;app&gt;.azurewebsites.net/stress?mode=combined&duration=60&workers=%d"</code></div>
  <label>Tabs to open</label>
  <input type="number" id="tabCount" value="4" min="1" max="20">
  <br><br>
  <button class="btn btn-warning" onclick="openTabs()">Open Parallel Stress Tabs</button>
  <script>
  function openTabs(){
    var n=parseInt(document.getElementById('tabCount').value,10);
    for(var i=0;i<n;i++) window.open('/stress?mode=combined&duration=60&workers=%d&tab='+i,'_blank');
  }
  </script>
</div>

<div class="card">
  <h2>Azure Monitoring — What to Watch</h2>
  <table>
    <tr><th>Metric / Tool</th><th>Where</th></tr>
    <tr><td>CPU Percentage</td><td>App Service → Metrics → CPU Percentage</td></tr>
    <tr><td>HTTP Queue Length</td><td>App Service Plan → Metrics → Http Queue Length</td></tr>
    <tr><td>Process Explorer (live PIDs)</td><td><code>https://&lt;app&gt;.scm.azurewebsites.net/ProcessExplorer/</code></td></tr>
    <tr><td>Memory Dump on High CPU</td><td>Diagnose &amp; Solve Problems → High CPU Analysis</td></tr>
    <tr><td>Auto-Heal trigger</td><td>App Service → Configuration → General → Auto Heal</td></tr>
    <tr><td>Live Metrics Stream</td><td>Application Insights → Live Metrics</td></tr>
  </table>
</div>
</body>
</html>`,
		runtime.Version(),
		runtime.GOOS, runtime.GOARCH,
		runtime.GOMAXPROCS(0), runtime.NumCPU(),
		time.Now().UTC().Format("2006-01-02 15:04:05"),
		// quick test buttons (workers = num CPUs)
		runtime.NumCPU(), runtime.NumCPU(),
		runtime.NumCPU(), runtime.NumCPU(),
		// custom form defaults
		runtime.NumCPU(), runtime.NumCPU(),
		// parallel tab tip
		runtime.NumCPU(),
		runtime.NumCPU(),
	)
}

func handleStress(w http.ResponseWriter, r *http.Request) {
	q := r.URL.Query()

	mode := q.Get("mode")
	validModes := map[string]bool{
		"primes": true, "fibonacci": true, "hashing": true,
		"sorting": true, "montecarlo": true, "combined": true, "infinite": true,
	}
	if !validModes[mode] {
		mode = "combined"
	}

	duration := 30
	if d, err := strconv.Atoi(q.Get("duration")); err == nil && d >= 0 {
		duration = d
	}

	workers := 1
	if ww, err := strconv.Atoi(q.Get("workers")); err == nil && ww >= 1 && ww <= 64 {
		workers = ww
	}

	infinite := mode == "infinite" || duration == 0

	// ── Stream HTML response ──
	w.Header().Set("Content-Type", "text/html; charset=UTF-8")
	w.Header().Set("X-Accel-Buffering", "no")
	w.Header().Set("Cache-Control", "no-store")

	flusher, canFlush := w.(http.Flusher)

	emit := func(s string) {
		fmt.Fprint(w, s)
		if canFlush {
			flusher.Flush()
		}
	}

	durationLabel := fmt.Sprintf("%ds", duration)
	if infinite {
		durationLabel = "∞ (infinite)"
	}

	emit(fmt.Sprintf(`<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Go CPU Stress — %s</title>
<style>
  body{font-family:monospace;background:#111;color:#0f0;padding:20px;font-size:13px}
  h2{color:#0af} .red{color:#f44} .yellow{color:#ff0} .green{color:#0f0} .gray{color:#888}
  span.label{color:#888;display:inline-block;width:170px}
  .bar{display:inline-block;background:#0f0;height:10px;vertical-align:middle}
  pre{white-space:pre-wrap;word-break:break-all}
</style></head><body>
<h2>Go CPU Stress Engine — Mode: <span style="color:#ff0">%s</span></h2>
<p><span class="label">Duration target:</span> %s</p>
<p><span class="label">Workers (goroutines):</span> %d</p>
<p><span class="label">Go version:</span> %s</p>
<p><span class="label">GOMAXPROCS:</span> %d</p>
<hr><pre>`,
		mode, mode, durationLabel, workers, runtime.Version(), runtime.GOMAXPROCS(0)))

	start := time.Now()
	deadline := start.Add(time.Duration(duration) * time.Second)

	var totalIter atomic.Int64
	var wg sync.WaitGroup
	stopCh := make(chan struct{})

	// ── Worker goroutines ──────────────────────────────────────────────────
	for w := 0; w < workers; w++ {
		wg.Add(1)
		go func(workerID int) {
			defer wg.Done()
			localIter := 0
			for {
				select {
				case <-stopCh:
					return
				default:
				}
				if !infinite && time.Now().After(deadline) {
					return
				}
				runIteration(mode, localIter)
				localIter++
				totalIter.Add(1)
			}
		}(w)
	}

	// ── Progress reporter (main goroutine) ────────────────────────────────
	ticker := time.NewTicker(500 * time.Millisecond)
	defer ticker.Stop()

	reportLoop:
	for {
		select {
		case <-ticker.C:
			elapsed := time.Since(start)
			iter := totalIter.Load()
			mem := memUsageMB()

			var pctStr, colorClass string
			var barW int
			if infinite {
				pctStr = "∞"
				colorClass = "red"
				barW = 80
			} else {
				frac := elapsed.Seconds() / float64(duration)
				pctStr = fmt.Sprintf("%.0f%%", frac*100)
				barW = int(frac * 80)
				if barW > 80 {
					barW = 80
				}
				switch {
				case frac > 0.75:
					colorClass = "red"
				case frac > 0.4:
					colorClass = "yellow"
				default:
					colorClass = "green"
				}
			}

			emit(fmt.Sprintf("[%s] total_iter=%-6d workers=%-2d elapsed=%-8s mem=%.1fMB progress=<span class=\"bar\" style=\"width:%dpx\"></span><span class=\"%s\"> %s</span>\n",
				time.Now().UTC().Format("15:04:05"),
				iter,
				workers,
				elapsed.Round(time.Millisecond).String(),
				mem,
				barW,
				colorClass,
				pctStr,
			))

			if !infinite && time.Now().After(deadline) {
				break reportLoop
			}
		}
	}

	close(stopCh)
	wg.Wait()

	totalElapsed := time.Since(start).Round(time.Millisecond)
	var peakMem runtime.MemStats
	runtime.ReadMemStats(&peakMem)

	emit(fmt.Sprintf(`</pre><hr>
<h2 class="green">Stress Test Completed</h2>
<p><span class="label">Total elapsed:</span> %s</p>
<p><span class="label">Total iterations:</span> %d</p>
<p><span class="label">Heap alloc (peak):</span> %.2f MB</p>
<p><a href="/" style="color:#0af">&#8592; Back to Dashboard</a></p>
</body></html>`,
		totalElapsed,
		totalIter.Load(),
		float64(peakMem.TotalAlloc)/1024/1024,
	))
}

// ── main ──────────────────────────────────────────────────────────────────────

func main() {
	// Azure App Service injects PORT env var; default to 8080 locally.
	port := envOr("PORT", "8080")

	// Use all available logical CPUs.
	runtime.GOMAXPROCS(runtime.NumCPU())

	mux := http.NewServeMux()
	mux.HandleFunc("/", handleIndex)
	mux.HandleFunc("/stress", handleStress)

	addr := ":" + port
	log.Printf("Go CPU Stress server starting on %s (GOMAXPROCS=%d, NumCPU=%d)",
		addr, runtime.GOMAXPROCS(0), runtime.NumCPU())

	srv := &http.Server{
		Addr:    addr,
		Handler: mux,
		// No read/write timeouts — stress runs need to stream for minutes.
		ReadHeaderTimeout: 10 * time.Second,
	}

	if err := srv.ListenAndServe(); err != nil {
		log.Fatalf("server error: %v", err)
	}
}
