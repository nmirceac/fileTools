<?php namespace FileTools\Http\Controllers;

use App\File;

class FilesController extends \App\Http\Controllers\Controller
{
    protected $itemName = 'file';

    protected $orderAsc = false;
    protected $orderBy = 'created_at';
    protected $itemsPerPage = 15;

    protected $singleAppends = [];
    protected $multipleAppends = [];

    protected $singleRelationships = [];
    protected $multipleRelationships = [];

    public function vueHelper($action=null)
    {
        if(is_null($action) and request()->has('action')) {
            $action = request()->get('action');
        }

        if($action) {
            if(in_array($action, get_class_methods($this))) {
                return $this->$action();
            } else {
                throw new \Exception('Method not found');
            }
        } else {
            return response('Not acceptable', '406');
        }
    }

    public function upload()
    {
        $roleName = request('role');
        $details = request()->except('file', 'modelId', 'single', 'model', 'files', 'role', $roleName);

        $file = File::createFromRequest(request(), $roleName);

        $model = '\\App\\' . ucfirst(request('model'));
        $model = new $model;
        $object = $model->find(request('modelId'));

        $file = $file->attach($object, $roleName, 0, $details);
        $object->log(Log::LOG_FILE_UPLOADED);

        return response()->json([$this->itemName => $file]);
    }

    public function uploadWithoutAttaching()
    {
        $roleName = request('role');
        $file = File::createFromRequest(request(), $roleName);

        return response()->json([$this->itemName => $file]);
    }

    public function preview($hash)
    {
        $file = File::getByHash($hash);
        if (config('filesystems.backend', 'local') == 'local') {
            return $file->serve();
        } else {
            return redirect($this->class::getByHash($hash)->getUrl());
        }
    }

    public function download($hash)
    {
        $file = File::getByHash($hash);
        if (config('filesystems.backend', 'local') == 'local') {
            return $file->serveForceDownload();
        } else {
            return redirect($file->downloadUrl());
        }
    }

    public function clear()
    {
        $model = '\\App\\' . ucfirst(request('model'));
        $model = new $model;
        $object = $model->find(request('modelId'));

        if (request('id')) {
            $object->clearFile(request('id'));
        } elseif (request('role')) {
            $object->clearFilesByRole(request('role'));
        }
    }

    public function reorder()
    {
        $model = '\\App\\' . ucfirst(request('model'));
        $model = new $model;
        $object = $model->find(request('modelId'));
        $object->reorderFilesByRole(request('ids'), request('role'));
    }

    public function update()
    {
        $file = File::find(request('id'));
        $model = '\\App\\' . ucfirst(request('model'));
        $model = new $model;
        $object = $model->find(request('modelId'));
        $file->attach($object, request('role'), request('order'), request('details'));
    }

    public function searchQuery()
    {
        $searchQuery = request('searchQuery');

        $results = File::with($this->multipleRelationships);

        if (isset($searchQuery) and !empty($searchQuery)) {
            $searchQuery = '%' . $searchQuery . '%';

            $results->where(function ($query) use ($searchQuery) {
                $query->where('name', 'LIKE', $searchQuery);
                $query->orWhere('metadata', 'LIKE', $searchQuery);
            });
        }

        return $results;
    }

}
