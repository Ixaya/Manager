<img src="http://www.ixaya.com/images/logo_ixaya.png">

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


## Application Structure

### Root Folder structure
We recomend you to create a folder named app and checkout the project inside.

* `composer.js`
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


Soon more Docs...


