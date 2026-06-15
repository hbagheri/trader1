#!/usr/bin/env python3
"""
Simple backtest runner for strategies.

Usage:
    python run_backtest.py --symbol BTC/USDT --strategy SMA --candles 100
"""

import asyncio
import argparse
import logging
from pathlib import Path

from bot.monitor import setup_logging
from bot.strategies.sma import SMAStrategy
from bot.strategies.rsi import RSIStrategy
from bot.strategies.combo import ComboStrategy
from backtest.engine import Backtester
from backtest.data import DataLoader
from dashboard.charts import create_candlestick_chart

logger = logging.getLogger(__name__)


def get_strategy(strategy_name: str):
    """Load strategy by name."""
    if strategy_name == 'SMA':
        return SMAStrategy({
            'fast_period': 50,
            'slow_period': 200,
        })
    elif strategy_name == 'RSI':
        return RSIStrategy({
            'period': 14,
            'overbought': 70,
            'oversold': 30,
        })
    elif strategy_name == 'COMBO':
        return ComboStrategy([
            SMAStrategy(),
            RSIStrategy(),
        ], name='SMA+RSI')
    else:
        raise ValueError(f"Unknown strategy: {strategy_name}")


async def main():
    parser = argparse.ArgumentParser(description='Run backtest')
    parser.add_argument('--symbol', default='ETH/USDT', help='Trading symbol')
    parser.add_argument('--strategy', default='SMA', help='Strategy name (SMA, RSI, COMBO)')
    parser.add_argument('--candles', type=int, default=100, help='Number of candles')
    parser.add_argument('--mock', action='store_true', help='Use mock data (default)')
    parser.add_argument('--output', help='Save chart to HTML file')

    args = parser.parse_args()

    # Setup logging
    logger_obj = setup_logging('logs/backtest.log')
    logger.info(f"Running backtest: {args.strategy} on {args.symbol}")

    # Load data
    data_loader = DataLoader()

    if args.mock:
        ohlcv = data_loader.generate_mock_data(args.symbol, num_candles=args.candles)
        logger.info(f"Generated {len(ohlcv)} mock candles")
    else:
        await data_loader.init()
        ohlcv = await data_loader.fetch_ohlcv(args.symbol, '1h', limit=args.candles)
        await data_loader.close()

        if not ohlcv:
            logger.error("Failed to fetch data")
            return

    # Run backtest
    strategy = get_strategy(args.strategy)
    backtester = Backtester(strategy, initial_balance=1000.0)
    result = await backtester.run(ohlcv)

    # Get indicator data
    indicator_data = None
    if hasattr(strategy, 'get_indicator_data'):
        indicator_data = await strategy.get_indicator_data()

    # Print results
    print("\n" + "=" * 80)
    print(f"BACKTEST RESULTS: {args.strategy} on {args.symbol}")
    print("=" * 80)
    print(f"Total Profit: ${result.total_profit:.2f}")
    print(f"Total Return: {result.total_return:.2f}%")
    print(f"Win Rate: {result.win_rate:.1%}")
    print(f"Max Drawdown: {result.max_drawdown:.1%}")
    print(f"Total Trades: {len(result.trades)}")
    print("=" * 80 + "\n")

    # Generate chart
    fig = create_candlestick_chart(
        ohlcv,
        sma_data=indicator_data if args.strategy in ('SMA', 'COMBO') else None,
        rsi_data=indicator_data if args.strategy in ('RSI', 'COMBO') else None,
        signal_data=result.signal_history,
        title=f"{args.symbol} - {args.strategy} Backtest"
    )

    # Save or show
    if args.output:
        fig.write_html(args.output)
        logger.info(f"Chart saved to {args.output}")
        print(f"Chart saved to: {args.output}")
    else:
        fig.show()


if __name__ == '__main__':
    asyncio.run(main())
