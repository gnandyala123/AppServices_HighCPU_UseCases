import math
import os
import time
import random
import hashlib
import multiprocessing
from flask import Flask, render_template_string, jsonify, request

app = Flask(__name__)

# ---------- shared state ----------
cpu_burn_processes = []

HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPU Chaos Lab</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f0c29;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: #e0e0e0;
            min-height: 100vh;
        }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        h1 {
            text-align: center;
            font-size: 2.8rem;
            margin-bottom: 10px;
            background: linear-gradient(90deg, #f857a6, #ff5858, #ffc837);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 40px;
            font-size: 1.1rem;
        }
        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
        .card h2 { margin-bottom: 10px; }
        .card p { color: #aaa; margin-bottom: 18px; line-height: 1.5; }
        .severity { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px; }
        .low { background: #2d6a4f; color: #b7e4c7; }
        .medium { background: #b45309; color: #fde68a; }
        .high { background: #991b1b; color: #fca5a5; }
        .extreme { background: #7f1d1d; color: #ff6b6b; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }
        button {
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            color: #fff;
        }
        button:hover { transform: scale(1.05); filter: brightness(1.15); }
        .btn-green  { background: linear-gradient(135deg, #2d6a4f, #40916c); }
        .btn-orange { background: linear-gradient(135deg, #b45309, #d97706); }
        .btn-red    { background: linear-gradient(135deg, #991b1b, #dc2626); }
        .btn-fire   { background: linear-gradient(135deg, #7f1d1d, #ef4444); }
        .btn-stop   { background: linear-gradient(135deg, #374151, #6b7280); }
        .result {
            margin-top: 16px;
            padding: 14px;
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
            font-family: 'Cascadia Code', 'Fira Code', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            display: none;
            border-left: 3px solid #f857a6;
        }
        .fire { font-size: 1.6rem; }
        .footer { text-align: center; margin-top: 40px; color: #555; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>CPU Chaos Lab</h1>
        <p class="subtitle">A playground for unleashing CPU mayhem on Azure App Services</p>

        <!-- 1. Light -->
        <div class="card">
            <h2>Calculate Pi Digits <span class="severity low">Low</span></h2>
            <p>Computes Pi using the Leibniz series. A gentle warm-up for your CPU.</p>
            <button class="btn-green" onclick="fire('/pi?iterations=5000000', this)">Run (5M iterations)</button>
            <div class="result" id="res-pi"></div>
        </div>

        <!-- 2. Medium -->
        <div class="card">
            <h2>Prime Number Crunch <span class="severity medium">Medium</span></h2>
            <p>Finds all prime numbers up to N using trial division. No shortcuts allowed!</p>
            <button class="btn-orange" onclick="fire('/primes?limit=200000', this)">Find Primes up to 200K</button>
            <div class="result" id="res-primes"></div>
        </div>

        <!-- 3. High -->
        <div class="card">
            <h2>Hash Avalanche <span class="severity high">High</span></h2>
            <p>Performs millions of SHA-256 hash computations in a chain. Each hash feeds the next.</p>
            <button class="btn-red" onclick="fire('/hash-storm?rounds=3000000', this)">Hash Storm (3M rounds)</button>
            <div class="result" id="res-hash"></div>
        </div>

        <!-- 4. Extreme -->
        <div class="card">
            <h2><span class="fire">&#x1F525;</span> Infinite Burn <span class="severity extreme">Extreme</span></h2>
            <p>Spawns real OS processes that burn CPU continuously until you stop them. Each process pins one CPU core at 100%. This WILL light up Task Manager!</p>
            <button class="btn-fire" onclick="fire('/burn-start?workers=4', this)">Start Burn (4 processes)</button>
            <button class="btn-stop" onclick="fire('/burn-stop', this)" style="margin-left:10px;">Stop Burn</button>
            <div class="result" id="res-burn"></div>
        </div>

        <!-- 5. Fibonacci -->
        <div class="card">
            <h2>Recursive Fibonacci <span class="severity high">High</span></h2>
            <p>Computes Fibonacci the worst way possible - pure recursion with no memoization. Exponential time complexity!</p>
            <button class="btn-red" onclick="fire('/fibonacci?n=38', this)">Fibonacci(38)</button>
            <div class="result" id="res-fib"></div>
        </div>

        <!-- 6. Matrix -->
        <div class="card">
            <h2>Matrix Multiplication <span class="severity medium">Medium</span></h2>
            <p>Multiplies two random NxN matrices using pure Python nested loops. No NumPy cheating!</p>
            <button class="btn-orange" onclick="fire('/matrix?size=250', this)">250x250 Matrix Multiply</button>
            <div class="result" id="res-matrix"></div>
        </div>

        <div class="footer">
            CPU Chaos Lab &mdash; Built for Azure App Services troubleshooting practice
        </div>
    </div>

    <script>
        async function fire(url, btn) {
            const card = btn.closest('.card');
            const result = card.querySelector('.result');
            result.style.display = 'block';
            result.textContent = 'Working... your CPU is crying right now...';
            btn.disabled = true;
            try {
                const resp = await fetch(url);
                const data = await resp.json();
                result.textContent = JSON.stringify(data, null, 2);
            } catch (e) {
                result.textContent = 'Error: ' + e.message;
            }
            btn.disabled = false;
        }
    </script>
</body>
</html>
"""


@app.route("/")
def index():
    return render_template_string(HTML_TEMPLATE)


# ---------- 1. Pi calculation (Leibniz series) ----------
@app.route("/pi")
def calculate_pi():
    iterations = int(request.args.get("iterations", 1_000_000))
    start = time.time()
    pi = 0.0
    for i in range(iterations):
        pi += ((-1) ** i) / (2 * i + 1)
    pi *= 4
    elapsed = round(time.time() - start, 3)
    return jsonify(pi=pi, iterations=iterations, elapsed_seconds=elapsed)


# ---------- 2. Prime number finder ----------
@app.route("/primes")
def find_primes():
    limit = int(request.args.get("limit", 100_000))
    start = time.time()
    primes = []
    for num in range(2, limit + 1):
        is_prime = True
        for d in range(2, int(math.sqrt(num)) + 1):
            if num % d == 0:
                is_prime = False
                break
        if is_prime:
            primes.append(num)
    elapsed = round(time.time() - start, 3)
    return jsonify(
        count=len(primes),
        largest=primes[-1] if primes else None,
        first_10=primes[:10],
        last_10=primes[-10:],
        elapsed_seconds=elapsed,
    )


# ---------- 3. Hash storm ----------
@app.route("/hash-storm")
def hash_storm():
    rounds = int(request.args.get("rounds", 1_000_000))
    start = time.time()
    data = b"cpu-chaos-lab-seed"
    for _ in range(rounds):
        data = hashlib.sha256(data).digest()
    elapsed = round(time.time() - start, 3)
    return jsonify(
        rounds=rounds,
        final_hash=hashlib.sha256(data).hexdigest(),
        elapsed_seconds=elapsed,
    )


# ---------- 4. Infinite burn (background processes) ----------
def _burn_cpu():
    """Burn CPU in a tight infinite loop — runs as a separate OS process."""
    while True:
        for _ in range(1000):
            math.factorial(5000)


@app.route("/burn-start")
def burn_start():
    global cpu_burn_processes
    if cpu_burn_processes:
        alive = [p for p in cpu_burn_processes if p.is_alive()]
        if alive:
            return jsonify(status="already burning", process_count=len(alive))
    num_workers = int(request.args.get("workers", 4))
    cpu_burn_processes = []
    for _ in range(num_workers):
        p = multiprocessing.Process(target=_burn_cpu, daemon=True)
        p.start()
        cpu_burn_processes.append(p)
    return jsonify(
        status="burn started",
        processes=num_workers,
        pids=[p.pid for p in cpu_burn_processes],
        message="CPU is now on fire! Each process burns one core. Hit /burn-stop to extinguish.",
    )


@app.route("/burn-stop")
def burn_stop():
    global cpu_burn_processes
    if not cpu_burn_processes:
        return jsonify(status="nothing to stop", message="No active burn running.")
    count = 0
    for p in cpu_burn_processes:
        if p.is_alive():
            p.terminate()
            p.join(timeout=3)
            count += 1
    cpu_burn_processes = []
    return jsonify(status="burn stopped", processes_stopped=count, message="Phew! CPU can breathe again.")


# ---------- 5. Recursive Fibonacci ----------
def _fib(n):
    if n <= 1:
        return n
    return _fib(n - 1) + _fib(n - 2)


@app.route("/fibonacci")
def fibonacci():
    n = int(request.args.get("n", 35))
    n = min(n, 42)  # cap to avoid truly endless waits
    start = time.time()
    result = _fib(n)
    elapsed = round(time.time() - start, 3)
    return jsonify(n=n, result=result, elapsed_seconds=elapsed)


# ---------- 6. Matrix multiplication ----------
@app.route("/matrix")
def matrix_multiply():
    size = int(request.args.get("size", 200))
    size = min(size, 500)
    start = time.time()
    A = [[random.random() for _ in range(size)] for _ in range(size)]
    B = [[random.random() for _ in range(size)] for _ in range(size)]
    C = [[0.0] * size for _ in range(size)]
    for i in range(size):
        for j in range(size):
            s = 0.0
            for k in range(size):
                s += A[i][k] * B[k][j]
            C[i][j] = s
    elapsed = round(time.time() - start, 3)
    return jsonify(
        size=f"{size}x{size}",
        sample_value=round(C[0][0], 6),
        elapsed_seconds=elapsed,
    )


# ---------- Health check ----------
@app.route("/health")
def health():
    return jsonify(status="healthy", app="CPU Chaos Lab")


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8000)
