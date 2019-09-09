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

    public function upload()
    {
        $roleName = request('role');

        $excludeFromDetails = ['file', 'modelId', 'model', 'single', 'role', $roleName];
        $details = request()->except($excludeFromDetails);

        $file = \App\File::createFromRequest(request(), $roleName);

        if (request('model', false) and request('modelId', false)) {
            $model = '\\App\\' . ucfirst(request('model'));
            $model = new $model;
            $object = $model->find(request('modelId'));

            if (request('single', false)) {
                $file = $file->set($object, $roleName, $details);
            } else {
                $file = $file->attach($object, $roleName, 'last', $details);
            }
        }

        return response()->json([$this->itemName => $file]);
    }

    public function attach()
    {
        $file = \App\File::findOrFail(request('id'));

        $roleName = request('role');

        if (request('model', false) and request('modelId', false)) {

            $model = '\\App\\' . ucfirst(request('model'));
            $model = new $model;
            $object = $model->find(request('modelId'));

            if (request('single', false)) {
                $file = $file->set($object, $roleName, []);
            } else {
                $file = $file->attach($object, $roleName, 'last', []);
            }
        }

        return response()->json([$this->itemName => $file]);
    }

    public function delete()
    {
        $file = \App\File::findOrFail(request('id'));

        if (request('model', false) and request('modelId', false)) {

            $model = '\\App\\' . ucfirst(request('model'));
            $model = new $model;
            $object = $model->find(request('modelId'));

            if (request('id')) {
                $object->clearFile(request('id'));
            } elseif (request('role')) {
                $object->clearFilesByRole(request('role'));
            }
        }

        $file->delete();
    }

    public function replace()
    {
        $originalFile = \App\File::findOrFail(request('id'));
        $roleName = request('role');

        $newFile = $originalFile->replaceFromRequest(request(), $roleName);

        $fileMeta = (array)$newFile->metadata;

        $metadata = request()->only(['caption', 'copyright', 'tags']);
        foreach ($metadata as $key => $data) {
            if ($data == "null" || is_null($data)) {
                if ($key !== 'tags') {
                    $fileMeta[$key] = '';
                } else {
                    $fileMeta[$key] = [];
                }
            } elseif ($key == 'tags') {
                $fileMeta['tags'] = explode(',', $data);
            } else {
                $fileMeta[$key] = $data;
            }
        }

        $newFile->metadata = $fileMeta;
        $newFile->update();

        return response()->json([$this->itemName => $newFile]);
    }

    public function update()
    {
        $file = \App\File::findOrFail(request('id'));

        if (request('model', false) and request('modelId', false)) {
            $model = '\\App\\' . ucfirst(request('model'));
            $model = new $model;
            $object = $model->find(request('modelId'));
            $file->attach($object, request('role'), request('order'), request('metadata'));
        } else {
            $file->metadata = request('metadata');
            $file->update();
        }

        $file->log(Log::LOG_FILE_UPDATED);

        return response()->json([$this->itemName => $file]);
    }

    public function reorder()
    {
        $model = '\\App\\' . ucfirst(request('model'));
        $model = new $model;
        $object = $model->find(request('modelId'));
        $object->reorderFilesByRole(request('ids'), request('role'));
    }
}

