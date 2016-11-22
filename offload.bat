@echo off
cls
set inifile=config.ini
set backupgroup=0
php offload.php "%inifile%" "%backupgroup%"