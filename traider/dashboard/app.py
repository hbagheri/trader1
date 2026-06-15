import asyncio
import logging
import os
from pathlib import Path
from flask import Flask, render_template, jsonify, request, session, redirect, url_for
from flask_cors import CORS
from functools import wraps
import json
import yaml

from .charts import create_candlestick_chart
from backtest.engine import Backtester
from backtest.data import DataLoader

logger = logging.getLogger(__name__)


class DashboardApp:
    def __init__(self, debug: bool = True, config_path: str = None):
        # Determine template folder path (works in Docker and local)
        template_dir = Path(__file__).parent / 'templates'
        self.flask_app = Flask(__name__, template_folder=str(template_dir))
        CORS(self.flask_app)
        self.debug = debug
        self.data_loader = None
        self.config = {}
        self.auth_config = {}
        self.event_loop = asyncio.new_event_loop()
        asyncio.set_event_loop(self.event_loop)

        # Load config
        if config_path is None:
            config_path = Path(__file__).parent.parent / 'config.yaml'
        self._load_config(config_path)

        # Setup Flask app config
        self.flask_app.secret_key = self.auth_config.get('session_secret', 'dev-secret-key')

        self._setup_routes()

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
            # If auth is disabled, allow access
            if not self.auth_config.get('enabled', False):
                return f(*args, **kwargs)

            # If user is not logged in, redirect to login
            if 'user' not in session:
                return redirect(url_for('login_page'))

            return f(*args, **kwargs)
        return decorated_function

    def _setup_routes(self):
        """Register Flask routes."""

        @self.flask_app.route('/login', methods=['GET'])
        def login_page():
            # If already logged in, redirect to dashboard
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

            # Check credentials
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
            """Generate chart for strategy backtest."""
            try:
                data = request.json
                symbol = data.get('symbol', 'BTC/USDT')
                strategy_name = data.get('strategy', 'SMA')
                use_mock_data = data.get('mock_data', True)

                from backtest.data import DataLoader
                from bot.strategies.sma import SMAStrategy
                from bot.strategies.rsi import RSIStrategy
                from bot.strategies.combo import ComboStrategy

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

                # Convert Plotly figure to dict format for direct JSON serialization
                # This avoids HTML parsing issues altogether
                plot_data = json.loads(fig.to_json())

                # Get the layout and data for JavaScript initialization
                chart_json = json.dumps({
                    'data': plot_data.get('data', []),
                    'layout': plot_data.get('layout', {}),
                    'config': {'responsive': True}
                })

                # Create chart HTML that will be initialized by JavaScript
                # Disable Plotly's editor mode to avoid "_guiEditing" error
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
                    }
                })

            except Exception as e:
                logger.error(f"Error generating chart: {e}", exc_info=True)
                return jsonify({'error': str(e)}), 500

    def run(self, host: str = '127.0.0.1', port: int = 5000):
        """Start the dashboard server."""
        logger.info(f"Starting dashboard on {host}:{port}")
        self.flask_app.run(host=host, port=port, debug=self.debug)
