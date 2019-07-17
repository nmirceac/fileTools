<?php

namespace ColorTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use \App\ImageStore;

class StatsCommand extends Command
{
    use ConfirmableTrait;
    protected $signature = 'colortools:stats';
    protected $description = 'Fun stats';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $images = ImageStore::count();
        $this->info($images.' '.str_plural('image', $images).' found with a total size of '.number_format(ImageStore::sum('size')/1024/1024, 2).'MB');
        $published = [];
        $jpeg = [];
        $png = [];

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
        $this->info(count($jpeg).' published images are JPEG with a total size of '.number_format(array_sum($jpeg)/1024/1024, 2).'MB');
        $this->info(count($png).' published images are PNG with a total size of '.number_format(array_sum($png)/1024/1024, 2).'MB');
        $this->info('All done!');
    }
}