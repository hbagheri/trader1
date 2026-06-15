from typing import Dict, Optional
import logging
from .base import BaseStrategy, Signal

logger = logging.getLogger(__name__)


class RSIStrategy(BaseStrategy):
    """Relative Strength Index Strategy.

    Signals:
    - BUY when RSI < oversold_threshold (default 30)
    - SELL when RSI > overbought_threshold (default 70)
    """

    def __init__(self, config: Optional[Dict] = None):
        default_config = {
            'period': 14,
            'overbought': 70,
            'oversold': 30,
        }
        if config:
            default_config.update(config)

        super().__init__('RSI', default_config)
        self.validate_config()

    def validate_config(self) -> bool:
        """Check if thresholds are valid."""
        period = self.config.get('period', 14)
        overbought = self.config.get('overbought', 70)
        oversold = self.config.get('oversold', 30)

        if period < 2:
            logger.error("period must be >= 2")
            return False

        if not (0 < oversold < overbought < 100):
            logger.error("0 < oversold < overbought < 100")
            return False

        return True

    def _calculate_rsi(self, prices: list, period: int) -> Optional[float]:
        """Calculate Relative Strength Index."""
        if len(prices) < period + 1:
            return None

        deltas = [prices[i] - prices[i-1] for i in range(1, len(prices))]

        gains = [d if d > 0 else 0 for d in deltas]
        losses = [-d if d < 0 else 0 for d in deltas]

        avg_gain = sum(gains[-period:]) / period
        avg_loss = sum(losses[-period:]) / period

        if avg_loss == 0:
            return 100.0 if avg_gain > 0 else 50.0

        rs = avg_gain / avg_loss
        rsi = 100 - (100 / (1 + rs))
        return rsi

    async def on_candle(self, ohlcv: Dict) -> Signal:
        """Process candle and return RSI signal."""
        self.update_history(ohlcv)

        closes = self.get_close_prices()
        period = self.config['period']

        if len(closes) < period + 1:
            return Signal.HOLD

        rsi = self._calculate_rsi(closes, period)

        if rsi is None:
            return Signal.HOLD

        overbought = self.config['overbought']
        oversold = self.config['oversold']

        if rsi < oversold:
            current_signal = Signal.BUY
        elif rsi > overbought:
            current_signal = Signal.SELL
        else:
            current_signal = Signal.HOLD

        self.last_signal = current_signal
        return current_signal

    async def get_indicator_data(self) -> Dict:
        """Return RSI values for visualization."""
        base = await super().get_indicator_data()
        closes = self.get_close_prices()
        period = self.config['period']

        rsis = []
        for i in range(len(closes)):
            window = closes[:i+1]
            if len(window) >= period + 1:
                rsis.append({
                    'timestamp': self.candle_history[i].get('timestamp'),
                    'rsi': self._calculate_rsi(window, period),
                })

        base['rsis'] = rsis
        return base
