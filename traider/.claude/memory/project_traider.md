---
name: traider_grid_bot_project
description: Grid Trading Bot for Toobit + Cloudflare deployment (Docker + tunnel)
metadata: 
  node_type: memory
  type: project
  originSessionId: 535afd2b-9eae-40d4-9e26-e10aa145a9ea
---

## Grid Trading Bot - Toobit Exchange

**Status:** v0.3 - MVP + Plugin System + Docker + Cloudflare Ready
**Locations:** 
- Source: `/home/hassan/projects/personal/traider`
- Deployment: `/home/hassan/projects/personal/localWebHosting/dockers/traider` (to be copied)

### ✅ What's Built

1. **Grid Trading Bot (Core MVP)**
   - Paper trading + live trading
   - Graceful shutdown (SIGINT/SIGTERM)
   - Dual logging (console + file)
   - Running in Docker ✓

2. **Strategy Plugin System** ⭐
   - Abstract `BaseStrategy` class
   - SMA, RSI, COMBO (voting)
   - NEW strategies = no kernel changes
   - Each returns Signal.BUY/SELL/HOLD

3. **Backtest Engine**
   - Replay historical OHLCV
   - Metrics: profit, return %, win rate, max drawdown
   - Real or mock data

4. **Web Dashboard (Flask + Plotly)**
   - Interactive candlesticks
   - SMA + RSI indicators
   - Trading signals (buy/sell markers)
   - Performance metrics
   - Port: 5001 (local) or via tunnel

5. **Docker Containerization** ✓
   - Dockerfile optimized (Python 3.11-slim)
   - Health checks
   - Persistent logs volume
   - docker-compose.yml ready

6. **Cloudflare Tunnel Integration** 🌐
   - `.cloudflared/config.yaml` configured
   - Domain: `algotrader1.hbvsoft.ir`
   - Subdomains ready:
     - `traider.algotrader1.hbvsoft.ir` → Dashboard
     - `ayimi.algotrader1.hbvsoft.ir` → WordPress
     - `physicalme.algotrader1.hbvsoft.ir` → WordPress
     - `api.algotrader1.hbvsoft.ir` → LearnArm
     - `db.algotrader1.hbvsoft.ir` → PhpMyAdmin

### 📁 Project Structure

```
traider/
├── main.py, run_backtest.py, run_dashboard.py
├── Dockerfile, docker-compose.yml, .dockerignore
├── config.yaml, requirements.txt, .env.example
├── bot/
│   ├── exchange.py, grid_engine.py, order_manager.py
│   ├── risk_manager.py, monitor.py
│   └── strategies/  # PLUGIN FOLDER
│       ├── base.py, sma.py, rsi.py, combo.py
├── backtest/
│   ├── engine.py, data.py
├── dashboard/
│   ├── app.py, charts.py
│   └── templates/index.html
├── logs/ (gitignored)
├── README.md, STRATEGIES.md, DOCKER.md
```

### 🚀 Quick Start

**Local (standalone):**
```bash
docker compose up -d
curl http://localhost:5001
```

**Production (Cloudflare):**
```bash
# Copy to localWebHosting
cp -r /home/hassan/projects/personal/traider \
   /home/hassan/projects/personal/localWebHosting/dockers/

cd localWebHosting/dockers
docker-compose down
docker-compose up -d --build

# Then add DNS CNAME records in Cloudflare Dashboard
```

### 🌐 Cloudflare Setup Status

**✅ Done:**
- `.cloudflared/config.yaml` configured with all routes
- docker-compose.yml updated with traider service
- DEPLOY_CHECKLIST.md + DNS_CHECK.md guides created
- Parent domain (hbvsoft.ir) uses Cloudflare nameservers ✓

**⏳ TODO:**
- Copy traider directory to localWebHosting/dockers/
- Add DNS CNAME records in Cloudflare Dashboard (5 records)
- Deploy: `docker-compose up -d --build`
- Verify: `dig traider.algotrader1.hbvsoft.ir`

### 🔧 Git Commits

1. **Initial MVP** — Grid trading bot core (1fb0b22)
2. **Plugin system** — Strategies + backtest + dashboard (388d024)
3. **Docker setup** — Containerization (03868a9)
4. **Cloudflare** — (pending)

**Note:** No Claude co-author signature in commits (per user request)

### 📚 Key Files

- `STRATEGIES.md` — How to create custom strategies
- `DOCKER.md` — Docker deployment guide
- `DEPLOY_CHECKLIST.md` — Step-by-step deployment
- `DNS_CHECK.md` — Domain verification commands
- `.cloudflared/config.yaml` — Tunnel routing (ready to use)

### 💾 Dependencies

- ccxt, pyyaml, python-dotenv, aiohttp
- flask, flask-cors, plotly
- Docker + docker-compose

### 🎯 Next Steps

1. Copy traider to localWebHosting/dockers/
2. Deploy with docker-compose
3. Add 5 CNAME records in Cloudflare DNS
4. Verify domain resolution
5. Access traider.algotrader1.hbvsoft.ir

### 📝 Deployment Notes

- Domain: `algotrader1.hbvsoft.ir`
- Port (local): 5001
- Memory: ~150-200MB per service
- Auto-restart: enabled
- Health checks: enabled
- Tunnel token: in `.env` (TUNNEL_TOKEN variable)
