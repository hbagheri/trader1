# Grid Trading Bot - Toobit Exchange

A Python-based grid trading bot for the Toobit exchange supporting both Spot and Futures markets.

## Features

- **Grid Trading**: Automatically place and manage a grid of buy/sell orders
- **Configurable Grid**: Arithmetic or geometric grid spacing
- **Risk Management**: Stop-loss triggers, max open orders limit
- **Paper Trading**: Test strategies without real API keys
- **Dual Logging**: Console + rotating log file
- **Graceful Shutdown**: Cancel all orders on exit (SIGINT/SIGTERM)
- **Live Monitoring**: Real-time summary of profit, grid cycles, open orders

## Installation

1. Clone the repository:
```bash
git clone https://github.com/hbagheri/traider.git
cd traider
```

2. Create virtual environment:
```bash
python3 -m venv .venv
source .venv/bin/activate  # On Windows: .venv\Scripts\activate
```

3. Install dependencies:
```bash
pip install -r requirements.txt
```

4. Set up environment:
```bash
cp .env.example .env
# Edit .env and add your Toobit API credentials
```

## Configuration

Edit `config.yaml`:

```yaml
mode: spot                  # spot or futures
symbol: BTC/USDT
upper_price: 70000          # Top of grid range
lower_price: 60000          # Bottom of grid range
num_grids: 10               # Number of grid levels
spacing: arithmetic         # arithmetic or geometric
total_investment: 1000      # USD to invest
paper_trading: true         # Set to false for live trading

risk:
  stop_loss_pct: 5          # Stop-loss threshold (% below lower bound)
  max_open_orders: 20
```

## Usage

### 1. Run Backtest (Test strategies on historical data)
```bash
# SMA strategy backtest, save chart to HTML
python run_backtest.py --symbol ETH/USDT --strategy SMA --candles 100 --output results.html

# RSI strategy with mock data
python run_backtest.py --symbol BTC/USDT --strategy RSI --candles 200 --mock

# Combo strategy (multiple strategies voting)
python run_backtest.py --symbol ETH/USDT --strategy COMBO --candles 100 --output combo_results.html
```

### 2. Web Dashboard (Interactive visualization)
```bash
python run_dashboard.py
# Then open: http://localhost:5000
```

The dashboard allows you to:
- Select a symbol and strategy
- Generate backtest charts with candlesticks + indicators
- See SMA lines, RSI, and trading signals
- View performance metrics (profit, return, win rate, trades)

### 3. Paper Trading (Live simulation)
```bash
# Ensure paper_trading: true in config.yaml
python main.py
```

Trades against real live prices but doesn't place real orders.

### 4. Live Trading
```bash
# WARNING: Only after thorough testing!
# 1. Edit config.yaml: paper_trading: false
# 2. Edit .env with real API credentials
python main.py
```

To stop gracefully:
```bash
Ctrl+C  # SIGINT
```

## How It Works

1. **Grid Initialization**: Places buy orders at all grid levels below current price
2. **Order Fill**: When a buy order fills → place sell order one level above
3. **Cycle Completion**: When a sell order fills → place buy order one level below
4. **Profit Tracking**: Each cycle profit is calculated and logged
5. **Risk Management**: Monitors stop-loss and price bounds

## Grid Spacing

- **Arithmetic**: Equal price intervals (linear spacing)
- **Geometric**: Equal percentage intervals (exponential spacing)

## Logging

- **Console**: INFO level and above
- **File**: DEBUG level (logs/bot.log) with rotation
- Check `logs/bot.log` for detailed trading history

## Plugin System

**Key feature:** Add new strategies without modifying the kernel.

1. Create a new file: `bot/strategies/my_strategy.py`
2. Inherit from `BaseStrategy`
3. Implement `on_candle()` method
4. That's it! No kernel changes needed.

See `STRATEGIES.md` for detailed guide and examples.

## Roadmap

- [x] Strategy plugin system (SMA, RSI, COMBO)
- [x] Backtesting framework
- [x] Web dashboard with Plotly
- [ ] Futures (USDT-M perpetual) support
- [ ] WebSocket real-time order fills
- [ ] Advanced strategies (MACD, Bollinger Bands, etc.)
- [ ] Multiple symbol support
- [ ] Strategy optimization (parameter tuning)

## Security

- API credentials stored in `.env` (never commit)
- Always test with paper trading first
- Start with small investment amounts
- Monitor logs carefully

## License

MIT
