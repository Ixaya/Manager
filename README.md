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
## Docker Setup

This project can be run using Docker with different configurations for development and production environments.

### Configuration Files

The project uses multiple Docker Compose files:

- `docker-compose.yml` - Base configuration (shared settings)
- `docker-compose.dev.yml` - Development overrides (code mounting, live changes)
- `docker-compose.prod.yml` - Production overrides (no volumes, optimized)

### Building and Running

#### Basic Setup (Base Configuration Only)

```bash
# Build
docker-compose build

# Start
docker-compose up -d

# Rebuild and start (if Dockerfile changed)
docker-compose up -d --build

# Stop
docker-compose down
```

#### Development Mode

Uses live code mounting - changes are reflected immediately without rebuilding.

```bash
# Start
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d app
```

#### Production Mode

Uses code copied into the image - requires rebuild for code changes.

```bash
# Start
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d app
```

### Useful Commands

```bash
# View all logs
docker-compose logs -f

# View logs for specific service
docker-compose logs -f app

# Enter the app container
docker-compose exec app bash
```

## Application Structure

### Project Setup

We recommend creating a root folder named `app` and checking out the project inside it. The framework follows an HMVC (Hierarchical Model-View-Controller) architecture based on CodeIgniter.

### Root Directory

```
app/
├── composer.json
├── application/
├── public/
├── private/
├── bin/
└── patches/
```

### Public Directory

The `public/` folder contains all publicly accessible files served by the web server.

```
public/
├── index.php                    # Application entry point
├── media/                       # User-uploaded files
└── assets/                      # Static assets organized by module
    └── {module}/
        ├── js/                  # JavaScript files
        ├── css/                 # Stylesheets
        ├── images/              # Images
        └── videos/              # Video files
```

### Application Directory

The `application/` folder contains the core application code and global resources.

```
application/
├── cache/                       # Application cache
├── config/                      # Application configuration files
├── controllers/                 # Global controllers
├── database/                    # Database configuration
├── helpers/                     # Global helper functions
├── hooks/                       # Global hooks
├── language/                    # Global language files
├── libraries/                   # Global libraries
├── migrations/                  # Database migrations
├── models/                      # Global models
├── modules/                     # HMVC modules (see below)
├── third_party/                 # Third-party libraries
└── views/                       # Global views
```

### Modules (HMVC Structure)

The framework uses HMVC architecture, allowing you to organize code into self-contained modules. Each module can have its own MVC structure and resources.

```
application/modules/
└── {module}/
    ├── controllers/             # Module-specific controllers
    ├── models/                  # Module-specific models
    ├── migrations/              # Module-specific migrations
    ├── views/                   # Module-specific views
    ├── libraries/               # Module-specific libraries
    ├── helpers/                 # Module-specific helpers
    ├── language/                # Module-specific language files
    └── config/                  # Module-specific configuration
```

**Benefits of HMVC:**
- **Modularity**: Each module is self-contained and reusable
- **Organization**: Better code organization for large applications
- **Separation**: Modules can be developed and tested independently
- **Scalability**: Easy to add, remove, or replace modules

**Example Module Structure:**

```
application/modules/blog/
├── controllers/
│   ├── Blog.php
│   └── Admin.php
├── models/
│   └── Blog_model.php
├── views/
│   ├── index.php
│   └── detail.php
└── libraries/
    └── Blog_helper.php
```

### Additional Directories

- **`bin/`** - Command-line scripts and utilities
- **`private/`** - Private files not accessible via web
- **`patches/`** - Compatibility patches for dependencies