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


## Samples

### ENV config

;FILE_STORAGE_BACKEND="s3"
;FILE_STORAGE_BACKEND="azure"
FILE_STORAGE_ROOT="files"

FILE_S3_ACCESS_KEY_ID="..."
FILE_S3_SECRET_ACCESS_KEY="..."
FILE_S3_DEFAULT_REGION="fra1"
FILE_S3_BUCKET="mybucker"
FILE_S3_URL="https://fra1.digitaloceanspaces.com"
FILE_S3_ROOT="ics"

FILE_AZURE_CONNECTION_STRING="DefaultEndpointsProtocol=https;AccountName=...;AccountKey=...;EndpointSuffix=core.windows.net"
FILE_AZURE_ROOT="comms"
