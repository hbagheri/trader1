import logging

logger = logging.getLogger(__name__)


class RiskManager:
    def __init__(self, lower_price: float, upper_price: float, stop_loss_pct: float, max_orders: int):
        self.lower_price = lower_price
        self.upper_price = upper_price
        self.stop_loss_pct = stop_loss_pct
        self.max_orders = max_orders
        self.stop_loss_triggered = False

    def check_stop_loss(self, current_price: float) -> bool:
        """Check if price breaches stop loss threshold."""
        threshold = self.lower_price * (1 - self.stop_loss_pct / 100)

        if current_price < threshold:
            if not self.stop_loss_triggered:
                logger.warning(f"STOP LOSS TRIGGERED: {current_price} < {threshold}")
                self.stop_loss_triggered = True
                return True

        elif self.stop_loss_triggered and current_price >= self.lower_price:
            logger.info(f"Stop loss condition cleared. Price recovered to {current_price}")
            self.stop_loss_triggered = False

        return self.stop_loss_triggered

    def check_max_orders(self, current_order_count: int) -> bool:
        """Check if max open orders limit exceeded."""
        if current_order_count > self.max_orders:
            logger.warning(f"Max orders exceeded: {current_order_count} > {self.max_orders}")
            return True
        return False

    def check_price_out_of_bounds(self, current_price: float) -> bool:
        """Check if price has moved outside configured grid bounds."""
        if current_price > self.upper_price:
            logger.warning(f"Price above upper bound: {current_price} > {self.upper_price}")
            return True
        if current_price < self.lower_price:
            logger.warning(f"Price below lower bound: {current_price} < {self.lower_price}")
            return True
        return False

    async def handle_stop_loss(self, order_manager):
        """Cancel all orders and prepare for shutdown on stop loss."""
        logger.critical("Handling stop loss event...")
        await order_manager.cancel_all_orders()
