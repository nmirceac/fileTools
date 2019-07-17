<?php

namespace ColorTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

class SetupCommand extends Command
{
    use ConfirmableTrait;
    protected $signature = 'colortools:setup';
    protected $description = 'Setup ColorTools folder structure';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $config = config('colortools');
        if(empty($config)) {
            $this->error('Config not found');
        }

        $storePath = public_path($config['store']['storeBasePath']);
        $this->info('Checking store path - '.$storePath);
        if(file_exists($storePath)) {
            if(is_file($storePath)) {
                $this->error('Path exists but not a folder');
            } else {
                $this->comment('Folder exists - setting permissions');
                chmod($storePath, 0777);
            }
        } else {
            mkdir($storePath);
            $this->comment('Folder created');
        }

        $publicPath = public_path($config['store']['publicPath']);
        $this->info('Checking public path - '.$publicPath);
        if(file_exists($publicPath)) {
            if(is_file($publicPath)) {
                $this->error('Path exists but not a folder');
            } else {
                $this->comment('Folder exists - setting permissions');
                chmod($publicPath, 0777);
            }
        } else {
            mkdir($publicPath);
            $this->comment('Folder created');
        }

        $htaccessPath = public_path($config['store']['publicPath'].DIRECTORY_SEPARATOR.'.htaccess');
        $this->info('Checking public .htaccess');
        if(file_exists($htaccessPath)) {
            if(!is_file($htaccessPath)) {
                $this->error('.htaccess exists but is a folder');
            } else {
                $this->comment('.htaccess exists - updating');
                file_put_contents($htaccessPath, $this->generateHtaccess());
                chmod($htaccessPath, 0444);
            }
        } else {
            file_put_contents($htaccessPath, $this->generateHtaccess());
            chmod($htaccessPath, 0444);
            $this->comment('created .htaccess at path '.$htaccessPath);
        }

        $gitIgnoreStoragePath = public_path($config['store']['storeBasePath'].DIRECTORY_SEPARATOR.'.gitignore');
        $this->info('Checking store .gitignore');
        if(file_exists($gitIgnoreStoragePath)) {
            if(!is_file($gitIgnoreStoragePath)) {
                $this->error('.gitignore exists but is a folder');
            } else {
                $this->comment('.gitignore exists - updating');
                file_put_contents($gitIgnoreStoragePath, $this->generateGitIgnore());
            }
        } else {
            file_put_contents($gitIgnoreStoragePath, $this->generateGitIgnore());
            $this->comment('created .gitignore at path '.$gitIgnoreStoragePath);
        }

        $gitIgnorePublicPath = public_path($config['store']['publicPath'].DIRECTORY_SEPARATOR.'.gitignore');
        $this->info('Checking public .gitignore');
        if(file_exists($gitIgnorePublicPath)) {
            if(!is_file($gitIgnorePublicPath)) {
                $this->error('.gitignore exists but is a folder');
            } else {
                $this->comment('.gitignore exists - updating');
                file_put_contents($gitIgnorePublicPath, $this->generateGitIgnore());
            }
        } else {
            file_put_contents($gitIgnorePublicPath, $this->generateGitIgnore());
            $this->comment('created .gitignore at path '.$gitIgnorePublicPath);
        }

        $this->info('All done');
    }

    public function generateHtaccess()
    {
        $htaccess = ''.
        '<IfModule mod_expires.c>'.PHP_EOL.
        '    ExpiresActive On'.PHP_EOL.
        '    ExpiresDefault "access plus 30 years"'.PHP_EOL.
        '</IfModule>'.PHP_EOL.
        '<IfModule mod_rewrite.c>'.PHP_EOL.
        '    Options +FollowSymlinks'.PHP_EOL.
        '    RewriteEngine On'.PHP_EOL.PHP_EOL.
        '    RewriteCond %{REQUEST_URI} /'.config('colortools.store.publicPath').'/[a-z0-9-%=+:]*\.(jpeg|png|gif)$'.PHP_EOL.
        '    RewriteCond %{REQUEST_FILENAME} !-f'.PHP_EOL.
        '    RewriteRule ^(.{2})(.*)$ ./$1/$1$2 [L]'.PHP_EOL.PHP_EOL.
        '    RewriteCond %{REQUEST_FILENAME} !-f'.PHP_EOL.
        '    RewriteRule ^(.*)$ ../index.php/$1 [L]'.PHP_EOL.
        '</IfModule>'.PHP_EOL;

        return $htaccess;
    }

    public function generateGitIgnore()
    {
        $gitIgnore = '*'.PHP_EOL.'!.gitignore'.PHP_EOL;

        return $gitIgnore;
    }
}