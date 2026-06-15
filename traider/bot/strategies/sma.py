from typing import Dict, Optional
import logging
from .base import BaseStrategy, Signal

logger = logging.getLogger(__name__)


class SMAStrategy(BaseStrategy):
    """Simple Moving Average Crossover Strategy.

    Signals:
    - BUY when SMA(fast) crosses above SMA(slow)
    - SELL when SMA(fast) crosses below SMA(slow)
    """

    def __init__(self, config: Optional[Dict] = None):
        default_config = {
            'fast_period': 50,
            'slow_period': 200,
        }
        if config:
            default_config.update(config)

        super().__init__('SMA', default_config)
        self.validate_config()

    def validate_config(self) -> bool:
        """Check if periods are valid."""
        fast = self.config.get('fast_period', 50)
        slow = self.config.get('slow_period', 200)

        if fast >= slow:
            logger.error("fast_period must be < slow_period")
            return False

        if fast < 2 or slow < 2:
            logger.error("Periods must be >= 2")
            return False

        return True

    def _calculate_sma(self, prices: list, period: int) -> Optional[float]:
        """Calculate Simple Moving Average."""
        if len(prices) < period:
            return None
        return sum(prices[-period:]) / period

    async def on_candle(self, ohlcv: Dict) -> Signal:
        """Process candle and return SMA crossover signal."""
        self.update_history(ohlcv)

        closes = self.get_close_prices()
        fast_period = self.config['fast_period']
        slow_period = self.config['slow_period']

        if len(closes) < slow_period:
            return Signal.HOLD

        fast_sma = self._calculate_sma(closes, fast_period)
        slow_sma = self._calculate_sma(closes, slow_period)

        if not fast_sma or not slow_sma:
            return Signal.HOLD

        # Previous candle SMAs (to detect crossover)
        prev_closes = closes[:-1]
        prev_fast = self._calculate_sma(prev_closes, fast_period)
        prev_slow = self._calculate_sma(prev_closes, slow_period)

        if not prev_fast or not prev_slow:
            current_signal = Signal.BUY if fast_sma > slow_sma else Signal.SELL
        else:
            # Crossover detection
            if prev_fast <= prev_slow and fast_sma > slow_sma:
                current_signal = Signal.BUY
            elif prev_fast >= prev_slow and fast_sma < slow_sma:
                current_signal = Signal.SELL
            else:
                current_signal = Signal.HOLD

        self.last_signal = current_signal
        return current_signal

    async def get_indicator_data(self) -> Dict:
        """Return SMAs for visualization."""
        base = await super().get_indicator_data()
        closes = self.get_close_prices()
        fast_period = self.config['fast_period']
        slow_period = self.config['slow_period']

        smas = []
        for i in range(len(closes)):
            window = closes[:i+1]
            if len(window) >= fast_period:
                smas.append({
                    'timestamp': self.candle_history[i].get('timestamp'),
                    'fast': self._calculate_sma(window, fast_period),
                    'slow': self._calculate_sma(window, slow_period),
                })

        base['smas'] = smas
        return base
