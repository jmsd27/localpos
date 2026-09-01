@echo off
REM Supervisor simple del worker de cola en Windows, sin dependencias
REM adicionales (sin NSSM). Pensado para registrarse como Tarea Programada
REM "al iniciar sesion/equipo". Si el worker truena, el bucle lo reinicia
REM a los 5 segundos. Ver scripts\sync-vps\README.md.
REM
REM Si "php" no esta en el PATH del sistema, define PHP_BIN antes de llamar
REM este script (ej. set PHP_BIN=C:\laragon\bin\php\php-8.4.25...\php.exe)
REM o edita la linea de abajo directamente.

if "%PHP_BIN%"=="" set PHP_BIN=php

cd /d "%~dp0..\.."

:loop
"%PHP_BIN%" artisan queue:work --stop-when-empty --tries=3
timeout /t 5 /nobreak >nul
goto loop
