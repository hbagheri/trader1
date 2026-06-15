import asyncio
import logging
from typing import Dict, Optional

logger = logging.getLogger(__name__)


class OrderManager:
    def __init__(self, exchange, grid_engine, symbol: str):
        self.exchange = exchange
        self.grid_engine = grid_engine
        self.symbol = symbol
        self.orders = {}
        self.order_fill_events = {}

    async def initialize_grid(self, total_investment: float):
        """Place initial buy orders at all grid levels below current price."""
        current_price = await self.exchange.get_price(self.symbol)
        logger.info(f"Initializing grid. Current price: {current_price}")

        grid_levels = self.grid_engine.grid_levels
        buy_levels = [p for p in grid_levels if p < current_price]

        if not buy_levels:
            logger.warning("No grid levels below current price")
            return

        per_order = total_investment / len(buy_levels)
        amount_per_order = per_order / (sum(buy_levels) / len(buy_levels))

        for price in buy_levels:
            try:
                amount = per_order / price
                order = await self.exchange.place_limit_order(self.symbol, 'BUY', amount, price)
                order_id = order['id']
                self.orders[order_id] = order
                self.grid_engine.register_order(price, 'BUY', order_id)
                logger.info(f"Placed BUY order: {amount:.6f} @ {price} (ID: {order_id})")
            except Exception as e:
                logger.error(f"Failed to place buy order at {price}: {e}")

    async def on_order_filled(self, order_id: str, price: float, side: str, amount: float):
        """React to order fill: buy filled → place sell one level up, etc."""
        logger.info(f"Order filled: {side} {amount:.6f} @ {price} (ID: {order_id})")

        self.grid_engine.unregister_order(price, side)

        if side == 'BUY':
            next_level = self._get_next_level(price, 'UP')
            if next_level:
                try:
                    sell_order = await self.exchange.place_limit_order(self.symbol, 'SELL', amount, next_level)
                    sell_id = sell_order['id']
                    self.orders[sell_id] = sell_order
                    self.grid_engine.register_order(next_level, 'SELL', sell_id)
                    logger.info(f"Placed SELL order: {amount:.6f} @ {next_level} (ID: {sell_id})")
                except Exception as e:
                    logger.error(f"Failed to place sell order at {next_level}: {e}")
        else:
            profit = (price - self._get_prev_level(price, 'DOWN')) * amount
            self.grid_engine.on_cycle_completed(profit)

            next_level = self._get_next_level(price, 'DOWN')
            if next_level:
                try:
                    buy_order = await self.exchange.place_limit_order(self.symbol, 'BUY', amount, next_level)
                    buy_id = buy_order['id']
                    self.orders[buy_id] = buy_order
                    self.grid_engine.register_order(next_level, 'BUY', buy_id)
                    logger.info(f"Placed BUY order: {amount:.6f} @ {next_level} (ID: {buy_id})")
                except Exception as e:
                    logger.error(f"Failed to place buy order at {next_level}: {e}")

    async def cancel_all_orders(self):
        """Graceful shutdown: cancel all open orders."""
        logger.info("Cancelling all open orders...")
        try:
            await self.exchange.cancel_all_orders(self.symbol)
            self.orders.clear()
            self.grid_engine.open_buy_orders.clear()
            self.grid_engine.open_sell_orders.clear()
            logger.info("All orders cancelled")
        except Exception as e:
            logger.error(f"Error cancelling orders: {e}")

    def _get_next_level(self, current_price: float, direction: str) -> Optional[float]:
        levels = sorted(self.grid_engine.grid_levels)
        try:
            idx = levels.index(min(levels, key=lambda x: abs(x - current_price)))
            if direction == 'UP':
                return levels[idx + 1] if idx + 1 < len(levels) else None
            else:
                return levels[idx - 1] if idx - 1 >= 0 else None
        except (ValueError, IndexError):
            return None

    def _get_prev_level(self, current_price: float, direction: str) -> Optional[float]:
        return self._get_next_level(current_price, direction)

    async def get_order_status(self, order_id: str) -> Optional[str]:
        if order_id not in self.orders:
            return None

        try:
            order = await self.exchange.fetch_order(order_id, self.symbol)
            return order.get('status')
        except Exception as e:
            logger.error(f"Error fetching order {order_id}: {e}")
            return None
