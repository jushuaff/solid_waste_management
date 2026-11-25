# Windows PHP 8.4 XAMPP CLI setup — Laravel 13 and MongoDB

This guide is for PHP 8.4 x64 ZTS at C:\xampp\php\php.exe.

## Confirm the CLI environment

```powershell
php -v
where.exe php
php --ini
Get-ChildItem C:\xampp\php\php.ini*
php -m | findstr /I "openssl mbstring fileinfo mongodb"
```

If `Loaded Configuration File: (none)` appears, PHP has no active php.ini. Composer cannot securely download dependencies without OpenSSL.

## Configure the PHP 8.4 php.ini

Use the PHP 8.4 template, **not** an older PHP 8.2 php.ini copied wholesale:

```powershell
if (-not (Test-Path "C:\xampp\php\php.ini")) {
  Copy-Item "C:\xampp\php\php.ini-development" "C:\xampp\php\php.ini"
}
notepad "C:\xampp\php\php.ini"
```

Enable only extensions actually available for the installed PHP build:

```ini
extension_dir = "C:\xampp\php\ext"
extension=openssl
extension=mbstring
extension=fileinfo
extension=mongodb
date.timezone = Asia/Manila
```

**Never copy PHP 8.2 DLLs into PHP 8.4.** Some extensions may already be compiled into PHP; avoid duplicate extension lines. Before enabling MongoDB, install a PHP 8.4 x64 ZTS-compatible MongoDB PHP driver DLL (from https://pecl.php.net/package/mongodb) in C:\xampp\php\ext.

If PHP still reports no INI:

```powershell
php -c "C:\xampp\php\php.ini" --ini
php -c "C:\xampp\php\php.ini" -m
$env:PHPRC="C:\xampp\php"
php --ini
```

## Install and test

```powershell
php -m | findstr /I "openssl mbstring fileinfo mongodb"
composer diagnose
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan route:list
php artisan test
php artisan serve
```

Only use real .env SMTP credentials locally; do not commit them. If Composer fails, vendor/autoload.php is absent, so artisan commands cannot start.

Do **not** disable TLS or use `--ignore-platform-reqs`.

## Safe comparison of PHP 8.2 and PHP 8.4 INI files

Keep the PHP 8.4 template as the base. Transfer settings selectively (timezone, upload limits, local mail). Confirm extension_dir, zend_extension, openssl.cafile, curl.cainfo, and every extension DLL refer to PHP 8.4-compatible files. Preserve backups.