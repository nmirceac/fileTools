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

    public function previewPage($id, $page)
    {
        $file = \App\File::find($id);
        if($file->mime != 'application/pdf') {
            return abort(403);
        }

        $dpi = request('dpi', 50);
        $format = strtolower(request('format', 'png'));
        if($format!='png') {
            $format = 'jpeg';
        }
        $crop = request('crop');
        if(empty($crop)) {
            $crop=[];
        } else {
            $crop = explode('-', $crop);
        }

        $filters = request('filters');
        if(!empty($filters)) {
            $filtersFromString = explode('|', $filters);
            $filters = [];
            foreach($filtersFromString as $filterParams) {
                $filterParams = explode(' ', $filterParams);
                $filterType = array_shift($filterParams);
                $filters[$filterType] = $filterParams;
            }
        } else {
            $filters = [];
        }

        $image = $file->getPdfPage($page, $dpi, $crop, $format, $filters);

        return response($image)
            ->header('Content-Type', 'image/'.$format)
            ->header('Content-Description', $file->name.' - page '.$page)
            ->header('Content-Length', strlen($image))
            ->header('Content-Disposition', 'inline; filename="' . $file->name.' - page '.$page . '"');
    }

    public function download($id)
    {
        if(strtolower(config('filetools.storage.backend')) == 's3') {
            return redirect(\App\File::find($id)->downloadUrl());
        }
        return \App\File::find($id)->serveForceDownload();
    }

    public function publicPreview($id, $hash)
    {
        $file = \App\File::find($id);

        if(is_null($file)) {
            return abort(404);
        }

        if($file->hash != $hash) {
            return abort(401);
        }

        if(!$file->public and !$file->checkSignatureForTimestamp(request('expiry'), request('signature'))){
            return abort(401);
        }

        return \App\File::find($id)->serve();
    }

    public function publicDownload($id, $hash)
    {
        $file = \App\File::find($id);

        if(is_null($file)) {
            return abort(404);
        }

        if($file->hash != $hash) {
            return abort(401);
        }

        if(!$file->public and !$file->checkSignatureForTimestamp(request('expiry'), request('signature'))){
            return abort(401);
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

        return response()->json(['file' => $file]);
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

        return response()->json(['file' => $file]);
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

        return response()->json(['file' => $newFile]);
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

        return response()->json(['file' => $file]);
    }

    public function reorder()
    {
        $model = '\\App\\' . ucfirst(request('model'));
        $model = new $model;
        $object = $model->find(request('modelId'));
        $object->reorderFilesByRole(request('ids'), request('role'));
    }

    public function associations()
    {
        $model = '\\App\\' . ucfirst(request('model'));
        $model = new $model;
        $object = $model->find(request('modelId'));

        //$traits = (new \ReflectionClass($object))->getTraits();
        //
        //if(!is_null($object) and array_key_exists('ColorTools\HasImages', $traits)) {
            return response()->json(['files' => $object->files]);
        //} else {
        //    return response()->json(['images' => []]);
        //}
    }

    public function public()
    {
        $file = \App\File::findOrFail(request('id'));
        if($file) {
            return response()->json(['public'=>true, 'url' => $file->getPublicUrl()]);
        }
    }

    public function private()
    {
        $file = \App\File::findOrFail(request('id'));
        if($file) {
            $file->makePrivate();
            return response()->json(['public'=>false]);
        }
    }

    public function togglePublic()
    {
        $file = \App\File::findOrFail(request('id'));

        if($file) {
            if($file->public) {
                $file->makePrivate();
                return response()->json(['public'=>false]);
            } else {
                $file->makePublic();
                return response()->json(['public'=>true]);
            }
        }
    }


    public function unhide()
    {
        $file = \App\File::findOrFail(request('id'));
        if($file) {
            $file->unhide();
            return response()->json(['hidden'=>false]);
        }
    }

    public function hide()
    {
        $file = \App\File::findOrFail(request('id'));
        if($file) {
            $file->hide();
            return response()->json(['hidden'=>true]);
        }
    }

    public function toggleHidden()
    {
        $file = \App\File::findOrFail(request('id'));
        if($file) {
            if($file->isHidden()) {
                $file->unhide();
                return response()->json(['hidden'=>false]);
            } else {
                $file->hide();
                return response()->json(['hidden'=>true]);
            }
        }
    }
}

