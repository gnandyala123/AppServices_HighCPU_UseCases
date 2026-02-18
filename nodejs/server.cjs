const express = require('express');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

// Serve static files from the dist directory
app.use(express.static(path.join(__dirname, 'dist')));

// API endpoint to trigger server-side CPU stress
app.get('/api/cpu-stress', (req, res) => {
  const duration = Math.min(parseInt(req.query.duration) || 10, 120); // Max 120 seconds
  const intensity = Math.min(parseInt(req.query.intensity) || 4, 8);  // Max 8 workers

  console.log(`Starting CPU stress test: ${duration}s, intensity: ${intensity}`);

  const startTime = Date.now();
  const endTime = startTime + duration * 1000;

  // CPU-intensive calculation
  const heavyWork = () => {
    let result = 0;
    for (let i = 0; i < 10000000; i++) {
      result += Math.sqrt(i) * Math.sin(i) * Math.cos(i);
    }
    return result;
  };

  // Run intensive calculations until duration is reached
  const runStress = () => {
    return new Promise((resolve) => {
      const interval = setInterval(() => {
        // Run multiple calculations per interval for higher CPU usage
        for (let i = 0; i < intensity; i++) {
          heavyWork();
        }

        if (Date.now() >= endTime) {
          clearInterval(interval);
          resolve();
        }
      }, 10);
    });
  };

  runStress()
    .then(() => {
      const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
      console.log(`CPU stress test completed in ${elapsed}s`);
      res.json({
        success: true,
        message: `CPU stress test completed in ${elapsed} seconds`,
        duration: elapsed,
        intensity: intensity
      });
    })
    .catch((error) => {
      res.status(500).json({
        success: false,
        message: error.message
      });
    });
});

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({ status: 'healthy', timestamp: new Date().toISOString() });
});

// Handle SPA routing - serve index.html for all other routes
app.get('/{*path}', (req, res) => {
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
  console.log(`Health check: http://localhost:${PORT}/api/health`);
});
