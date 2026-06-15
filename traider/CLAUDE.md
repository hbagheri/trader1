# CLAUDE.md - Traider Project Guide

Quick reference for Claude Code working with this project.

## Project
- **Grid Trading Bot** for Toobit exchange
- **Status**: v0.3 (MVP + Plugin System + Docker + Cloudflare)
- **Domain**: `algotrader1.hbvsoft.ir`

## Quick Commands

```bash
# Backtest with chart
python run_backtest.py --symbol ETH/USDT --strategy SMA --candles 100 --output results.html

# Run dashboard (local)
python run_dashboard.py    # http://localhost:5000

# Run in Docker
docker compose up -d       # http://localhost:5001

# Live/paper trading
python main.py
```

## Important Notes

- **No Claude co-author signature** in git commits
- **Dashboard port**: 5001 (Docker) or 5000 (direct)
- **Memory location**: `./.claude/memory/` (auto-loaded)
- **Docker**: Always use `docker compose` (not `docker-compose`)

## File Structure

- `bot/strategies/` — Plugin folder for custom strategies
- `backtest/` — Backtesting engine
- `dashboard/` — Flask web UI
- `STRATEGIES.md` — How to add strategies
- `DOCKER.md` — Docker deployment guide

## Key Workflows

### Adding a New Strategy
1. Create `bot/strategies/my_strategy.py`
2. Inherit from `BaseStrategy`
3. Implement `async on_candle(ohlcv) -> Signal`
4. Test: `python run_backtest.py --strategy MyStrategy`

### Testing Dashboard
```bash
python run_dashboard.py
# Go to http://localhost:5000
# Select symbol, strategy, click "Generate Chart"
```

### Deploying to Cloudflare
1. Copy to: `/home/hassan/projects/personal/localWebHosting/dockers/traider`
2. Add DNS CNAME records in Cloudflare
3. Run: `docker-compose up -d --build`

## Memory
- Auto-loaded from `./.claude/memory/`
- Use `/remember` to update
- See `MEMORY.md` for index

## Debugging

**Dashboard not working?**
```bash
docker compose logs dashboard -f
curl http://localhost:5001
```

**Backtest fails?**
```bash
python run_backtest.py --strategy SMA --mock --candles 50
# Use --mock for quick testing
```

**Git issues?**
```bash
git log --oneline -3
# Check last commits
```

---

For detailed guides, see:
- README.md
- STRATEGIES.md
- DOCKER.md
- DEPLOY_CHECKLIST.md
