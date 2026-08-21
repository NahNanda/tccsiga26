@echo off
REM ============================================================
REM  Iniciar Extrator PDF/Excel - Servidor Local (Windows)
REM  Nao feche esta janela enquanto estiver usando o sistema.
REM ============================================================
chcp 65001 >nul
title Extrator PDF/Excel - Servidor Local

set PHP_DIR=%~dp0php
set PHP_EXE=%PHP_DIR%\php.exe

if not exist "%PHP_EXE%" (
    echo.
    echo [ERRO] Nao encontrei o PHP portatil em:
    echo    %PHP_DIR%
    echo.
    echo Siga o passo a passo do arquivo LEIA-ME.txt para baixar
    echo e colocar a pasta "php" ao lado deste arquivo .bat
    echo.
    pause
    exit /b 1
)

echo.
echo ============================================
echo   Iniciando servidor local... aguarde.
echo ============================================
echo.
echo Endereco do sistema: http://localhost:8000
echo Para ENCERRAR o sistema, feche esta janela ou pressione CTRL+C.
echo.

start "" http://localhost:8000
"%PHP_EXE%" -S localhost:8000 -t "%~dp0."

pause
