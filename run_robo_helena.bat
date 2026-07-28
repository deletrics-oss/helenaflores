@echo off
chcp 65001 > nul
title HELENA FLORES - Robô Extrator Automático v1.0
color 0D

echo.
echo ==========================================
echo   HELENA FLORES — Robô Extrator Automático
echo   Extrator de Produtos e Fotos do WhatsApp
echo ==========================================
echo.

echo [1/4] Verificando ambiente Node.js...
where node >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo [ERRO] Node.js não foi encontrado no seu computador!
    echo Por favor, baixe e instale o Node.js em: https://nodejs.org
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('node -v') do echo [OK] Node.js detectado: %%i

echo.
echo [2/4] Entrando no diretório do robô...
cd /d "%~dp0robot_playwright"

echo.
echo [3/4] Instalando dependências do Playwright...
call npm install --silent 2>nul
call npx playwright install chromium 2>nul
echo [OK] Dependências e Navegador Automático instalados.

echo.
echo [4/4] Iniciando o Robô Automático Helena Flores...
echo.
node scraper.js

echo.
echo ==========================================
echo   Execução concluída!
echo ==========================================
pause
