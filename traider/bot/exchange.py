import asyncio
import ccxt.async_support as ccxt
from typing import Optional, Dict, List
import logging

logger = logging.getLogger(__name__)


class ExchangeClient:
    def __init__(self, api_key: str = None, api_secret: str = None, paper_trading: bool = False):
        self.paper_trading = paper_trading
        self.api_key = api_key
        self.api_secret = api_secret
        self.exchange = None
        self.last_price = None

        if not paper_trading and (not api_key or not api_secret):
            raise ValueError("API key and secret required for live trading")

    async def init(self):
        if self.paper_trading:
            self.exchange = None
        else:
            self.exchange = ccxt.toobit({
                'apiKey': self.api_key,
                'secret': self.api_secret,
            })

    async def close(self):
        if self.exchange:
            await self.exchange.close()

    async def get_price(self, symbol: str) -> float:
        if self.paper_trading:
            return self.last_price or 65000.0

        ticker = await self.exchange.fetch_ticker(symbol)
        self.last_price = ticker['last']
        return ticker['last']

    async def get_balance(self, currency: str = None) -> Dict:
        if self.paper_trading:
            return {'free': 10000.0, 'used': 0.0, 'total': 10000.0}

        balance = await self.exchange.fetch_balance()
        if currency:
            return balance.get(currency, {'free': 0, 'used': 0, 'total': 0})
        return balance

    async def place_limit_order(self, symbol: str, side: str, amount: float, price: float) -> Dict:
        order_id = None

        if self.paper_trading:
            order_id = f"PAPER_{int(asyncio.get_event_loop().time() * 1000)}"
            return {
                'id': order_id,
                'symbol': symbol,
                'side': side,
                'amount': amount,
                'price': price,
                'status': 'open',
                'timestamp': None,
            }

        order = await self.exchange.create_limit_order(symbol, side, amount, price)
        return order

    async def cancel_order(self, order_id: str, symbol: str) -> Dict:
        if self.paper_trading:
            return {'id': order_id, 'status': 'canceled'}

        result = await self.exchange.cancel_order(order_id, symbol)
        return result

    async def cancel_all_orders(self, symbol: str) -> List[Dict]:
        if self.paper_trading:
            return []

        orders = await self.exchange.cancel_all_orders(symbol)
        return orders

    async def get_open_orders(self, symbol: str) -> List[Dict]:
        if self.paper_trading:
            return []

        orders = await self.exchange.fetch_open_orders(symbol)
        return orders

    async def fetch_order(self, order_id: str, symbol: str) -> Dict:
        if self.paper_trading:
            return {'id': order_id, 'status': 'open'}

        order = await self.exchange.fetch_order(order_id, symbol)
        return order
