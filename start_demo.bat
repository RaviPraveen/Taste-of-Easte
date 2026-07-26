@echo off
title Food Hotel POS - Client Demo
echo ============================================
echo  Food Hotel POS - Client Demo Launcher
echo ============================================
echo.
echo NOTE: MySQL must be running in XAMPP Control Panel first!
echo.
echo Starting local server on port 8080...
set PHP_CLI_SERVER_WORKERS=6
start "POS PHP Server" /min C:\xampp\php\php.exe -S 127.0.0.1:8080 -t C:\xampp\htdocs\food_hotel_pos
timeout /t 2 >nul
echo.
echo Starting public tunnel... your shareable link will appear below
echo (look for the https://....trycloudflare.com box)
echo.
echo Keep this window OPEN while the client is testing.
echo Close this window to end the demo.
echo.
"C:\Users\Praveen\cloudflared\cloudflared.exe" tunnel --url http://127.0.0.1:8080
