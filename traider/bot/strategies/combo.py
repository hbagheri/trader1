from typing import List, Dict, Optional
import logging
from .base import BaseStrategy, Signal

logger = logging.getLogger(__name__)


class ComboStrategy(BaseStrategy):
    """Combine multiple strategies with voting.

    Signals are based on majority vote:
    - If >50% vote BUY → BUY
    - If >50% vote SELL → SELL
    - Otherwise → HOLD
    """

    def __init__(self, strategies: List[BaseStrategy], name: str = 'Combo'):
        config = {
            'strategies': [s.name for s in strategies],
        }
        super().__init__(name, config)
        self.strategies = strategies

        if not strategies:
            logger.warning("ComboStrategy initialized with no strategies")

    def validate_config(self) -> bool:
        """All component strategies must validate."""
        return all(s.validate_config() for s in self.strategies)

    async def on_candle(self, ohlcv: Dict) -> Signal:
        """Aggregate signals from all strategies."""
        if not self.strategies:
            return Signal.HOLD

        signals = []
        for strategy in self.strategies:
            signal = await strategy.on_candle(ohlcv)
            signals.append(signal)

        buy_votes = sum(1 for s in signals if s == Signal.BUY)
        sell_votes = sum(1 for s in signals if s == Signal.SELL)
        total = len(signals)

        if buy_votes / total > 0.5:
            current_signal = Signal.BUY
        elif sell_votes / total > 0.5:
            current_signal = Signal.SELL
        else:
            current_signal = Signal.HOLD

        self.last_signal = current_signal
        return current_signal

    async def get_indicator_data(self) -> Dict:
        """Combine indicator data from all strategies."""
        base = await super().get_indicator_data()
        base['strategies'] = []

        for strategy in self.strategies:
            strategy_data = await strategy.get_indicator_data()
            base['strategies'].append({
                'name': strategy.name,
                'data': strategy_data,
            })

        return base
