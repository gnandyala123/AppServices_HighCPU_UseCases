<?php
/**
 * CPU Stress Engine
 * Azure Web App Diagnostic Tool
 *
 * Query params:
 *   mode      = primes | fibonacci | hashing | sorting | regex | combined | infinite
 *   duration  = seconds to run (default 30, ignored for 'infinite')
 *   no_limit  = 1 to call set_time_limit(0)
 *   workers   = number of child processes to fork (requires proc_open)
 *   worker    = internal worker id (used when forking)
 */

declare(strict_types=1);

// ── Configuration ────────────────────────────────────────────────────────────
$mode     = $_GET['mode']     ?? 'combined';
$duration = max(1, (int)($_GET['duration'] ?? 30));
$noLimit  = ($_GET['no_limit'] ?? '0') === '1';
$workers  = max(1, min(16, (int)($_GET['workers'] ?? 1)));

$allowedModes = ['primes', 'fibonacci', 'hashing', 'sorting', 'regex', 'combined', 'infinite'];
if (!in_array($mode, $allowedModes, true)) {
    $mode = 'combined';
}

if ($noLimit || $mode === 'infinite') {
    set_time_limit(0);
} else {
    // Add 10s grace over requested duration so PHP doesn't die prematurely
    set_time_limit($duration + 10);
}

// ── Output buffering — stream results to browser ──────────────────────────
if (ob_get_level()) ob_end_clean();
header('Content-Type: text/html; charset=UTF-8');
header('X-Accel-Buffering: no');   // nginx: disable proxy buffering
header('Cache-Control: no-store');

// ── Helpers ──────────────────────────────────────────────────────────────────

function elapsed(float $start): float
{
    return round(microtime(true) - $start, 2);
}

function memUsageMB(): string
{
    return round(memory_get_usage(true) / 1024 / 1024, 1) . ' MB';
}

function flush_output(string $line): void
{
    echo $line . "\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// ── Stress functions ─────────────────────────────────────────────────────────

/**
 * Prime sieve up to $limit — CPU-bound integer work.
 */
function stress_primes(int $limit = 200000): int
{
    $sieve = array_fill(2, $limit - 1, true);
    for ($i = 2; $i * $i <= $limit; $i++) {
        if ($sieve[$i]) {
            for ($j = $i * $i; $j <= $limit; $j += $i) {
                $sieve[$j] = false;
            }
        }
    }
    return count(array_filter($sieve));
}

/**
 * Recursive Fibonacci — exponential time, hammers the call stack & CPU.
 */
function stress_fibonacci(int $n = 35): int
{
    if ($n <= 1) return $n;
    return stress_fibonacci($n - 1) + stress_fibonacci($n - 2);
}

/**
 * SHA-256 hashing loop — tests crypto-speed and memory bandwidth.
 */
function stress_hashing(int $iterations = 100000): int
{
    $data = str_repeat('AzureCPUStressTest', 64);
    for ($i = 0; $i < $iterations; $i++) {
        hash('sha256', $data . $i);
    }
    return $iterations;
}

/**
 * Sort a large random integer array — tests memory + compare ops.
 */
function stress_sorting(int $size = 500000): float
{
    $arr = [];
    for ($i = 0; $i < $size; $i++) {
        $arr[] = mt_rand();
    }
    sort($arr);
    return $arr[0] / ($arr[$size - 1] ?: 1);  // prevent dead-code elimination
}

/**
 * Catastrophic regex backtracking — locks a PHP thread on regex engine.
 * Classic ReDoS pattern: (a+)+ against a string that doesn't match.
 */
function stress_regex(int $length = 28): bool
{
    $str = str_repeat('a', $length) . 'b';
    return (bool) preg_match('/^(a+)+$/', $str);
}

// ── Worker spawn (parallel processes) ────────────────────────────────────────

function spawn_workers(int $count, string $mode, int $duration, bool $noLimit): void
{
    if (!function_exists('proc_open')) {
        flush_output('<p class="warn">proc_open is disabled — cannot fork workers. Running single-threaded.</p>');
        return;
    }

    $php  = PHP_BINARY;
    $self = __FILE__;
    $procs = [];

    for ($i = 1; $i < $count; $i++) {
        $cmd = sprintf(
            '%s %s mode=%s duration=%d no_limit=%d worker=%d',
            escapeshellcmd($php),
            escapeshellarg($self),
            escapeshellarg($mode),
            $duration,
            $noLimit ? 1 : 0,
            $i
        );
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $procs[] = ['proc' => $proc, 'stdout' => $pipes[1]];
        }
    }

    // Parent does its own work, then waits for children
    flush_output("<p>Spawned <strong>" . count($procs) . "</strong> worker processes.</p>");
    return; // parent continues to run stress below
}

// ── CLI mode (when forked as child process) ───────────────────────────────
$isCLI = (PHP_SAPI === 'cli');
if ($isCLI) {
    // Parse args like key=value
    foreach (array_slice($argv ?? [], 1) as $arg) {
        [$k, $v] = array_pad(explode('=', $arg, 2), 2, '');
        $_GET[$k] = $v;
    }
    $mode     = $_GET['mode']     ?? 'combined';
    $duration = max(1, (int)($_GET['duration'] ?? 30));
    $noLimit  = ($_GET['no_limit'] ?? '0') === '1';
    if ($noLimit || $mode === 'infinite') set_time_limit(0);
}

// ── Main execution ───────────────────────────────────────────────────────────

$startTime = microtime(true);
$iteration = 0;

if (!$isCLI) {
    // Spawn additional workers BEFORE outputting the HTML header
    if ($workers > 1) {
        spawn_workers($workers, $mode, $duration, $noLimit);
    }

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<title>CPU Stress — ' . htmlspecialchars($mode) . '</title>';
    echo '<style>
        body{font-family:monospace;background:#111;color:#0f0;padding:20px;font-size:13px;}
        .bar{display:inline-block;background:#0f0;height:10px;vertical-align:middle;}
        .red{color:#f44;} .yellow{color:#ff0;} .green{color:#0f0;} .gray{color:#888;}
        h2{color:#0af;} span.label{color:#888;display:inline-block;width:160px;}
        pre{white-space:pre-wrap;word-break:break-all;}
    </style></head><body>';
    echo '<h2>CPU Stress Engine — Mode: <span style="color:#ff0">' . htmlspecialchars(strtoupper($mode)) . '</span></h2>';
    echo '<p><span class="label">Duration target:</span> ' . ($mode === 'infinite' ? '&infin; (infinite)' : $duration . 's') . '</p>';
    echo '<p><span class="label">Workers:</span> ' . $workers . '</p>';
    echo '<p><span class="label">PHP Version:</span> ' . PHP_VERSION . '</p>';
    echo '<p><span class="label">Time limit:</span> ' . ($noLimit || $mode === 'infinite' ? 'disabled' : $duration + 10 . 's') . '</p>';
    echo '<hr><pre>';
    if (ob_get_level()) ob_flush();
    flush();
}

// ── Run loop ─────────────────────────────────────────────────────────────────

$running = true;

while ($running) {
    $now = microtime(true);
    $age = $now - $startTime;

    // Check stop condition
    if ($mode !== 'infinite' && $age >= $duration) {
        break;
    }

    $result = '';
    $label  = '';

    switch ($mode) {
        case 'primes':
            $r      = stress_primes(200000);
            $label  = 'primes_found';
            $result = $r;
            break;

        case 'fibonacci':
            $r      = stress_fibonacci(36);
            $label  = 'fib(36)';
            $result = $r;
            break;

        case 'hashing':
            $r      = stress_hashing(150000);
            $label  = 'hashes';
            $result = $r;
            break;

        case 'sorting':
            $r      = stress_sorting(300000);
            $label  = 'sort_result';
            $result = number_format($r, 6);
            break;

        case 'regex':
            $r      = stress_regex(28);
            $label  = 'regex_match';
            $result = $r ? 'true' : 'false';
            break;

        case 'combined':
        default:
            // Rotate through all methods for maximum CPU diversity
            $sub = $iteration % 5;
            switch ($sub) {
                case 0: $r = stress_primes(150000);   $label = 'primes';   $result = $r;  break;
                case 1: $r = stress_fibonacci(34);     $label = 'fib(34)';  $result = $r;  break;
                case 2: $r = stress_hashing(80000);    $label = 'hashes';   $result = $r;  break;
                case 3: $r = stress_sorting(200000);   $label = 'sort';     $result = number_format($r, 4); break;
                case 4: $r = stress_regex(26);         $label = 'regex';    $result = $r ? 'match' : 'no-match'; break;
            }
            break;

        case 'infinite':
            // Pure busy-loop — no sleep, no break, no mercy
            $r      = stress_primes(250000);
            $label  = 'primes';
            $result = $r;
            break;
    }

    $iteration++;
    $elapsed = elapsed($startTime);
    $mem     = memUsageMB();

    if (!$isCLI) {
        $pct = ($mode === 'infinite') ? '&infin;' : round(($age / $duration) * 100) . '%';
        $barW = ($mode === 'infinite') ? 80 : min(80, (int)(($age / $duration) * 80));
        $color = ($age / max(1, $duration)) > 0.75 ? 'red' : (($age / max(1, $duration)) > 0.4 ? 'yellow' : 'green');
        echo sprintf(
            "[%s] iter=%-4d %-12s %-12s elapsed=%-6ss mem=%-8s progress=<span class=\"bar\" style=\"width:%dpx\"></span>%s\n",
            gmdate('H:i:s'),
            $iteration,
            $label . '=' . $result,
            '',
            $elapsed,
            $mem,
            $barW,
            '<span class="' . $color . '"> ' . $pct . '</span>'
        );
        if (ob_get_level()) ob_flush();
        flush();
    } else {
        echo sprintf("[%s] iter=%d %s=%s elapsed=%ss mem=%s\n",
            gmdate('H:i:s'), $iteration, $label, $result, $elapsed, $mem);
    }
}

// ── Summary ──────────────────────────────────────────────────────────────────

$totalElapsed = elapsed($startTime);
$peakMem      = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';

if (!$isCLI) {
    echo '</pre><hr>';
    echo '<h2 class="green">Stress Test Completed</h2>';
    echo '<p><span class="label">Total elapsed:</span> ' . $totalElapsed . 's</p>';
    echo '<p><span class="label">Total iterations:</span> ' . $iteration . '</p>';
    echo '<p><span class="label">Peak memory:</span> ' . $peakMem . '</p>';
    echo '<p><a href="index.php" style="color:#0af">&#8592; Back to Dashboard</a></p>';
    echo '</body></html>';
} else {
    echo "--- Done | elapsed={$totalElapsed}s iterations={$iteration} peakMem={$peakMem}\n";
}
