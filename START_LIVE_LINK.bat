@echo off
title QARTIV - Public Live Link Tunnel
color 0b
cls
echo ========================================================
echo          QARTIV SMART COMMERCE - LIVE LINK LAUNCHER
echo ========================================================
echo.
echo [*] Checking WAMP Apache and MySQL...
echo [*] Starting Cloudflare Live Tunnel for http://localhost:80 ...
echo.
echo ========================================================
echo   COPY AND SHARE THE HTTPS LINK PRINTED BELOW:
echo   (Open it on any Phone, Tablet, or PC worldwide)
echo ========================================================
echo.
"%~dp0cloudflared.exe" tunnel --url http://localhost:80
pause
