import asyncio
import logging
from typing import List, Dict, Optional
from datetime import datetime, timedelta

logger = logging.getLogger(__name__)


class BacktestResult:
    def __init__(self):
        self.trades = []
        self.positions = []
        self.balance_history = []
        self.signal_history = []

        self.total_profit = 0.0
        self.total_return = 0.0
        self.win_rate = 0.0
        self.max_drawdown = 0.0
        self.sharpe_ratio = 0.0

    def add_trade(self, entry_price: float, exit_price: float, quantity: float, timestamp: int):
        profit = (exit_price - entry_price) * quantity
        self.trades.append({
            'entry': entry_price,
            'exit': exit_price,
            'quantity': quantity,
            'profit': profit,
            'timestamp': timestamp,
        })
        self.total_profit += profit

    def calculate_metrics(self, initial_balance: float):
        if not self.trades:
            return

        winning_trades = sum(1 for t in self.trades if t['profit'] > 0)
        losing_trades = sum(1 for t in self.trades if t['profit'] < 0)

        if self.trades:
            self.win_rate = winning_trades / len(self.trades)

        self.total_return = (self.total_profit / initial_balance) * 100 if initial_balance > 0 else 0.0

        # Simple max drawdown calculation
        if self.balance_history:
            peak = self.balance_history[0]
            max_dd = 0.0
            for balance in self.balance_history:
                if balance > peak:
                    peak = balance
                drawdown = (peak - balance) / peak
                max_dd = max(max_dd, drawdown)
            self.max_drawdown = max_dd

    def summary(self) -> str:
        return (
            f"Trades: {len(self.trades)} | "
            f"Profit: ${self.total_profit:.2f} | "
            f"Return: {self.total_return:.2f}% | "
            f"Win Rate: {self.win_rate:.1%} | "
            f"Max Drawdown: {self.max_drawdown:.1%}"
        )


class Backtester:
    def __init__(self, strategy, initial_balance: float = 1000.0):
        self.strategy = strategy
        self.initial_balance = initial_balance
        self.result = BacktestResult()

    async def run(self, ohlcv_data: List[Dict]) -> BacktestResult:
        """Run backtest on historical OHLCV data."""
        logger.info(f"Starting backtest with {len(ohlcv_data)} candles")

        balance = self.initial_balance
        position = None

        for i, candle in enumerate(ohlcv_data):
            signal = await self.strategy.on_candle(candle)

            current_price = candle['close']

            # Simple trade execution
            if signal.value == 1 and not position:  # BUY signal
                position = {
                    'entry_price': current_price,
                    'timestamp': candle.get('timestamp'),
                }
                logger.debug(f"BUY @ {current_price}")

            elif signal.value == -1 and position:  # SELL signal
                self.result.add_trade(
                    entry_price=position['entry_price'],
                    exit_price=current_price,
                    quantity=1,
                    timestamp=candle.get('timestamp'),
                )
                balance += (current_price - position['entry_price'])
                position = None
                logger.debug(f"SELL @ {current_price}")

            self.result.balance_history.append(balance)
            self.result.signal_history.append({
                'timestamp': candle.get('timestamp'),
                'signal': signal.name,
                'price': current_price,
            })

        # Close open position at last price
        if position:
            last_price = ohlcv_data[-1]['close']
            self.result.add_trade(
                entry_price=position['entry_price'],
                exit_price=last_price,
                quantity=1,
                timestamp=ohlcv_data[-1].get('timestamp'),
            )

        self.result.calculate_metrics(self.initial_balance)
        logger.info(f"Backtest complete: {self.result.summary()}")

        return self.result
