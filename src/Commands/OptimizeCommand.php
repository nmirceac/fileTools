<?php

namespace ColorTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use \App\ImageStore;

class OptimizeCommand extends Command
{
    use ConfirmableTrait;
    protected $signature = 'colortools:optimize {--jpeg} {--png}';
    protected $description = 'Reoptimize published images';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        if(!$this->option('jpeg', false) and !$this->option('png', false)) {
            $this->error('Must specify --jpeg or --png');
            exit();
        }

        $images = ImageStore::count();
        $published = [];
        $jpeg = [];
        $jpegOptimized = [];
        $png = [];
        $pngOptimized = [];

        $publicPath = public_path(config('colortools.store.publicPath'));

        foreach(glob($publicPath.'/*/*') as $publishedFile) {
            $published[] = filesize($publishedFile);
            if(substr($publishedFile, -4)=='jpeg') {
                $jpeg[] = filesize($publishedFile);
            } else if(substr($publishedFile, -3)=='png') {
                $png[] = filesize($publishedFile);
            }
        }



        $this->info(count($published).' published images with a total size of '.number_format(array_sum($published)/1024/1024, 2).'MB');
        if($this->option('jpeg', false)) {
            $this->info(count($jpeg).' published JPEG images have a total size of '.number_format(array_sum($jpeg)/1024/1024, 2).'MB');
            $this->comment('Optimizing JPEGs');
            foreach(glob($publicPath.'/*/*.jpeg') as $publishedFile) {
                \ColorTools\Store::optimizeFile($publishedFile);
                echo '.';
                $jpegOptimized[] = filesize($publishedFile);
            }
            echo PHP_EOL;
            $this->comment('Published JPEG images now have a total size of '.number_format(array_sum($jpegOptimized)/1024/1024, 2).'MB');
            $this->info('The JPEG optimization saved '.number_format((array_sum($jpeg) - array_sum($jpegOptimized))/1024/1024, 2).'MB');
        }
        if($this->option('png', false)) {
            $this->info(count($png).' published PNG images have a total size of '.number_format(array_sum($png)/1024/1024, 2).'MB');
            $this->comment('Optimizing PNGs');
            foreach(glob($publicPath.'/*/*.png') as $publishedFile) {
                \ColorTools\Store::optimizeFile($publishedFile);
                echo '.';
                $pngOptimized[] = filesize($publishedFile);
            }
            echo PHP_EOL;
            $this->comment('Published PNG images now have a total size of '.number_format(array_sum($pngOptimized)/1024/1024, 2).'MB');
            $this->info('The PNG optimization saved '.number_format((array_sum($png) - array_sum($pngOptimized))/1024/1024, 2).'MB');
        }

        $this->info('All done!');
    }
}