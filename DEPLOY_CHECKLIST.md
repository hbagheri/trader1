# Traider + Cloudflare Deployment Checklist

## Domain: `algotrader1.hbvsoft.ir`

### ✅ Ready to Deploy

All files are configured for your domain.

---

## Step 1: Copy Traider Directory

```bash
cp -r /home/hassan/projects/personal/traider \
   /home/hassan/projects/personal/localWebHosting/dockers/
```

Verify:
```bash
ls -la dockers/traider/
# Should show: Dockerfile, docker-compose.yml, bot/, dashboard/, etc.
```

---

## Step 2: Deploy with Docker Compose

```bash
cd /home/hassan/projects/personal/localWebHosting/dockers

# Stop current services
docker-compose down

# Build and start everything
docker-compose up -d --build

# Wait for services to start
sleep 10

# Check status
docker-compose ps
```

Expected output:
```
wp-db                   ✓ running
wp-ayimi                ✓ running
wp-physicalme           ✓ running
wp-learnarm-api         ✓ running
wp-traider-dashboard    ✓ running (healthy)
wp-cloudflared-tunnel   ✓ running
wp-phpmyadmin           ✓ running
```

---

## Step 3: Cloudflare DNS Setup

Go to **Cloudflare Dashboard** → **algotrader1.hbvsoft.ir** → **DNS**

Add these DNS records (if not already present):

| Type  | Name      | Content                      | Proxy |
|-------|-----------|------------------------------|-------|
| CNAME | traider   | wp-cloudflared-tunnel.cfargotunnel.com | 🟠 Proxied |
| CNAME | ayimi     | wp-cloudflared-tunnel.cfargotunnel.com | 🟠 Proxied |
| CNAME | physicalme| wp-cloudflared-tunnel.cfargotunnel.com | 🟠 Proxied |
| CNAME | api       | wp-cloudflared-tunnel.cfargotunnel.com | 🟠 Proxied |
| CNAME | db        | wp-cloudflared-tunnel.cfargotunnel.com | 🟠 Proxied |

**Note:** The exact `cfargotunnel.com` domain depends on your tunnel. Check in Cloudflare Dashboard under:
- **Workers & Pages** → **Tunnels** → `wp-cloudflared-tunnel` → Copy the CNAME target

---

## Step 4: Verify Routing

Cloudflared container will route based on `.cloudflared/config.yaml`:

```bash
# Check if cloudflared recognizes routes
docker-compose logs cloudflared | grep -i ingress

# Should show something like:
# ingress rules loaded from config.yaml
```

---

## Access Points

### Local (No tunnel needed)
- **Traider**: `http://localhost:5001`
- **Ayimi**: `http://localhost:50080`
- **PhysicalMe**: `http://localhost:50081`
- **LearnArm API**: `http://localhost:50082`
- **PhpMyAdmin**: `http://localhost:50090`

### Via Cloudflare Tunnel (from anywhere)
- **Traider**: `https://traider.algotrader1.hbvsoft.ir`
- **Ayimi**: `https://ayimi.algotrader1.hbvsoft.ir`
- **PhysicalMe**: `https://physicalme.algotrader1.hbvsoft.ir`
- **LearnArm API**: `https://api.algotrader1.hbvsoft.ir/learnarm`
- **Database Admin**: `https://db.algotrader1.hbvsoft.ir`

---

## Troubleshooting

### Traider not accessible via tunnel?

1. Check container is running:
```bash
docker-compose ps | grep traider
```

2. Check local access works:
```bash
curl http://localhost:5001
```

3. Check cloudflared logs:
```bash
docker-compose logs cloudflared | tail -50
```

4. Restart tunnel:
```bash
docker-compose restart cloudflared
```

### DNS not resolving?

1. Verify DNS records in Cloudflare (Step 3)
2. Check Cloudflare status page
3. Clear DNS cache: `sudo systemctl restart systemd-resolved`

### Services won't start?

1. Check port conflicts:
```bash
docker-compose logs
```

2. Rebuild everything:
```bash
docker-compose down -v
docker-compose up -d --build
```

---

## Files Modified

- ✅ `dockers/docker-compose.yml` — Added traider-dashboard service
- ✅ `dockers/.cloudflared/config.yaml` — Configured routing for all services
- ✅ `TRAIDER_SETUP.md` — Setup guide

---

## Next Steps

1. ✅ Copy traider directory (Step 1)
2. ✅ Deploy with docker-compose (Step 2)
3. ✅ Add DNS records (Step 3)
4. ✅ Verify access (Step 4)
5. ✅ Test Traider: `traider.algotrader1.hbvsoft.ir`

---

## Performance Notes

- Total memory: ~800MB-1.2GB
- CPU: Low (scales with usage)
- Auto-restart on failure
- Health checks enabled
- Logs: `docker-compose logs [service]`

---

**Ready to deploy?** Run Step 1 and Step 2! 🚀
