@echo off
rem Runs the shell script of the same name through Git for Windows' bash, so PowerShell and cmd can call bin\<name>.
setlocal
set "GITBASH="
for /f "delims=" %%G in ('where git.exe 2^>nul') do if not defined GITBASH if exist "%%~dpG..\bin\bash.exe" set "GITBASH=%%~dpG..\bin\bash.exe"
if not defined GITBASH set "GITBASH=%ProgramFiles%\Git\bin\bash.exe"
if not exist "%GITBASH%" (
  echo Git for Windows is missing: %~n0 needs its bash.exe 1>&2
  exit /b 127
)
set "SCRIPT=%~dpn0"
"%GITBASH%" "%SCRIPT:\=/%" %*
exit /b %ERRORLEVEL%
