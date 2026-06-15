import asyncio
import logging
from typing import List, Dict, Optional
import ccxt.async_support as ccxt

logger = logging.getLogger(__name__)


class DataLoader:
    def __init__(self, exchange_name: str = 'toobit'):
        self.exchange_name = exchange_name
        self.exchange = None

    async def init(self):
        """Initialize exchange connection."""
        if self.exchange_name == 'toobit':
            self.exchange = ccxt.toobit()
        else:
            self.exchange = getattr(ccxt, self.exchange_name)()

    async def close(self):
        """Close exchange connection."""
        if self.exchange:
            await self.exchange.close()

    async def fetch_ohlcv(
        self,
        symbol: str,
        timeframe: str = '1h',
        limit: int = 100,
        since: Optional[int] = None
    ) -> List[Dict]:
        """Fetch OHLCV data from exchange.

        Args:
            symbol: Trading pair (e.g. 'BTC/USDT')
            timeframe: Candle interval ('1m', '5m', '1h', '4h', '1d', etc)
            limit: Number of candles to fetch
            since: Starting timestamp in ms (optional)

        Returns:
            List of OHLCV dicts with keys: timestamp, open, high, low, close, volume
        """
        logger.info(f"Fetching {limit} {timeframe} candles for {symbol}")

        try:
            ohlcv_list = await self.exchange.fetch_ohlcv(
                symbol, timeframe, since, limit
            )

            result = []
            for ohlcv in ohlcv_list:
                result.append({
                    'timestamp': int(ohlcv[0]),
                    'open': float(ohlcv[1]),
                    'high': float(ohlcv[2]),
                    'low': float(ohlcv[3]),
                    'close': float(ohlcv[4]),
                    'volume': float(ohlcv[5]),
                })

            logger.info(f"Fetched {len(result)} candles")
            return result

        except Exception as e:
            logger.error(f"Failed to fetch OHLCV: {e}")
            return []

    def generate_mock_data(
        self,
        symbol: str,
        num_candles: int = 100,
        start_price: float = 65000.0,
        volatility: float = 0.02
    ) -> List[Dict]:
        """Generate synthetic OHLCV data for testing.

        Useful when real exchange data is not available.
        """
        import random
        from datetime import datetime, timedelta

        logger.info(f"Generating {num_candles} mock candles for {symbol}")

        data = []
        timestamp = int(datetime.now().timestamp() * 1000) - (num_candles * 3600 * 1000)
        price = start_price

        for i in range(num_candles):
            # Random walk
            change = random.gauss(0, volatility * price)
            price += change
            price = max(price, 1000)  # Don't go below 1000

            open_p = price
            close_p = price + random.gauss(0, 0.005 * price)
            high_p = max(open_p, close_p) * (1 + abs(random.gauss(0, 0.01)))
            low_p = min(open_p, close_p) * (1 - abs(random.gauss(0, 0.01)))
            volume = random.uniform(100, 1000)

            data.append({
                'timestamp': timestamp,
                'open': float(open_p),
                'high': float(high_p),
                'low': float(low_p),
                'close': float(close_p),
                'volume': float(volume),
            })

            timestamp += 3600 * 1000  # 1 hour

        return data
