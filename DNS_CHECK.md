# دامین Check Guide

## 🔍 چطوری دامین رو چک کنی

### **Step 1: Check if domain uses Cloudflare nameservers**

```bash
nslookup hbvsoft.ir
# یا
dig hbvsoft.ir NS +short
```

Expected output (Cloudflare nameservers):
```
dawn.ns.cloudflare.com
henry.ns.cloudflare.com
```

### **Step 2: Check subdomain DNS records in Cloudflare**

```bash
nslookup traider.algotrader1.hbvsoft.ir 8.8.8.8
# یا
dig traider.algotrader1.hbvsoft.ir +short
```

Expected:
```
wp-cloudflared-tunnel.cfargotunnel.com
```

### **Step 3: Check if tunnel is active**

```bash
# Check tunnel status in Cloudflare Dashboard:
# Workers & Pages → Tunnels → wp-cloudflared-tunnel → Status should be "ACTIVE"
```

### **Step 4: Test locally first**

```bash
# Make sure docker services are running
docker-compose ps

# Test local access
curl http://localhost:5001

# Test from container
docker-compose exec traider-dashboard curl http://localhost:5000
```

---

## ⚠️ اگر domain resolve نمی‌شه:

### **اولین چیز: Check Cloudflare nameservers**

1. برو **Cloudflare Dashboard** → `hbvsoft.ir` → **Nameservers**
2. ببین آیا domain registrar رو nameservers اپدیت کرده؟
3. اگر نه، باید registrar (جایی که domain رو گرفتی) رو update کنی

مثال:
- Old nameservers: `ns1.example.com`, `ns2.example.com`
- New (Cloudflare): `dawn.ns.cloudflare.com`, `henry.ns.cloudflare.com`

---

### **دوم: اگر parent domain resolve می‌شه، subdomain رو check کن**

**In Cloudflare Dashboard:**
1. **DNS** tab رو باز کن
2. ببین `traider` CNAME record exist کنه؟
3. اگه نه، اضافه کن:
   - Type: **CNAME**
   - Name: **traider**
   - Content: **wp-cloudflared-tunnel.cfargotunnel.com** (یا tunnel CNAME)
   - Proxy: **🟠 Proxied**

---

## 🔧 Complete DNS Check Script

```bash
#!/bin/bash

echo "=== Domain DNS Check ==="
echo ""

echo "1. Check parent domain:"
dig hbvsoft.ir NS +short
echo ""

echo "2. Check subdomain:"
dig traider.algotrader1.hbvsoft.ir +short
echo ""

echo "3. Check tunnel status:"
dig wp-cloudflared-tunnel.cfargotunnel.com +short
echo ""

echo "4. Check local services:"
docker-compose ps | grep -E "(traider|cloudflared)"
echo ""

echo "5. Test local access:"
curl -I http://localhost:5001 2>&1 | head -3
echo ""

echo "=== End of Check ==="
```

---

## 📊 Expected DNS Flow

```
traider.algotrader1.hbvsoft.ir (CNAME)
    ↓
wp-cloudflared-tunnel.cfargotunnel.com
    ↓
Cloudflare Tunnel (active)
    ↓
Docker: traider-dashboard:5000
```

---

## ⏱️ DNS Propagation Time

- **Cloudflare CNAME records**: Usually immediate (minutes)
- **Registrar nameserver changes**: Can take 24-48 hours
- **DNS cache**: Browser might cache for minutes

### Clear DNS cache:
```bash
# Linux
sudo systemctl restart systemd-resolved

# macOS
sudo dscacheutil -flushcache

# Windows
ipconfig /flushdns
```

---

## 🚀 Test Steps (In Order)

1. ✅ **Check parent domain** → `hbvsoft.ir` resolves to Cloudflare NS
2. ✅ **Check Cloudflare config** → CNAME records exist in Dashboard
3. ✅ **Check tunnel** → Active in Cloudflare Tunnels page
4. ✅ **Check local** → `curl http://localhost:5001` works
5. ✅ **Check Docker** → `docker-compose ps` shows all healthy
6. ✅ **Test subdomain** → `ping traider.algotrader1.hbvsoft.ir` (should resolve)

---

## Common Issues

### **Domain doesn't resolve**
- [ ] Is parent domain (`hbvsoft.ir`) pointing to Cloudflare nameservers?
- [ ] Are CNAME records created in Cloudflare?
- [ ] Did you wait 24 hours for nameserver propagation?

### **Domain resolves but no response**
- [ ] Is docker service running? `docker-compose ps`
- [ ] Is tunnel active? Check Cloudflare Dashboard
- [ ] Is health check passing? `docker-compose ps` → status = "healthy"

### **Connection refused**
- [ ] Is cloudflared container running?
- [ ] Check logs: `docker-compose logs cloudflared`
- [ ] Check routing in config.yaml matches hostname

---

## Quick Troubleshooting

```bash
# See if domain resolves to anything
nslookup traider.algotrader1.hbvsoft.ir

# Check cloudflared routing
docker-compose logs cloudflared | grep traider

# Test tunnel connection
curl -v https://traider.algotrader1.hbvsoft.ir

# Check certificate (should be valid)
echo | openssl s_client -servername traider.algotrader1.hbvsoft.ir \
  -connect traider.algotrader1.hbvsoft.ir:443 2>/dev/null | grep -A 2 "subject="
```

---

**نتیجه؟** Report کن چی دیدی! 📝
