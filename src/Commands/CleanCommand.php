<?php

namespace ColorTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use \App\ImageStore;

class CleanCommand extends Command
{
    use ConfirmableTrait;
    protected $signature = 'colortools:clean {--delete} {--deletePublished}';
    protected $description = 'Spring cleaning';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $images = ImageStore::count();
        $this->info($images.' '.str_plural('image', $images).' found with a total size of '.number_format(ImageStore::sum('size')/1024/1024, 2).'MB');

        $storePath = public_path(config('colortools.store.storeBasePath'));
        $publicPath = public_path(config('colortools.store.publicPath'));

        $storedHashes = ImageStore::get(['hash'])->pluck('hash')->toArray();

        $extraFilesStored = [];
        foreach(glob($storePath.'/*/*') as $storedFile) {
            $hash = substr($storedFile, -32);
            if(in_array($hash, $storedHashes)) {
                continue;
            }
            $extraFilesStored[] = filesize($storedFile);
            $toDelete[] = $storedFile;
        }

        $this->comment(count($extraFilesStored).' extra images found in storage '.str_plural('image', count($extraFilesStored)).' found with a total size of '.number_format(array_sum($extraFilesStored)/1024/1024, 2).'MB');

        $filesPublished = [];
        $extraFilesPublished = [];
        foreach(glob($publicPath.'/*/*') as $publishedFile) {
            $hash = substr($publishedFile, 1 + strrpos($publishedFile, '/'), 32);
            if($this->option('deletePublished', false)) {
                $filesPublished[] = $publishedFile;
            }
            if(in_array($hash, $storedHashes)) {
                continue;
            }
            $extraFilesPublished[] = filesize(($publishedFile));
            $toDelete[] = $publishedFile;
        }

        $this->comment(count($extraFilesPublished).' extra published '.str_plural('image', count($extraFilesPublished)).' found with a total size of '.number_format(array_sum($extraFilesPublished)/1024/1024, 2).'MB');

        if($this->option('deletePublished', false)) {
            $this->error('Deleting all published files - '.count($filesPublished).' '.str_plural('image', count($filesPublished)));
            $deletedSize = 0;
            foreach($filesPublished as $file) {
                if(file_exists($file)) {
                    $deletedSize += filesize($file);
                    unlink($file);
                }
            }
            $this->error('Freed up '.number_format($deletedSize/1024/1024, 2).'MB');
        }

        if($this->option('delete', false)) {
            if(isset($toDelete)) {
                $this->error('Deleting '.count($toDelete).' '.str_plural('image', count($toDelete)));
                $deletedSize = 0;
                foreach($toDelete as $file) {
                    if(file_exists($file)) {
                        $deletedSize += filesize($file);
                        unlink($file);
                    }
                }
                $this->error('Freed up '.number_format($deletedSize/1024/1024, 2).'MB');
            } else {
                $this->info('No files to delete, you\'re running a clean ship!');
            }
        } else {
            if(isset($toDelete)) {
                $this->comment('Run php artisan colortools:clean --delete if you want to delete the extra files or --deletePublished to clean the published images');
            } else if (!$this->option('deletePublished', false)) {
                $this->comment('Run php artisan colortools:clean --deletePublished to clean the published images');
            }
        }
        
        $this->info('All done!');
    }
}