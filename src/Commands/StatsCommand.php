<?php

namespace FileTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use \App\File;

class StatsCommand extends Command
{
    use ConfirmableTrait;
    protected $signature = 'filetools:stats';
    protected $description = 'Fun stats';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $files = File::count();
        $this->info($files.' '.str_plural('files', $files).' found with a total size of '.number_format(File::sum('size')/1024/1024, 2).'MB');
        $this->info('All done!');
    }
}