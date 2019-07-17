<?php

namespace ColorTools\Http\Controllers;

use Illuminate\Http\Request;

class ImageController extends \App\Http\Controllers\Controller
{
    public function index($urlString='')
    {
        $type='jpeg';
        if(strrpos($urlString, '.')) {
            $type = substr($urlString, 1 + strrpos($urlString, '.'));
        }

        $store = \ColorTools\Store::findAndProcess($urlString);
        $store->publish($type);
        header('Content-type: image/'.$type);
        if(config('colortools.store.optimizeAfterPublish.'.$type, false)) {
            \ColorTools\Store::optimizeFile($store->getPublishPath($type));
        }
        echo file_get_contents($store->getPublishPath($type));
    }
}
