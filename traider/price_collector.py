"""Price collector service - fetches data from exchanges and stores in DB/Redis."""
import asyncio
import logging
from datetime import datetime, timedelta
import ccxt.async_support as ccxt
import redis
import json
import os
from sqlalchemy.orm import Session
from models import SessionLocal, Candle

logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO)

REDIS_URL = os.getenv('REDIS_URL', 'redis://:redis_password@localhost:6379/0')
BINANCE_LIMIT = 1000
TOOBIT_LIMIT = 1000


class PriceCollector:
    """Collects prices from multiple exchanges and stores in DB/Redis."""

    def __init__(self):
        self.redis_client = self._init_redis()
        self.exchanges = {
            'binance': ccxt.binance(),
            'toobit': ccxt.toobit() if hasattr(ccxt, 'toobit') else None,
        }

    def _init_redis(self):
        """Initialize Redis connection."""
        try:
            # Parse redis URL
            import redis
            if REDIS_URL.startswith('redis://'):
                url_parts = REDIS_URL.replace('redis://', '').split('@')
                password = url_parts[0].split(':')[1] if ':' in url_parts[0] else None
                host_port = url_parts[1].split(':')
                host = host_port[0]
                port = int(host_port[1].split('/')[0])
                db = int(url_parts[1].split('/')[-1]) if '/' in url_parts[1] else 0

                return redis.Redis(host=host, port=port, db=db, password=password, decode_responses=True)
            else:
                return redis.from_url(REDIS_URL, decode_responses=True)
        except Exception as e:
            logger.error(f"Redis connection failed: {e}")
            return None

    async def fetch_candles(self, exchange_name: str, symbol: str, timeframe: str = '1m', limit: int = 1000):
        """Fetch candlestick data from exchange."""
        try:
            exchange = self.exchanges.get(exchange_name.lower())
            if not exchange:
                logger.error(f"Exchange {exchange_name} not supported")
                return None

            # Fetch OHLCV data
            ohlcv = await exchange.fetch_ohlcv(symbol, timeframe, limit=limit)

            candles = []
            for candle in ohlcv:
                candles.append({
                    'timestamp': int(candle[0]),
                    'open': float(candle[1]),
                    'high': float(candle[2]),
                    'low': float(candle[3]),
                    'close': float(candle[4]),
                    'volume': float(candle[5])
                })

            return candles
        except Exception as e:
            logger.error(f"Error fetching {symbol} from {exchange_name}: {e}")
            return None

    async def collect_price_snapshot(self, exchange_name: str, symbol: str):
        """Fetch current price and store in Redis."""
        try:
            exchange = self.exchanges.get(exchange_name.lower())
            if not exchange:
                return None

            ticker = await exchange.fetch_ticker(symbol)

            price_data = {
                'symbol': symbol,
                'exchange': exchange_name,
                'price': ticker['last'],
                'bid': ticker.get('bid'),
                'ask': ticker.get('ask'),
                'high': ticker.get('high'),
                'low': ticker.get('low'),
                'volume': ticker.get('volume'),
                'timestamp': int(ticker['timestamp'] / 1000),
            }

            # Store in Redis
            if self.redis_client:
                redis_key = f"price:{exchange_name.lower()}:{symbol}"
                self.redis_client.setex(redis_key, 60, json.dumps(price_data))

            return price_data
        except Exception as e:
            logger.error(f"Error collecting price {symbol} from {exchange_name}: {e}")
            return None

    def save_candles_to_db(self, exchange: str, symbol: str, timeframe: str, candles: list):
        """Save candles to PostgreSQL."""
        try:
            db = SessionLocal()

            for candle in candles:
                # Check if already exists
                existing = db.query(Candle).filter(
                    Candle.exchange == exchange,
                    Candle.symbol == symbol,
                    Candle.timeframe == timeframe,
                    Candle.timestamp == candle['timestamp']
                ).first()

                if not existing:
                    db_candle = Candle(
                        exchange=exchange,
                        symbol=symbol,
                        timeframe=timeframe,
                        timestamp=candle['timestamp'],
                        open=candle['open'],
                        high=candle['high'],
                        low=candle['low'],
                        close=candle['close'],
                        volume=candle['volume']
                    )
                    db.add(db_candle)

            db.commit()
            logger.info(f"Saved {len(candles)} candles for {symbol} to database")
            db.close()
        except Exception as e:
            logger.error(f"Error saving candles to database: {e}")

    async def start_collection_loop(self, symbols: list = None, timeframe: str = '1m', interval: int = 20):
        """Start continuous price collection."""
        if symbols is None:
            symbols = ['BTC/USDT', 'ETH/USDT']

        logger.info(f"Starting price collection for {symbols}")

        while True:
            try:
                for symbol in symbols:
                    # Collect from Binance
                    await self.collect_price_snapshot('binance', symbol)

                    # Collect from Toobit if available
                    if self.exchanges['toobit']:
                        await self.collect_price_snapshot('toobit', symbol)

                logger.info(f"Price snapshot collected at {datetime.utcnow()}")

                # Wait before next collection
                await asyncio.sleep(interval)

            except Exception as e:
                logger.error(f"Error in collection loop: {e}")
                await asyncio.sleep(5)

    async def load_historical_data(self, symbols: list = None):
        """Load historical data from exchange."""
        if symbols is None:
            symbols = ['BTC/USDT', 'ETH/USDT']

        logger.info(f"Loading historical data for {symbols}")

        for symbol in symbols:
            for exchange_name in ['binance']:  # Start with Binance
                candles = await self.fetch_candles(exchange_name, symbol, '1m', limit=1000)
                if candles:
                    self.save_candles_to_db(exchange_name, symbol, '1m', candles)
                    logger.info(f"Loaded {len(candles)} candles for {symbol} from {exchange_name}")


async def main():
    """Test the price collector."""
    collector = PriceCollector()

    # Load historical data
    await collector.load_historical_data(['BTC/USDT'])

    # Start collection loop
    await collector.start_collection_loop(['BTC/USDT'], interval=20)


if __name__ == '__main__':
    asyncio.run(main())
