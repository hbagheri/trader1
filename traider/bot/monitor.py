import logging
import logging.handlers
import os
from pathlib import Path


def setup_logging(log_file: str = 'logs/bot.log') -> logging.Logger:
    """Configure dual logging: file + console."""
    os.makedirs(os.path.dirname(log_file) or '.', exist_ok=True)

    logger = logging.getLogger()
    logger.setLevel(logging.DEBUG)

    formatter = logging.Formatter(
        '%(asctime)s [%(levelname)-8s] %(name)s: %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )

    file_handler = logging.handlers.RotatingFileHandler(
        log_file, maxBytes=10 * 1024 * 1024, backupCount=5
    )
    file_handler.setLevel(logging.DEBUG)
    file_handler.setFormatter(formatter)

    console_handler = logging.StreamHandler()
    console_handler.setLevel(logging.INFO)
    console_handler.setFormatter(formatter)

    logger.addHandler(file_handler)
    logger.addHandler(console_handler)

    return logger


def print_summary(grid_engine, exchange_price: float, risk_manager):
    """Print live trading summary."""
    print("\n" + "=" * 100)
    print(f"Current Price: ${exchange_price:.2f}")
    print(grid_engine.get_summary())
    if risk_manager.stop_loss_triggered:
        print("⚠️  STOP LOSS ACTIVE")
    print("=" * 100 + "\n")
