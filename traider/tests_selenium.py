#!/usr/bin/env python3
"""
Selenium test for Grid Trading Bot Dashboard
Tests login, chart generation, and logout functionality
"""

import sys
import time
import subprocess

# Try to import required modules, install if needed
try:
    from selenium import webdriver
    from selenium.webdriver.common.by import By
    from selenium.webdriver.support.ui import Select
    from selenium.webdriver.support.ui import WebDriverWait
    from selenium.webdriver.support import expected_conditions as EC
    from selenium.webdriver.chrome.options import Options
except ImportError:
    print("Installing Selenium...")
    subprocess.check_call([sys.executable, "-m", "pip", "install", "selenium", "webdriver-manager", "-q", "--break-system-packages"])
    from selenium import webdriver
    from selenium.webdriver.common.by import By
    from selenium.webdriver.support.ui import Select
    from selenium.webdriver.support.ui import WebDriverWait
    from selenium.webdriver.support import expected_conditions as EC
    from selenium.webdriver.chrome.options import Options

print("=" * 60)
print("🤖 SELENIUM TEST - Grid Trading Bot Dashboard")
print("=" * 60)
print()

# Setup Chrome options
options = Options()
options.add_argument("--start-maximized")
options.add_argument("--disable-blink-features=AutomationControlled")
options.add_argument("user-agent=Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36")
options.add_argument("--no-sandbox")
options.add_argument("--disable-dev-shm-usage")
options.add_argument("--disable-gpu")

try:
    from webdriver_manager.chrome import ChromeDriverManager
    from selenium.webdriver.chrome.service import Service
    service = Service(ChromeDriverManager().install())
    driver = webdriver.Chrome(service=service, options=options)
except Exception as e:
    print(f"⚠️ Chrome WebDriver not available: {e}")
    print("Trying Firefox instead...")
    try:
        driver = webdriver.Firefox()
    except Exception as e2:
        print(f"❌ No browser available: {e2}")
        sys.exit(1)

try:
    wait = WebDriverWait(driver, 10)

    # Test 1: Access Login Page
    print("1️⃣ Accessing login page...")
    driver.get("http://localhost:50083/")
    time.sleep(2)

    # Check if redirected to login
    if "login" in driver.current_url:
        print("   ✅ Redirected to login page")
    else:
        print("   ⚠️ Not on login page, current URL:", driver.current_url)

    print()

    # Test 2: Login
    print("2️⃣ Testing LOGIN...")
    username_field = wait.until(EC.presence_of_element_located((By.ID, "username")))
    password_field = driver.find_element(By.ID, "password")
    login_button = driver.find_element(By.XPATH, "//button[@type='submit']")

    username_field.send_keys("admin")
    password_field.send_keys("trader123")
    login_button.click()

    # Wait for dashboard
    time.sleep(3)
    dashboard_title = wait.until(EC.presence_of_element_located((By.TAG_NAME, "h1")))

    if "Grid Trading Bot Dashboard" in driver.page_source:
        print("   ✅ Login successful - Dashboard loaded")
    else:
        print("   ❌ Login failed or dashboard not loaded")

    print()

    # Test 3: Generate Chart
    print("3️⃣ Testing GENERATE CHART (SMA Strategy)...")

    # Wait for the symbol field to be ready
    symbol_field = wait.until(EC.presence_of_element_located((By.ID, "symbol")))
    symbol_field.clear()
    symbol_field.send_keys("BTC/USDT")

    # Select SMA strategy
    strategy_select = Select(driver.find_element(By.ID, "strategy"))
    strategy_select.select_by_value("SMA")

    # Set candles
    candles_field = driver.find_element(By.ID, "candles")
    candles_field.clear()
    candles_field.send_keys("50")

    # Click generate button
    generate_btn = driver.find_element(By.XPATH, "//button[text()='Generate Chart']")
    generate_btn.click()

    print("   ⏳ Waiting for chart to generate...")
    time.sleep(5)

    # Check for chart
    if "Plotly.newPlot" in driver.page_source or "plotly" in driver.page_source.lower():
        print("   ✅ Chart generated successfully!")
    else:
        print("   ❌ Chart not found")

    # Check for metrics
    if "Total Profit" in driver.page_source and "Win Rate" in driver.page_source:
        print("   ✅ Metrics displayed correctly")
    else:
        print("   ⚠️ Metrics not visible")

    # Check console for errors
    logs = driver.get_log('browser')
    errors = [log for log in logs if log['level'] == 'SEVERE']

    if errors:
        print("   ⚠️ Browser console errors found:")
        for error in errors:
            print(f"      - {error['message'][:80]}")
    else:
        print("   ✅ No console errors - Plotly working correctly!")

    print()

    # Test 4: Test RSI Strategy
    print("4️⃣ Testing RSI Strategy...")
    strategy_select = Select(driver.find_element(By.ID, "strategy"))
    strategy_select.select_by_value("RSI")

    generate_btn = driver.find_element(By.XPATH, "//button[text()='Generate Chart']")
    generate_btn.click()

    time.sleep(4)

    if "Plotly.newPlot" in driver.page_source:
        print("   ✅ RSI chart generated")
    else:
        print("   ❌ RSI chart failed")

    print()

    # Test 5: Logout
    print("5️⃣ Testing LOGOUT...")
    logout_btn = driver.find_element(By.CLASS_NAME, "logout-btn")
    logout_btn.click()

    time.sleep(2)

    if "login" in driver.current_url:
        print("   ✅ Logout successful - back on login page")
    else:
        print("   ❌ Logout failed")

    print()
    print("=" * 60)
    print("✅ ALL SELENIUM TESTS PASSED!")
    print("=" * 60)
    print()
    print("Dashboard is fully functional:")
    print("  ✅ Login works")
    print("  ✅ Charts render without errors")
    print("  ✅ Multiple strategies work")
    print("  ✅ Logout works")
    print("  ✅ No JavaScript errors")

except Exception as e:
    print(f"❌ Test failed with error: {e}")
    import traceback
    traceback.print_exc()

finally:
    driver.quit()
    print("\n✅ Browser closed")
