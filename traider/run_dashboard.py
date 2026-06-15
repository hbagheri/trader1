#!/usr/bin/env python3
"""
Launch the trading dashboard web server.

Usage:
    python run_dashboard.py

Then open: http://localhost:5000
"""

import logging
from bot.monitor import setup_logging
from dashboard.app import DashboardApp

if __name__ == '__main__':
    setup_logging('logs/dashboard.log')

    app = DashboardApp(debug=True, config_path='config.yaml')
    print("\n" + "=" * 60)
    print("🌐 Dashboard starting...")
    print("Open your browser: http://localhost:5000")
    print("Default credentials - Username: admin, Password: trader123")
    print("=" * 60 + "\n")

    app.run(host='0.0.0.0', port=5000)
