#!/usr/bin/env python3

import asyncio
import signal
import sys
import os
from pathlib import Path

import yaml
from dotenv import load_dotenv

from bot.exchange import ExchangeClient
from bot.grid_engine import GridEngine
from bot.order_manager import OrderManager
from bot.risk_manager import RiskManager
from bot.monitor import setup_logging, print_summary

load_dotenv()

logger = None
exchange = None
order_manager = None
risk_manager = None
shutdown_event = asyncio.Event()


def signal_handler(signum, frame):
    """Graceful shutdown on SIGINT/SIGTERM."""
    logger.info(f"Received signal {signum}. Initiating graceful shutdown...")
    shutdown_event.set()


async def main_loop():
    """Main trading loop."""
    global exchange, order_manager, risk_manager, logger

    config_path = Path(__file__).parent / 'config.yaml'
    with open(config_path) as f:
        config = yaml.safe_load(f)

    logger = setup_logging(config['monitoring']['log_file'])
    logger.info("=" * 100)
    logger.info("Grid Trading Bot started")
    logger.info(f"Config: {config}")
    logger.info("=" * 100)

    api_key = os.getenv('TOOBIT_API_KEY')
    api_secret = os.getenv('TOOBIT_API_SECRET')
    paper_trading = config.get('paper_trading', False)

    exchange = ExchangeClient(
        api_key=api_key,
        api_secret=api_secret,
        paper_trading=paper_trading
    )
    await exchange.init()

    grid_engine = GridEngine(
        upper=config['upper_price'],
        lower=config['lower_price'],
        num_grids=config['num_grids'],
        spacing=config['spacing']
    )

    order_manager = OrderManager(exchange, grid_engine, config['symbol'])

    risk_manager = RiskManager(
        lower_price=config['lower_price'],
        upper_price=config['upper_price'],
        stop_loss_pct=config['risk']['stop_loss_pct'],
        max_orders=config['risk']['max_open_orders']
    )

    logger.info(f"Mode: {'Paper Trading' if paper_trading else 'Live Trading'}")
    logger.info(f"Symbol: {config['symbol']}")
    logger.info(f"Grid levels: {grid_engine.get_summary()}")

    try:
        await order_manager.initialize_grid(config['total_investment'])

        summary_counter = 0
        summary_interval = config['monitoring']['summary_interval']

        while not shutdown_event.is_set():
            try:
                current_price = await exchange.get_price(config['symbol'])

                if risk_manager.check_stop_loss(current_price):
                    await risk_manager.handle_stop_loss(order_manager)
                    break

                if risk_manager.check_price_out_of_bounds(current_price):
                    logger.warning("Price out of bounds. Consider adjusting grid.")

                summary_counter += 1
                if summary_counter >= summary_interval:
                    print_summary(grid_engine, current_price, risk_manager)
                    summary_counter = 0

                await asyncio.sleep(1)

            except Exception as e:
                logger.error(f"Error in main loop: {e}", exc_info=True)
                await asyncio.sleep(5)

    except KeyboardInterrupt:
        logger.info("Interrupted by user")
    finally:
        logger.info("Shutting down...")
        await order_manager.cancel_all_orders()
        await exchange.close()
        logger.info("Bot stopped gracefully")


if __name__ == '__main__':
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    asyncio.run(main_loop())
