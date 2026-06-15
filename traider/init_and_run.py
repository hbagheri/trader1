#!/usr/bin/env python3
"""Initialize database and start dashboard + price collector."""
import asyncio
import logging
import sys
import threading
from models import init_db
from price_collector import PriceCollector
from dashboard.app import DashboardApp

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


def start_price_collector():
    """Start price collector in background."""
    try:
        logger.info("Starting price collector service...")
        collector = PriceCollector()

        # Create event loop for price collector
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)

        # Load historical data once
        logger.info("Loading historical data...")
        loop.run_until_complete(
            collector.load_historical_data(['BTC/USDT', 'ETH/USDT'])
        )

        # Start continuous collection
        logger.info("Starting continuous price collection...")
        loop.run_until_complete(
            collector.start_collection_loop(['BTC/USDT', 'ETH/USDT'], interval=20)
        )
    except Exception as e:
        logger.error(f"Price collector error: {e}")


def main():
    """Initialize database and start services."""
    logger.info("========================================")
    logger.info("  Trading Bot Dashboard - Initializing")
    logger.info("========================================")

    # Initialize database
    try:
        logger.info("Initializing database...")
        init_db()
        logger.info("✅ Database initialized successfully")
    except Exception as e:
        logger.error(f"❌ Database initialization failed: {e}")
        sys.exit(1)

    # Start price collector in background thread
    try:
        collector_thread = threading.Thread(target=start_price_collector, daemon=True)
        collector_thread.start()
        logger.info("✅ Price collector started in background")
    except Exception as e:
        logger.warning(f"⚠️  Price collector failed to start: {e}")

    # Start Flask dashboard
    try:
        logger.info("Starting Flask dashboard...")
        dashboard = DashboardApp(debug=False)
        logger.info("✅ Dashboard started successfully")
        dashboard.run(host='0.0.0.0', port=5000)
    except Exception as e:
        logger.error(f"❌ Dashboard failed: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()
