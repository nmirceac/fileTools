<?php namespace FileTools\Http\Controllers;

class FilesController extends \App\Http\Controllers\Controller
{
    public function preview($id)
    {
        return \App\File::find($id)->serve();
    }

    public function download($id)
    {
        return \App\File::find($id)->serveForceDownload();
    }
}

