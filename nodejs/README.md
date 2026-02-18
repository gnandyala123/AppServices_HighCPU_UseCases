# CPU Stress Test App

A simple CPU stress testing tool built with React and Express, designed for testing and monitoring Azure App Service performance.

## Features

- **Client-Side Stress Testing**: Uses Web Workers to stress your browser's CPU
- **Server-Side Stress Testing**: Triggers CPU load on the Node.js backend (useful for Azure App Service monitoring)
- **Configurable Parameters**: Adjust duration (1-300 seconds) and intensity (1-16 workers)
- **Real-Time Progress**: Visual progress bar during client-side stress tests
- **Health Check Endpoint**: Built-in `/api/health` endpoint for monitoring

## Requirements

- Node.js >= 18.0.0

## Installation

```bash
npm install
```

## Usage

### Development Mode

Run the Vite development server with hot module replacement:

```bash
npm run dev
```

### Production Mode

1. Build the frontend:

```bash
npm run build
```

2. Start the Express server:

```bash
npm start
```

The app will be available at `http://localhost:3000` (or the port specified in the `PORT` environment variable).

## API Endpoints

### CPU Stress

```
GET /api/cpu-stress?duration=10&intensity=4
```

| Parameter | Description | Default | Max |
|-----------|-------------|---------|-----|
| duration | Test duration in seconds | 10 | 120 |
| intensity | Number of calculation cycles | 4 | 8 |

**Response:**

```json
{
  "success": true,
  "message": "CPU stress test completed in 10.00 seconds",
  "duration": "10.00",
  "intensity": 4
}
```

### Health Check

```
GET /api/health
```

**Response:**

```json
{
  "status": "healthy",
  "timestamp": "2024-01-01T00:00:00.000Z"
}
```

## Azure App Service Deployment

This app is designed for testing Azure App Service CPU metrics:

1. Deploy the app to Azure App Service
2. Use the server-side stress test to generate CPU load
3. Monitor CPU usage in Azure Portal: **Metrics** > **CPU Percentage**

## Scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Start Vite dev server |
| `npm run build` | Build for production |
| `npm run preview` | Preview production build |
| `npm start` | Start Express production server |
| `npm run lint` | Run ESLint |

## Tech Stack

- **Frontend**: React 19, Vite 7
- **Backend**: Express 5
- **Build Tool**: Vite

## License

Private
