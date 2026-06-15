# Docker Deployment Guide

## Option 1: Standalone (Simple)

Run the traider dashboard in its own container:

```bash
cd /home/hassan/projects/personal/traider
docker-compose up -d
```

Access at: `http://localhost:5001`

Stop:
```bash
docker-compose down
```

---

## Option 2: Integrated with localWebHosting (Recommended)

**Step 1:** Move traider to localWebHosting dockers directory:
```bash
mv /home/hassan/projects/personal/traider \
   /home/hassan/projects/personal/localWebHosting/dockers/traider
```

**Step 2:** Add this to `/home/hassan/projects/personal/localWebHosting/dockers/docker-compose.yml`:

```yaml
  traider-dashboard:
    build:
      context: ./traider
      dockerfile: Dockerfile
    container_name: wp-traider-dashboard
    restart: unless-stopped
    networks:
      - wpnet
    environment:
      FLASK_ENV: production
      PYTHONUNBUFFERED: 1
    volumes:
      - ./traider/logs:/app/logs
      - ./traider/config.yaml:/app/config.yaml
    ports:
      - "127.0.0.1:5001:5000"
```

**Step 3:** If you want Cloudflare tunnel access, update cloudflared config:

```yaml
  cloudflared:
    # ... existing config ...
    depends_on:
      - ayimi
      - physicalme
      - learnarm-api
      - traider-dashboard  # Add this
```

**Step 4:** Rebuild and start:

```bash
cd /home/hassan/projects/personal/localWebHosting/dockers
docker-compose down
docker-compose up -d --build
```

Access at: `http://localhost:5001` (local) or via Cloudflare tunnel

---

## Features

- 🐳 Multi-stage builds optimized (slim Python image)
- 🔄 Auto-restart on failure
- 💾 Persistent logs volume
- 🏥 Health checks enabled
- 🌐 Shared network with other services
- 📊 Port 5001 (local) + optional Cloudflare tunnel

## Environment Variables

If you need to customize, add to .env:

```env
FLASK_ENV=production
PYTHONUNBUFFERED=1
```

## Troubleshooting

**See logs:**
```bash
docker logs wp-traider-dashboard -f
```

**Rebuild after code changes:**
```bash
docker-compose down
docker-compose up -d --build
```

**Reset everything:**
```bash
docker-compose down -v
docker-compose up -d --build
```

## Performance Notes

- Dashboard is read-only (backtests, visualization)
- No database required
- Stateless - safe to restart
- CPU: Low (only when generating charts)
- Memory: ~150-200MB base, depends on chart size
