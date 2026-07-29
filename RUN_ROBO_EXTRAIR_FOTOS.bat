@echo off
title HELENA FLORES - Extrator de Fotos
cls

cd /d "C:\Users\ILHA\Documents\GitHub\helenaflores\_catalogo referencia\robot_playwright"

echo ==========================================================
echo   HELENA FLORES - Robo Extrator do Whatsapp
echo ==========================================================
echo.
echo [1/3] Pasta do robo: %CD%

echo.
echo [2/3] Verificando e instalando o navegador Chromium...
call npx playwright install chromium

echo.
echo [3/3] Iniciando o robo de extracao de fotos...
echo.
node scraper.js

echo.
echo ==========================================================
echo   Processo Concluido! Pressione qualquer tecla para sair.
echo ==========================================================
pause
