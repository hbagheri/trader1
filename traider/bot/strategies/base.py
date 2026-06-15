from abc import ABC, abstractmethod
from enum import Enum
from typing import Dict, Optional
import logging

logger = logging.getLogger(__name__)


class Signal(Enum):
    BUY = 1
    SELL = -1
    HOLD = 0


class BaseStrategy(ABC):
    """Abstract base class for all trading strategies.

    Inherit from this and implement on_candle() to create a new strategy.
    Each strategy receives OHLCV candles and returns a signal (BUY/SELL/HOLD).
    """

    def __init__(self, name: str, config: Optional[Dict] = None):
        self.name = name
        self.config = config or {}
        self.last_signal = Signal.HOLD
        self.candle_history = []
        logger.info(f"Strategy loaded: {name}")

    @abstractmethod
    async def on_candle(self, ohlcv: Dict) -> Signal:
        """
        Process a new candlestick and return a trading signal.

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
        pass

    @abstractmethod
    def validate_config(self) -> bool:
        """Validate configuration parameters."""
        pass

    def update_history(self, ohlcv: Dict):
        """Store candle for indicator calculation."""
        self.candle_history.append(ohlcv)

    def get_close_prices(self, n: Optional[int] = None) -> list:
        """Get last N close prices."""
        if not self.candle_history:
            return []
        closes = [c['close'] for c in self.candle_history]
        return closes[-n:] if n else closes

    async def get_indicator_data(self) -> Dict:
        """Return indicator values for visualization."""
        return {
            'last_signal': self.last_signal.name,
            'config': self.config,
        }
