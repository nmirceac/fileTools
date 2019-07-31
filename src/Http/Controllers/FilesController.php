<?php namespace FileTools\Http\Controllers;

class FilesController extends \App\Http\Controllers\Controller
{
    public function preview($id)
    {
        if(strtolower(config('filetools.storage.backend')) == 's3') {
            return redirect(\App\File::find($id)->getUrl());
        }
        return \App\File::find($id)->serve();
    }

    public function download($id)
    {
        if(strtolower(config('filetools.storage.backend')) == 's3') {
            return redirect(\App\File::find($id)->downloadUrl());
        }
        return \App\File::find($id)->serveForceDownload();
    }
}

