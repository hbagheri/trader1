import plotly.graph_objects as go
from plotly.subplots import make_subplots
from typing import List, Dict, Optional
import logging

logger = logging.getLogger(__name__)


def create_candlestick_chart(
    ohlcv_data: List[Dict],
    sma_data: Optional[Dict] = None,
    rsi_data: Optional[Dict] = None,
    signal_data: Optional[List[Dict]] = None,
    title: str = "Trading Chart"
) -> go.Figure:
    """Create interactive candlestick chart with indicators.

    Args:
        ohlcv_data: List of OHLCV candles
        sma_data: Indicator data (works for any strategy type)
        rsi_data: Deprecated, use sma_data instead
        signal_data: Buy/Sell signals
        title: Chart title

    Returns:
        Plotly Figure object
    """

    # Use sma_data for all indicator types (generic parameter)
    indicator_data = sma_data or rsi_data

    fig = make_subplots(
        rows=2, cols=1,
        row_heights=[0.7, 0.3],
        specs=[[{"secondary_y": False}], [{"secondary_y": False}]],
        subplot_titles=(title, "Indicators")
    )

    # Candlestick
    timestamps = [d['timestamp'] for d in ohlcv_data]
    opens = [d['open'] for d in ohlcv_data]
    highs = [d['high'] for d in ohlcv_data]
    lows = [d['low'] for d in ohlcv_data]
    closes = [d['close'] for d in ohlcv_data]

    fig.add_trace(
        go.Candlestick(
            x=timestamps,
            open=opens,
            high=highs,
            low=lows,
            close=closes,
            name="OHLC",
        ),
        row=1, col=1
    )

    # SMA lines
    if indicator_data and 'smas' in indicator_data:
        smas = indicator_data['smas']
        if smas:
            fast_smas = [s['fast'] for s in smas if s['fast']]
            slow_smas = [s['slow'] for s in smas if s['slow']]
            sma_timestamps = [s['timestamp'] for s in smas if s['fast']]

            if fast_smas:
                fig.add_trace(
                    go.Scatter(
                        x=sma_timestamps,
                        y=fast_smas,
                        name=f"SMA({indicator_data['config'].get('fast_period', 50)})",
                        line=dict(color='orange', width=2),
                    ),
                    row=1, col=1
                )

            if slow_smas:
                fig.add_trace(
                    go.Scatter(
                        x=sma_timestamps,
                        y=slow_smas,
                        name=f"SMA({indicator_data['config'].get('slow_period', 200)})",
                        line=dict(color='blue', width=2),
                    ),
                    row=1, col=1
                )

    # RSI indicator
    if indicator_data and 'rsis' in indicator_data:
        rsis = indicator_data['rsis']
        if rsis:
            rsi_values = [r['rsi'] for r in rsis if r['rsi']]
            rsi_timestamps = [r['timestamp'] for r in rsis if r['rsi']]

            fig.add_trace(
                go.Scatter(
                    x=rsi_timestamps,
                    y=rsi_values,
                    name="RSI",
                    line=dict(color='purple', width=2),
                ),
                row=2, col=1
            )

            # RSI zones
            fig.add_hline(y=70, line_dash="dash", line_color="red", row=2, col=1)
            fig.add_hline(y=30, line_dash="dash", line_color="green", row=2, col=1)

    # Trading signals
    if signal_data:
        buy_signals = [s for s in signal_data if s.get('signal') == 'BUY']
        sell_signals = [s for s in signal_data if s.get('signal') == 'SELL']

        if buy_signals:
            fig.add_trace(
                go.Scatter(
                    x=[s['timestamp'] for s in buy_signals],
                    y=[s['price'] for s in buy_signals],
                    mode='markers',
                    name="BUY",
                    marker=dict(color='green', size=10, symbol='triangle-up'),
                ),
                row=1, col=1
            )

        if sell_signals:
            fig.add_trace(
                go.Scatter(
                    x=[s['timestamp'] for s in sell_signals],
                    y=[s['price'] for s in sell_signals],
                    mode='markers',
                    name="SELL",
                    marker=dict(color='red', size=10, symbol='triangle-down'),
                ),
                row=1, col=1
            )

    fig.update_xaxes(title_text="Time", row=2, col=1)
    fig.update_yaxes(title_text="Price", row=1, col=1)
    fig.update_yaxes(title_text="RSI", row=2, col=1)

    fig.update_layout(
        xaxis_rangeslider_visible=False,
        height=800,
        template="plotly_dark",
        hovermode='x unified',
    )

    return fig
