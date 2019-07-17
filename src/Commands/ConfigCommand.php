<?php

namespace ColorTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

class ConfigCommand extends Command
{
    use ConfirmableTrait;
    protected $signature = 'colortools:config';
    protected $description = 'Show ColorTools config';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $config = config('colortools');
        if(!empty($config)) {
            $this->info('--- Config found  ---');
            foreach($config as $param=>$value) {
                $this->comment('-'.$param.'-');
                echo "    ".trim(substr(print_r($value, true), 8, -2)).PHP_EOL;
            }
            $this->comment('--- end of config ---');
        } else {
            $this->error('Config not found');
        }
    }
}