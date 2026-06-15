# Trading Strategies Plugin System

The bot uses a **plugin architecture** for strategies. Adding a new strategy requires **no kernel changes** — just create a new file in `bot/strategies/` and inherit from `BaseStrategy`.

## Available Strategies

### 1. SMA (Simple Moving Average Crossover)
**File:** `bot/strategies/sma.py`

Signals:
- **BUY** when SMA(50) crosses above SMA(200)
- **SELL** when SMA(50) crosses below SMA(200)

Config:
```yaml
sma:
  fast_period: 50
  slow_period: 200
```

### 2. RSI (Relative Strength Index)
**File:** `bot/strategies/rsi.py`

Signals:
- **BUY** when RSI < 30 (oversold)
- **SELL** when RSI > 70 (overbought)

Config:
```yaml
rsi:
  period: 14
  overbought: 70
  oversold: 30
```

### 3. Grid Trading
**File:** `bot/strategies/grid.py` (planned)

The original MVP grid trading strategy.

### 4. Combo (Multi-Strategy)
**File:** `bot/strategies/combo.py`

Combines multiple strategies with majority voting:
- **BUY** if >50% of strategies vote BUY
- **SELL** if >50% of strategies vote SELL
- **HOLD** otherwise

## Creating Your Own Strategy

### Step 1: Create a new file in `bot/strategies/`

Example: `bot/strategies/my_strategy.py`

```python
from typing import Dict, Optional
from .base import BaseStrategy, Signal

class MyStrategy(BaseStrategy):
    def __init__(self, config: Optional[Dict] = None):
        default_config = {
            'param1': 10,
            'param2': 20,
        }
        if config:
            default_config.update(config)
        
        super().__init__('MyStrategy', default_config)
        self.validate_config()

    def validate_config(self) -> bool:
        """Validate your config parameters."""
        return True

    async def on_candle(self, ohlcv: Dict) -> Signal:
        """
        Process a candlestick and return a signal.
        
        Args:
            ohlcv: {
                'timestamp': int,
                'open': float,
                'high': float,
                'low': float,
                'close': float,
                'volume': float,
            }
        
        Returns:
            Signal.BUY, Signal.SELL, or Signal.HOLD
        """
        self.update_history(ohlcv)
        
        closes = self.get_close_prices()
        
        # Your strategy logic here
        if closes[-1] > closes[-2]:
            signal = Signal.BUY
        else:
            signal = Signal.SELL
        
        self.last_signal = signal
        return signal

    async def get_indicator_data(self) -> Dict:
        """Return indicator values for visualization."""
        base = await super().get_indicator_data()
        # Add your indicator data
        base['my_indicator'] = [...]
        return base
```

### Step 2: Use your strategy

**In backtest:**
```python
from bot.strategies.my_strategy import MyStrategy

strategy = MyStrategy({
    'param1': 15,
    'param2': 25,
})
backtester = Backtester(strategy)
result = await backtester.run(ohlcv_data)
```

**In config.yaml:**
```yaml
strategy: MyStrategy
config:
  param1: 15
  param2: 25
```

## Base Strategy API

All strategies inherit from `BaseStrategy` and must implement:

### `async on_candle(ohlcv: Dict) -> Signal`
Called on every new candlestick. Return a trading signal.

### `validate_config() -> bool`
Validate configuration parameters. Return `True` if valid.

### `update_history(ohlcv: Dict)`
Store candle in history (called automatically).

### `get_close_prices(n: Optional[int]) -> list`
Get last N close prices (helper for calculations).

### `async get_indicator_data() -> Dict`
Return indicator values for visualization/dashboard.

## Testing Your Strategy

### Command-line backtest:
```bash
python run_backtest.py --symbol ETH/USDT --strategy MyStrategy --candles 100 --output results.html
```

### In Python:
```python
import asyncio
from bot.strategies.my_strategy import MyStrategy
from backtest.engine import Backtester
from backtest.data import DataLoader

async def test():
    data_loader = DataLoader()
    ohlcv = data_loader.generate_mock_data('ETH/USDT', num_candles=200)
    
    strategy = MyStrategy()
    backtester = Backtester(strategy)
    result = await backtester.run(ohlcv)
    
    print(result.summary())

asyncio.run(test())
```

## Best Practices

1. **Use helpers**: `get_close_prices()`, `update_history()` are available
2. **Store state sparingly**: Only keep necessary history (e.g., don't store 1000 candles)
3. **Return HOLD when uncertain**: Better to skip a trade than signal falsely
4. **Validate config**: Always check parameters are sensible
5. **Test with mock data first**: Use `generate_mock_data()` before live trading

## Signal Aggregation

For multi-strategy bots, signals are aggregated using `ComboStrategy`:
- Each strategy votes BUY/SELL/HOLD
- Majority vote wins
- Useful for reducing false signals

## Visualization

Indicators are automatically visualized on the dashboard if `get_indicator_data()` returns the right keys. For example, SMA strategy returns:

```python
{
    'smas': [
        {'timestamp': ..., 'fast': 65000.0, 'slow': 64500.0},
        ...
    ]
}
```

The dashboard expects:
- `smas`: List with 'fast' and 'slow' keys
- `rsis`: List with 'rsi' key
- Custom indicators: Add to the list and update `dashboard/charts.py`

## See Also

- `backtest/engine.py` — Backtesting framework
- `dashboard/charts.py` — Charting with Plotly
- `bot/strategies/base.py` — Base class definition
