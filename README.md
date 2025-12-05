<img src="https://www.ixaya.com/assets/frontend/default/images/logo_ixaya.png">

# Ixaya / Manager 

HMVC Code Igniter based Framework for creating backends and complete websites

## About this package

**Ixaya Manager** is a set of files, libraries, and modules that allows you to use Code Igniter to build a Backend with Login or a Complete Website if you prefer.

### Features
* CodeIgniter upgradeable through Composer (always use latest version)
* Run the project (a webserver) using a shell script (no need to install Apache or Nginx during development (`http://localhost:8000`)
* HMVC
* Diferent folders for diferent modules: `modules/admin`, `modules/frontned`, etc.
* Support for MySQL, PostgreSQL, MSSQL, Sqlite, or any database that is supported in CodeIgniter 3.
* Different Database connection/technology per Model. (you can have a model that loads a Database from Postgres and another Model that loads a Database from MySQL.
* Responsive Theme (SB Admin 2 Template for the Backend)
* Login protected Admin module
* Examples to create a REST API
* Examples to send Native Apple Push Notifications or use Firebase for Android
* Production Tested
* try { } catch { } login for errors (an improvement over CodeIgniter's)
* Secured Application Folder from Public.


## How to Install

To Install **Manager** you need to  

### Step by Step guide on OSX
* Install Homebew `/usr/bin/ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"`
* Install Git `brew install git`
* Install PHP (5.4+) `brew install php54` or `brew install php72`
* Install Composer `brew install composer`
* Clone Repository `git clone https://github.com/Ixaya/Manager.git`
* Update packages using composer `composer install`
* Run Server `sh bin/server.sh`

### Step by Step guide on Windows
* Install Git `https://git-scm.com/download/win`
* Install PHP (5.4+) `https://windows.php.net/download/`
* Install Composer `https://getcomposer.org/download/`
* Clone Repository `git clone https://github.com/Ixaya/Manager.git`
* Update packages using composer `composer install`

## PHP Validations

Run static code analysis using PHPStan:

- **Standard analysis:**
  ```bash
  ./vendor/bin/phpstan analyse
  ```

- **With increased memory limit:**
  ```bash
  ./vendor/bin/phpstan analyse --memory-limit=512M
  ```

> **Tip:** Use the memory limit option if you encounter out-of-memory errors during analysis.


**## PHP Unit Testing**
Run unit tests using PHPUnit:
- **Run all tests:**
 ```bash
./vendor/bin/phpunit
 ```
- **Run specific test file:**
 ```bash
./vendor/bin/phpunit tests/Unit/ExampleTest.php
 ```
- **Run tests with verbose output:**
 ```bash
./vendor/bin/phpunit --verbose
 ```
- **Run tests in specific group/category:**
 ```bash
./vendor/bin/phpunit --group unit
 ```
> **Tip:** Use `--testdox` flag for readable test output, or `--stop-on-failure` to halt execution on the first failed test.

## MsgPack Support

This package can use MsgPack for faster cache and payload serialization. While the native PHP MsgPack extension (installed via `pecl` or system packages) offers the best performance, not all servers have it available.

### 1. Install the PHP MsgPack Fallback Library

Add the pure PHP implementation to your project:

```bash
composer require rybakit/msgpack
```

### 2. Enable the Composer Patches Plugin

If your project doesn't already have `composer-patches` installed:

```bash
composer require cweagans/composer-patches
```

### 3. Apply the PHP 8.1+ Compatibility Patch

Add the following configuration to your root `composer.json`:

```json
{
  ...
    "extra": {
        "patches": {
            "rybakit/msgpack": {
                "Fix PHP 8.1 chr() deprecation": "vendor/ixaya/manager/patches/msgpack-php81-fix.patch"
            }
        }
    }
  ...
}
```

### 4. Apply the Changes

Run the following command to install dependencies and apply patches:

```bash
composer install
```

## Application Structure

### Root Folder structure
We recomend you to create a folder named app and checkout the project inside.

* `composer.jsn`
* `application/`
* `public/`
* `bin/`

### Public Folder
* `public/`
* `public/media/` This is where you put all the files uploaded from your users
* `public/assets/{module}/js`
* `public/assets/{module}/css`
* `public/assets/{module}/images`
* `public/assets/{module}/videos`

### Application Folder
* `application/`
* `application/views/` Global Views
* `application/thid_party`
* `application/modules` Where all your modules go
* `application/models` Global Models
* `application/migrations`
* `application/libraries` Global Libraries
* `application/language` Global Language
* `application/hooks` Global Hooks
* `application/helpers` Global Helpers
* `application/database`
* `application/controllers` Global Controllers
* `application/config` Configuration of your App
* `application/cache`



### Modules Folder
Inside the modules folder you can have any folder that goes inside Application, like: Models, Views, Controllers

* `application/modules` 
* `application/modules/{module}/views`
* `application/modules/{module}/controllers`
* `application/modules/{module}/models` 
* `application/modules/{module}/libraries`
* `application/modules/{module}/language` 
* `application/modules/{module}/....` and more
