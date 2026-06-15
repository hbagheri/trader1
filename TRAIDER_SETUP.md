# Traider Dashboard Integration Guide

تکامل Traider Grid Trading Bot Dashboard به Cloudflare Tunnel.

## مراحل:

### 1. **کپی traider directory**

```bash
cp -r /home/hassan/projects/personal/traider /home/hassan/projects/personal/localWebHosting/dockers/
```

### 2. **Update Docker Compose** ✓ (Already done)

- `docker-compose.yml` updated with traider-dashboard service
- `traider-dashboard` added to cloudflared depends_on
- `.cloudflared/config.yaml` mounted into cloudflared container

### 3. **Cloudflare Setup (Choose one)**

#### **Method A: Dashboard UI (Easiest)**

1. Go to **Cloudflare Dashboard** → Your Domain
2. **DNS** → Create CNAME:
   - Name: `traider`
   - Content: `[tunnel-name].cfargotunnel.com`
   - Proxy: **Proxied** (orange cloud)

3. **Workers & Pages** → **Routes** → Add:
   - Domain: `traider.example.com`
   - Service: `wp-cloudflared-tunnel`

#### **Method B: Config File** (Already created)

File: `.cloudflared/config.yaml`

Already configured! (`.cloudflared/config.yaml`):
```yaml
tunnel: wp-cloudflared-tunnel

ingress:
  # Traider Dashboard
  - hostname: traider.algotrader1.hbvsoft.ir
    service: http://traider-dashboard:5000

  # Ayimi (WordPress)
  - hostname: ayimi.algotrader1.hbvsoft.ir
    service: http://ayimi:80

  # PhysicalMe (WordPress)
  - hostname: physicalme.algotrader1.hbvsoft.ir
    service: http://physicalme:80

  # LearnArm API
  - hostname: api.algotrader1.hbvsoft.ir
    service: http://learnarm-api:8000

  # Database Admin
  - hostname: db.algotrader1.hbvsoft.ir
    service: http://phpmyadmin:80
```

### 4. **Build & Deploy**

```bash
cd /home/hassan/projects/personal/localWebHosting/dockers

# Stop current services
docker-compose down

# Rebuild everything
docker-compose up -d --build

# Verify all services running
docker-compose ps
```

### 5. **Verify**

```bash
# Local access
curl http://localhost:5001

# Check logs
docker-compose logs traider-dashboard

# Test tunnel (if using config file method)
docker-compose logs cloudflared | grep traider
```

---

## Architecture

```
┌─────────────────────────────────────┐
│     Cloudflare Tunnel               │
│  (wp-cloudflared-tunnel)            │
└──────────────┬──────────────────────┘
               │
       ┌───────┼───────┐
       │       │       │
   traider  ayimi  physicalme
   :5000    :80      :80
```

---

## Files Modified

1. **`dockers/docker-compose.yml`** — Added traider service + config volume
2. **`dockers/.cloudflared/config.yaml`** — Created with routing rules
3. **`dockers/traider/`** — Copied entire traider directory

---

## Subdomain Structure

After setup, you'll have:

- **`traider.algotrader1.hbvsoft.ir`** → Dashboard (Grid Trading Bot)
- **`ayimi.algotrader1.hbvsoft.ir`** → WordPress Ayimi
- **`physicalme.algotrader1.hbvsoft.ir`** → WordPress PhysicalMe
- **`api.algotrader1.hbvsoft.ir/learnarm`** → LearnArm FastAPI
- **`db.algotrader1.hbvsoft.ir`** → PhpMyAdmin (Database Admin)

---

## Troubleshooting

**Traider not accessible?**
```bash
# Check if service is running
docker-compose ps | grep traider

# Check logs
docker-compose logs traider-dashboard

# Test locally
docker-compose exec traider-dashboard curl http://localhost:5000
```

**Cloudflared not picking up config?**
```bash
# Restart tunnel
docker-compose restart cloudflared

# Check routing
docker-compose logs cloudflared | grep traider
```

**Port conflicts?**
- Default local: `http://localhost:5001`
- Change in `docker-compose.yml`: `ports: "127.0.0.1:XXXX:5000"`

---

## Performance

- **Traider Dashboard**: ~150-200MB RAM
- **All services together**: ~500MB-1GB
- **CPU**: Minimal (only when generating charts)

## Next Steps

1. ✅ Update domain names in config.yaml
2. ✅ Run `docker-compose up -d --build`
3. ✅ Test via Cloudflare subdomain
4. ✅ Monitor with `docker-compose logs`
