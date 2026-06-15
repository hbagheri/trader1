import asyncio
import logging
import os
from pathlib import Path
from flask import Flask, render_template, jsonify, request, session, redirect, url_for
from flask_cors import CORS
from flask_socketio import SocketIO, emit, join_room, leave_room
from functools import wraps
import json
import yaml
import redis
from sqlalchemy import desc
from sqlalchemy.orm import Session

from .charts import create_candlestick_chart
from backtest.engine import Backtester
from backtest.data import DataLoader
from models import SessionLocal, Candle, get_db

logger = logging.getLogger(__name__)


class DashboardApp:
    def __init__(self, debug: bool = True, config_path: str = None):
        template_dir = Path(__file__).parent / 'templates'
        self.flask_app = Flask(__name__, template_folder=str(template_dir))
        CORS(self.flask_app)

        # Initialize SocketIO for real-time updates
        self.socketio = SocketIO(self.flask_app, cors_allowed_origins="*", async_mode='threading')

        self.debug = debug
        self.data_loader = None
        self.config = {}
        self.auth_config = {}
        self.event_loop = asyncio.new_event_loop()
        asyncio.set_event_loop(self.event_loop)

        # Initialize Redis connection
        self.redis_client = self._init_redis()

        # Load config
        if config_path is None:
            config_path = Path(__file__).parent.parent / 'config.yaml'
        self._load_config(config_path)

        # Setup Flask app config
        self.flask_app.secret_key = self.auth_config.get('session_secret', 'dev-secret-key')

        self._setup_routes()
        self._setup_websocket_handlers()

    def _init_redis(self):
        """Initialize Redis connection."""
        try:
            redis_url = os.getenv('REDIS_URL', 'redis://:redis_password@localhost:6379/0')
            if redis_url.startswith('redis://'):
                url_parts = redis_url.replace('redis://', '').split('@')
                password = url_parts[0].split(':')[1] if ':' in url_parts[0] else None
                host_port = url_parts[1].split(':')
                host = host_port[0]
                port = int(host_port[1].split('/')[0])
                db = int(url_parts[1].split('/')[-1]) if '/' in url_parts[1] else 0

                return redis.Redis(host=host, port=port, db=db, password=password, decode_responses=True)
            else:
                return redis.from_url(redis_url, decode_responses=True)
        except Exception as e:
            logger.warning(f"Redis connection failed: {e}. Using mock data only.")
            return None

    def _load_config(self, config_path):
        """Load configuration from YAML."""
        try:
            if os.path.exists(config_path):
                with open(config_path, 'r') as f:
                    self.config = yaml.safe_load(f) or {}
                self.auth_config = self.config.get('auth', {})
        except Exception as e:
            logger.warning(f"Could not load config: {e}")
            self.auth_config = {}

    def _require_login(self, f):
        """Decorator to require login for protected routes."""
        @wraps(f)
        def decorated_function(*args, **kwargs):
            if not self.auth_config.get('enabled', False):
                return f(*args, **kwargs)

            if 'user' not in session:
                return redirect(url_for('login_page'))

            return f(*args, **kwargs)
        return decorated_function

    def _setup_websocket_handlers(self):
        """Setup WebSocket event handlers."""
        @self.socketio.on('connect')
        def handle_connect():
            logger.info(f"Client connected: {request.sid}")
            emit('response', {'data': 'Connected to server'})

        @self.socketio.on('disconnect')
        def handle_disconnect():
            logger.info(f"Client disconnected: {request.sid}")

        @self.socketio.on('subscribe_price')
        def handle_subscribe_price(data):
            """Subscribe to real-time price updates."""
            exchange = data.get('exchange', 'binance')
            symbol = data.get('symbol', 'BTC/USDT')

            room = f"price:{exchange}:{symbol}"
            join_room(room)

            # Send current price from Redis
            if self.redis_client:
                try:
                    redis_key = f"price:{exchange}:{symbol}"
                    price_data = self.redis_client.get(redis_key)
                    if price_data:
                        emit('price_update', json.loads(price_data))
                except Exception as e:
                    logger.error(f"Error getting price from Redis: {e}")

            emit('subscribed', {'room': room, 'message': f'Subscribed to {symbol} on {exchange}'})

        @self.socketio.on('unsubscribe_price')
        def handle_unsubscribe_price(data):
            """Unsubscribe from price updates."""
            exchange = data.get('exchange', 'binance')
            symbol = data.get('symbol', 'BTC/USDT')

            room = f"price:{exchange}:{symbol}"
            leave_room(room)
            emit('unsubscribed', {'room': room})

    def _setup_routes(self):
        """Register Flask routes."""

        @self.flask_app.route('/login', methods=['GET'])
        def login_page():
            if session.get('user'):
                return redirect(url_for('index'))
            return render_template('login.html')

        @self.flask_app.route('/api/login', methods=['POST'])
        def login():
            """Handle login request."""
            if not self.auth_config.get('enabled', False):
                return jsonify({'error': 'Authentication not enabled'}), 403

            data = request.json
            username = data.get('username', '')
            password = data.get('password', '')

            if (username == self.auth_config.get('username') and
                password == self.auth_config.get('password')):
                session['user'] = username
                return jsonify({'success': True})
            else:
                return jsonify({'error': 'Invalid username or password'}), 401

        @self.flask_app.route('/api/logout', methods=['POST'])
        def logout():
            """Handle logout request."""
            session.pop('user', None)
            return jsonify({'success': True})

        @self.flask_app.route('/')
        @self._require_login
        def index():
            return render_template('index.html')

        @self.flask_app.route('/api/chart', methods=['POST'])
        @self._require_login
        def get_chart():
            """Generate chart for strategy backtest or load from DB."""
            try:
                data = request.json
                symbol = data.get('symbol', 'BTC/USDT')
                strategy_name = data.get('strategy', 'SMA')
                use_mock_data = data.get('mock_data', True)
                exchange = data.get('exchange', 'binance')

                from backtest.data import DataLoader
                from bot.strategies.sma import SMAStrategy
                from bot.strategies.rsi import RSIStrategy
                from bot.strategies.combo import ComboStrategy

                # Try to load real data from database first
                ohlcv = None
                if not use_mock_data:
                    ohlcv = self._load_candles_from_db(exchange, symbol, limit=100)

                # Fall back to mock data if DB load failed
                if ohlcv is None:
                    ohlcv = DataLoader().generate_mock_data(symbol, num_candles=100)

                # Dynamically load the correct strategy
                if strategy_name == 'SMA':
                    strategy = SMAStrategy()
                elif strategy_name == 'RSI':
                    strategy = RSIStrategy()
                elif strategy_name == 'COMBO':
                    sma = SMAStrategy()
                    rsi = RSIStrategy()
                    strategy = ComboStrategy([sma, rsi], name='Combo (SMA + RSI)')
                elif strategy_name == 'GRID':
                    strategy = SMAStrategy()  # Fallback to SMA for now
                else:
                    strategy = SMAStrategy()

                backtester = Backtester(strategy)

                # Run backtest using instance event loop
                result = self.event_loop.run_until_complete(backtester.run(ohlcv))
                indicator_data = self.event_loop.run_until_complete(strategy.get_indicator_data())

                # Create chart
                fig = create_candlestick_chart(
                    ohlcv,
                    sma_data=indicator_data,
                    signal_data=result.signal_history,
                    title=f"{symbol} - {strategy_name}"
                )

                # Convert to JSON format
                plot_data = json.loads(fig.to_json())

                chart_json = json.dumps({
                    'data': plot_data.get('data', []),
                    'layout': plot_data.get('layout', {}),
                    'config': {'responsive': True}
                })

                # Create chart HTML
                chart_html = f"""
                <div id="chart" style="width:100%;height:800px;"></div>
                <script>
                    if (window.Plotly) {{
                        var chartData = {chart_json};
                        chartData.config.edits = {{}};
                        chartData.config.staticPlot = false;
                        Plotly.newPlot('chart', chartData.data, chartData.layout, chartData.config);
                    }}
                </script>
                """

                return jsonify({
                    'chart': chart_html,
                    'metrics': {
                        'total_profit': result.total_profit,
                        'total_return': result.total_return,
                        'win_rate': result.win_rate,
                        'trades': len(result.trades),
                    },
                    'source': 'real' if not use_mock_data else 'mock'
                })

            except Exception as e:
                logger.error(f"Error generating chart: {e}", exc_info=True)
                return jsonify({'error': str(e)}), 500

        @self.flask_app.route('/api/candles', methods=['GET'])
        @self._require_login
        def get_candles():
            """Get candlestick data from database."""
            try:
                exchange = request.args.get('exchange', 'binance')
                symbol = request.args.get('symbol', 'BTC/USDT')
                limit = int(request.args.get('limit', 100))

                candles = self._load_candles_from_db(exchange, symbol, limit)

                if not candles:
                    return jsonify({'error': 'No data available'}), 404

                return jsonify({
                    'exchange': exchange,
                    'symbol': symbol,
                    'candles': candles
                })

            except Exception as e:
                logger.error(f"Error getting candles: {e}")
                return jsonify({'error': str(e)}), 500

        @self.flask_app.route('/api/price/current', methods=['GET'])
        @self._require_login
        def get_current_price():
            """Get current price from Redis."""
            try:
                exchange = request.args.get('exchange', 'binance')
                symbol = request.args.get('symbol', 'BTC/USDT')

                if not self.redis_client:
                    return jsonify({'error': 'Redis not available'}), 503

                redis_key = f"price:{exchange}:{symbol}"
                price_data = self.redis_client.get(redis_key)

                if not price_data:
                    return jsonify({'error': 'Price data not available'}), 404

                return jsonify(json.loads(price_data))

            except Exception as e:
                logger.error(f"Error getting price: {e}")
                return jsonify({'error': str(e)}), 500

    def _load_candles_from_db(self, exchange: str, symbol: str, limit: int = 100) -> list:
        """Load candlestick data from database."""
        try:
            db = SessionLocal()
            candles = db.query(Candle).filter(
                Candle.exchange == exchange,
                Candle.symbol == symbol,
                Candle.timeframe == '1m'
            ).order_by(desc(Candle.timestamp)).limit(limit).all()

            db.close()

            if not candles:
                return None

            # Convert to OHLCV format
            ohlcv = []
            for candle in reversed(candles):
                ohlcv.append({
                    'timestamp': candle.timestamp,
                    'open': candle.open,
                    'high': candle.high,
                    'low': candle.low,
                    'close': candle.close,
                    'volume': candle.volume
                })

            return ohlcv
        except Exception as e:
            logger.error(f"Error loading candles from DB: {e}")
            return None

    def run(self, host: str = '0.0.0.0', port: int = 5000, **kwargs):
        """Start the dashboard server with WebSocket support."""
        logger.info(f"Starting dashboard on {host}:{port}")
        self.socketio.run(self.flask_app, host=host, port=port, debug=self.debug, **kwargs)
