import logging
from typing import List, Tuple

logger = logging.getLogger(__name__)


class GridEngine:
    def __init__(self, upper: float, lower: float, num_grids: int, spacing: str = 'arithmetic'):
        self.upper = upper
        self.lower = lower
        self.num_grids = num_grids
        self.spacing = spacing

        self.grid_levels = self.calculate_grid_levels()
        self.completed_cycles = 0
        self.total_profit = 0.0
        self.open_buy_orders = {}
        self.open_sell_orders = {}

    def calculate_grid_levels(self) -> List[float]:
        if self.spacing == 'arithmetic':
            return self._arithmetic_spacing()
        elif self.spacing == 'geometric':
            return self._geometric_spacing()
        else:
            raise ValueError(f"Unknown spacing type: {self.spacing}")

    def _arithmetic_spacing(self) -> List[float]:
        step = (self.upper - self.lower) / (self.num_grids - 1)
        return [self.lower + i * step for i in range(self.num_grids)]

    def _geometric_spacing(self) -> List[float]:
        ratio = (self.upper / self.lower) ** (1 / (self.num_grids - 1))
        return [self.lower * (ratio ** i) for i in range(self.num_grids)]

    def calculate_profit_per_grid(self, grid_index: int) -> float:
        if grid_index >= len(self.grid_levels) - 1:
            return 0.0

        buy_price = self.grid_levels[grid_index]
        sell_price = self.grid_levels[grid_index + 1]
        return ((sell_price - buy_price) / buy_price) * 100

    def get_grid_spacing(self) -> float:
        if len(self.grid_levels) < 2:
            return 0.0
        return self.grid_levels[1] - self.grid_levels[0]

    def register_order(self, price: float, side: str, order_id: str):
        if side == 'BUY':
            self.open_buy_orders[price] = order_id
        else:
            self.open_sell_orders[price] = order_id

        logger.info(f"Registered {side} order at {price}: {order_id}")

    def unregister_order(self, price: float, side: str) -> str:
        if side == 'BUY':
            return self.open_buy_orders.pop(price, None)
        else:
            return self.open_sell_orders.pop(price, None)

    def on_cycle_completed(self, profit: float):
        self.completed_cycles += 1
        self.total_profit += profit
        logger.info(f"Cycle #{self.completed_cycles} completed. Profit: {profit:.2f}. Total: {self.total_profit:.2f}")

    def get_summary(self) -> str:
        return (
            f"Grids: {self.num_grids} ({self.spacing}) | "
            f"Range: {self.lower} - {self.upper} | "
            f"Completed: {self.completed_cycles} | "
            f"Profit: ${self.total_profit:.2f} | "
            f"Open Buy Orders: {len(self.open_buy_orders)} | "
            f"Open Sell Orders: {len(self.open_sell_orders)}"
        )
