@echo off
chcp 65001 > nul
title HELENA FLORES - Extrator de Fotos do Catálogo v2.0
color 0D

echo.
echo ==========================================================
echo   HELENA FLORES — Robô Extrator Automático estilo Makerlist
echo   Extrai e Salva as Fotos 1 por 1 do WhatsApp Web
echo ==========================================================
echo.

echo [1/4] Verificando ambiente Node.js...
where node >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo [ERRO] Node.js não foi encontrado no seu computador!
    echo Baixe e instale o Node.js em: https://nodejs.org
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('node -v') do echo [OK] Node.js detectado: %%i

echo.
echo [2/4] Entrando no diretório do robô...
cd /d "%~dp0robot_playwright"

echo.
echo [3/4] Garantindo dependências e Navegador Automático...
if not exist "node_modules" (
    echo [INFO] Instalando bibliotecas do robô...
    call npm install
)
call npx playwright install chromium
echo [OK] Navegador e dependências verificados!

echo.
echo [4/4] Iniciando o Robô Extrator Helena Flores...
echo.
node scraper.js

echo.
echo ==========================================================
echo   Processo Concluído! Pressione qualquer tecla para fechar.
echo ==========================================================
pause
