"""Database models for trading bot."""
from datetime import datetime
from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, Boolean
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
import os

DATABASE_URL = os.getenv('DATABASE_URL', 'postgresql://traider:traider_password@localhost:5432/traider')

engine = create_engine(DATABASE_URL, echo=False)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()


class Candle(Base):
    """OHLCV candlestick data."""
    __tablename__ = "candles"

    id = Column(Integer, primary_key=True, index=True)
    exchange = Column(String(50), index=True)  # binance, toobit, etc
    symbol = Column(String(20), index=True)  # BTC/USDT, ETH/USDT
    timeframe = Column(String(10), index=True)  # 1m, 5m, 1h
    timestamp = Column(Integer, index=True)  # Unix timestamp
    open = Column(Float)
    high = Column(Float)
    low = Column(Float)
    close = Column(Float)
    volume = Column(Float)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    class Config:
        orm_mode = True


class Exchange(Base):
    """Exchange configuration and credentials."""
    __tablename__ = "exchanges"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(50), unique=True, index=True)  # binance, toobit
    api_key = Column(String(500))
    api_secret = Column(String(500))
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)


class Portfolio(Base):
    """Portfolio/account information."""
    __tablename__ = "portfolios"

    id = Column(Integer, primary_key=True, index=True)
    exchange_id = Column(Integer)
    symbol = Column(String(20), index=True)
    balance = Column(Float)  # Current balance
    total_invested = Column(Float, default=0)  # Total invested amount
    total_profit = Column(Float, default=0)  # Total profit/loss
    last_updated = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    created_at = Column(DateTime, default=datetime.utcnow)


class Trade(Base):
    """Executed trades."""
    __tablename__ = "trades"

    id = Column(Integer, primary_key=True, index=True)
    exchange = Column(String(50), index=True)
    symbol = Column(String(20), index=True)
    trade_type = Column(String(10))  # BUY, SELL
    price = Column(Float)
    quantity = Column(Float)
    commission = Column(Float, default=0)
    timestamp = Column(Integer, index=True)
    strategy = Column(String(50))  # SMA, RSI, COMBO, GRID
    profit_loss = Column(Float, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)


def init_db():
    """Initialize database tables."""
    Base.metadata.create_all(bind=engine)
    print("Database tables created successfully!")


def get_db():
    """Get database session."""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
