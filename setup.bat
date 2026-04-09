@echo off
echo ============================================
echo FlowStone - Complete Setup Script
echo ============================================
echo.

echo Step 1: Setting up database...
echo.
echo Please visit: http://localhost:8000/setup_database.php
echo in your browser to complete database setup.
echo.
echo Press any key once database setup is complete...
pause > nul

echo.
echo Step 2: Testing backend connection...
curl -s http://localhost:8000/dashboard.php > nul
if %ERRORLEVEL% EQU 0 (
    echo ✓ Backend is running on port 8000
) else (
    echo ✗ Backend not responding. Make sure PHP server is running:
    echo   cd backend
    echo   php -S localhost:8000
    echo.
    pause
    exit
)

echo.
echo ============================================
echo Setup Complete!
echo ============================================
echo.
echo You can now use FlowStone with live data:
echo.
echo 1. Backend: http://localhost:8000
echo 2. Frontend: Run 'npm run dev' or 'bun dev'
echo.
echo Login credentials:
echo   Email: admin@flowstone.com
echo   Password: password123
echo.
echo ============================================
echo.
pause
