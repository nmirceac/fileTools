# Welcome to FileTools!

## Contents
1. Intro
2. Classes, methods and examples

# 1. Intro

## How to install?

- composer require nmirceac/file-tools
- add \FileTools\FileToolsServiceProvider::class to your config/app.php's service providers section
- php artisan vendor:publish
- php artisan migrate
- check config/filetools.php (just in case)
- php artisan filetools:setup
- extend \FileTools\File as an app model (see example below)
- add the \FileTools\HasFiles
- check the examples below
- enjoy! 

