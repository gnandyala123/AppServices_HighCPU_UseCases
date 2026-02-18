import { useState, useRef } from 'react'
import './App.css'

function App() {
  const [isRunning, setIsRunning] = useState(false)
  const [duration, setDuration] = useState(10)
  const [intensity, setIntensity] = useState(4)
  const [progress, setProgress] = useState(0)
  const workersRef = useRef([])

  // CPU-intensive calculation (runs in main thread)
  const heavyCalculation = () => {
    let result = 0
    for (let i = 0; i < 10000000; i++) {
      result += Math.sqrt(i) * Math.sin(i) * Math.cos(i)
    }
    return result
  }

  // Start CPU stress test
  const startStressTest = () => {
    setIsRunning(true)
    setProgress(0)

    const startTime = Date.now()
    const endTime = startTime + duration * 1000

    // Create multiple worker threads for higher CPU usage
    for (let i = 0; i < intensity; i++) {
      const worker = new Worker(
        URL.createObjectURL(
          new Blob([`
            self.onmessage = function(e) {
              const endTime = e.data.endTime;
              while (Date.now() < endTime) {
                let result = 0;
                for (let i = 0; i < 1000000; i++) {
                  result += Math.sqrt(i) * Math.sin(i) * Math.cos(i);
                }
              }
              self.postMessage('done');
            };
          `], { type: 'application/javascript' })
        )
      )
      worker.postMessage({ endTime })
      worker.onmessage = () => {
        worker.terminate()
      }
      workersRef.current.push(worker)
    }

    // Update progress
    const progressInterval = setInterval(() => {
      const elapsed = Date.now() - startTime
      const newProgress = Math.min((elapsed / (duration * 1000)) * 100, 100)
      setProgress(newProgress)

      if (Date.now() >= endTime) {
        clearInterval(progressInterval)
        setIsRunning(false)
        setProgress(100)
        workersRef.current = []
      }
    }, 100)
  }

  // Stop the stress test
  const stopStressTest = () => {
    workersRef.current.forEach(worker => worker.terminate())
    workersRef.current = []
    setIsRunning(false)
    setProgress(0)
  }

  // Server-side CPU stress (calls backend)
  const triggerServerCPU = async () => {
    try {
      const response = await fetch(`/api/cpu-stress?duration=${duration}&intensity=${intensity}`)
      const data = await response.json()
      alert(`Server CPU stress completed: ${data.message}`)
    } catch (error) {
      alert(`Error: ${error.message}`)
    }
  }

  return (
    <div className="container">
      <h1>CPU Stress Test App</h1>
      <p className="subtitle">For Azure App Service Testing</p>

      <div className="card">
        <h2>Configuration</h2>

        <div className="input-group">
          <label>Duration (seconds):</label>
          <input
            type="number"
            value={duration}
            onChange={(e) => setDuration(Number(e.target.value))}
            min="1"
            max="300"
            disabled={isRunning}
          />
        </div>

        <div className="input-group">
          <label>Intensity (workers):</label>
          <input
            type="number"
            value={intensity}
            onChange={(e) => setIntensity(Number(e.target.value))}
            min="1"
            max="16"
            disabled={isRunning}
          />
        </div>
      </div>

      <div className="card">
        <h2>Client-Side CPU Stress</h2>
        <p>Runs heavy calculations in browser (Web Workers)</p>

        {isRunning && (
          <div className="progress-container">
            <div className="progress-bar" style={{ width: `${progress}%` }}></div>
            <span className="progress-text">{Math.round(progress)}%</span>
          </div>
        )}

        <div className="button-group">
          {!isRunning ? (
            <button className="btn btn-start" onClick={startStressTest}>
              Start Client CPU Stress
            </button>
          ) : (
            <button className="btn btn-stop" onClick={stopStressTest}>
              Stop
            </button>
          )}
        </div>
      </div>

      <div className="card">
        <h2>Server-Side CPU Stress</h2>
        <p>Triggers CPU load on Azure App Service backend</p>

        <button
          className="btn btn-server"
          onClick={triggerServerCPU}
          disabled={isRunning}
        >
          Trigger Server CPU Stress
        </button>
      </div>

      <div className="card info">
        <h3>How to Use</h3>
        <ul>
          <li><strong>Client-Side:</strong> Stresses your browser's CPU</li>
          <li><strong>Server-Side:</strong> Stresses Azure App Service CPU</li>
          <li>Monitor CPU in Azure Portal → Metrics → CPU Percentage</li>
        </ul>
      </div>
    </div>
  )
}

export default App
