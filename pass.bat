@echo off
rem Hides password when typing on windows
rem license GPLv3+ http://www.gnu.org/licenses/gpl.html
echo hP1X500P[PZBBBfh#b##fXf-V@`$fPf]f3/f1/5++u5x > "%temp%\in.com"
set /p=%1<nul
for /f "tokens=*" %%a in ('"%temp%\in.com"') do (
	set "InputPass=%%a"
)
del %temp%\in.com
echo %InputPass%
