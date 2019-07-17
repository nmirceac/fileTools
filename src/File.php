<?php namespace FileTools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File as Filesystem;

class File extends Model
{
    /**
     * @var null
     */
    private $relationship = null;

    protected $fillable = ['public'];
    public static $withPivot = ['order', 'role', 'details'];
    protected $appends = ['basename', 'details'];

    /**
     * Returns post relationship to files.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function posts()
    {
        return $this->morphedByMany(Post::class,
            'association',
            'image_associations',
            'association_id',
            'image_id'
        )->withPivot(self::$withPivot);
    }

    public static function getBackend()
    {
        return \Storage::disk(config('filetools.backend'));
    }

    public static function getRoot()
    {
        return rtrim(config('filetools.root'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public static function getPath($path)
    {
        return self::getRoot() . ltrim($path, DIRECTORY_SEPARATOR);
    }

    public function setNameAttribute($name)
    {
        $this->attributes['name'] = iconv(mb_detect_encoding($name, mb_detect_order(), true), "UTF-8//IGNORE", $name);
    }

    public function setMetadataAttribute($value)
    {
        $this->attributes['metadata'] = json_encode($value);
    }

    public function getMetadataAttribute()
    {
        return json_decode($this->attributes['metadata']);
    }

    public function getBasenameAttribute()
    {
        return $this->name . '.' . $this->extension;
    }

    public function getDetailsAttribute($value)
    {
        if (isset($this->pivot['details'])) {
            return json_decode($this->pivot['details']);
        }
    }

    public function setDetailsAttribute($value)
    {
        if (isset($this->pivot['details'])) {
            $this->pivot->update(['details' => json_encode($value)]);
        }
    }

    public static function create(array $metadata, string $contents = null)
    {
        $validator = \Validator::make($metadata, [
            'hash' => 'required|string|size:32',
            'name' => 'required|string',
            'mime' => 'required|string',
            'size' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors());
        }

        if (empty($contents)) {
            throw new \Exception('Contents is empty');
        }

        $file = self::getByHash($metadata['hash']);
        if (!is_null($file)) {
            return $file;
        }

        $file = new File();
        $file->hash = $metadata['hash'];
        $file->name = $metadata['name'];
        $file->extension = $metadata['extension'];
        $file->mime = $metadata['mime'];
        $file->size = $metadata['size'];
        $file->metadata = $metadata;

        $inStore = self::getBackend()->has(self::getPath($file->hash));

        if (!$inStore) {
            $inStore = self::getBackend()->put(self::getPath($file->hash), $contents);
        }

        if ($inStore) {
            $file->save();
            return $file;
        } else {
            throw new \Exception('There was a problem with storing the file: ' . json_encode($metadata));
        }
    }

    public static function createFromPath(string $filePath)
    {
        if (!file_exists(($filePath))) {
            throw new \Exception('File not found at path ' . $filePath . ' (' . base_path($filePath) . ')');
        }

        if (is_dir(($filePath))) {
            throw new \Exception('The path ' . $filePath . ' resolves to a directory, not to a file');
        }

        $metadata['mime'] = Filesystem::mimeType($filePath);
        $metadata['name'] = Filesystem::name($filePath);
        $metadata['dirname'] = Filesystem::dirname($filePath);
        $metadata['basename'] = Filesystem::basename($filePath);
        $metadata['extension'] = Filesystem::extension($filePath);
        $metadata['size'] = Filesystem::size($filePath);
        $metadata['lastModified'] = Filesystem::lastModified($filePath);
        $metadata['originalPath'] = $filePath;
        $metadata['hash'] = Filesystem::hash($filePath);

        return self::create($metadata, Filesystem::get($filePath));
    }

    public function getContent()
    {
        return self::getBackend()->get(self::getPath($this->hash));
    }

    public function inUse()
    {
        if ($this->abstracts()->count() > 0) {
            return true;
        }

        return false;
    }

    public static function tryToDelete($fileId)
    {
        $file = self::find($fileId);
        if (!$file->inUse()) {
            $file->delete();
        }
    }

    public function delete()
    {
        if (File::where('id', '!=', $this->id)->where('hash', $this->hash)->count() == 0) {
            if (self::getBackend()->has(self::getPath($this->hash))) {
                self::getBackend()->delete(self::getPath($this->hash));
            }
        }
        parent::delete();
    }


    public static function createFromRequest(\Illuminate\Http\Request $request, $fileKey = 'file')
    {
        if (!$request->hasFile($fileKey)) {
            return response()->json([
                'error' => 'Missing file'
            ]);
        }

        $fileInfo = $request->file($fileKey);

        $metadata['mime'] = $fileInfo->getMimeType();
        $metadata['name'] = $fileInfo->getClientOriginalName();
        $metadata['basename'] = $fileInfo->getClientOriginalName();
        $metadata['extension'] = $fileInfo->getClientOriginalExtension();
        $metadata['size'] = $fileInfo->getSize();
        $metadata['originalPath'] = $fileInfo->getRealPath();
        $metadata['hash'] = md5_file($metadata['originalPath']);

        if (!empty($metadata['extension'])) {
            $metadata['name'] = substr($metadata['name'], 0, -(1 + strlen($metadata['extension'])));
        }

        $contents = file_get_contents($fileInfo->getRealPath());

        return self::create($metadata, $contents);
    }

    public static function getByHash($hash)
    {
        return File::where('hash', $hash)->first();
    }

    public function serve()
    {
        return response($this->getContent())
            ->header('Content-Type', $this->mime)
            ->header('Content-Description', $this->name)
            ->header('Content-Length', $this->size)
            ->header('Content-Disposition', 'inline; filename="' . $this->basename . '"');
    }

    public function serveForceDownload()
    {
        return response($this->getContent())
            ->header('Content-Type', $this->mime)
            ->header('Content-Description', $this->name)
            ->header('Content-Length', $this->size)
            ->header('Content-Disposition', 'attachment; filename="' . $this->basename . '"');
    }

    public function getPublicUrl()
    {
        $this->checkPublic();
        return self::getBackend()->url(self::getPath($this->hash));
    }

    public function checkPublic()
    {
        if (!$this->public) {
            $this->makePublic();
        }
    }

    public function getVisibility()
    {
        $visibility = self::getBackend()->getVisibility(self::getPath($this->hash));
        if ($visibility == 'public') {
            $this->update(['public' => true]);
        } else {
            $this->update(['public' => false]);
        }
        return $visibility;
    }

    public function setVisibility($visibility = 'private')
    {
        self::getBackend()->setVisibility(self::getPath($this->hash), $visibility);
        if ($visibility == 'public') {
            $this->update(['public' => true]);
        } else {
            $this->update(['public' => false]);
        }
        return $this;
    }

    public function makePublic()
    {
        $this->setVisibility('public');
    }

    public function makePrivate()
    {
        $this->setVisibility('private');
    }

    public function getUrl($expiryMinutes = 600, $options = [])
    {
        if (!isset($options['ResponseContentType'])) {
            $options['ResponseContentType'] = $this->mime;
        }

        if (!isset($options['ResponseCacheControl'])) {
            $options['ResponseCacheControl'] = 'max-age=604800';
        }

        if (!isset($options['ResponseContentDisposition'])) {
            $options['ResponseContentDisposition'] = 'inline; filename="' . $this->basename . '"';
        }

        return self::getBackend()->temporaryUrl(self::getPath($this->hash), '+' . $expiryMinutes . ' minutes', $options);
    }

    public function downloadUrl($expiryMinutes = 600, $options = [])
    {
        $options['ResponseContentDisposition'] = 'attachment; filename="' . $this->basename . '"';

        return $this->getUrl($expiryMinutes, $options);
    }

    public function products()
    {
        return $this->morphedByMany(Product::class, 'association', 'file_associations')->withPivot(self::$withPivot);
    }

    private function checkRelationship($model)
    {
        if (is_null($this->relationship)) {
            $modelName = get_class($model);
            $modelName = strtolower(substr($modelName, 1 + strrpos($modelName, '\\')));

            if (!method_exists($this, $modelName) and !method_exists($this, Str::plural($modelName))) {
                throw new \Exception(self::class . ' missing relationship to model of type ' . get_class($model));
            }

            if (!method_exists($model, 'files')) {
                throw new \Exception('Model of type ' . get_class($model) . ' is missing a relationship to ' . self::class);
            }


            $this->relationship = $model->filesRelationship();
        }

        return $this->relationship;
    }

    public function attach($model, $role = 'files', $order = 0, $details = [])
    {
        $relationship = $this->checkRelationship($model);

        if (empty($order)) {
            $order = 'next';
        }
        if ($order < 0) {
            $order = 'first';
        }

        if (!isset($details['name'])) {
            $details['name'] = $this->name;
        }

        $models = [];
        foreach ($model->filesByRole($role)->get() as $file) {
            $models[$file->id] = [
                'order' => $file->pivot->order,
                'role' => $file->pivot->role,
                'details' => $file->pivot->details,
            ];
        }

        if (!isset($details['name'])) {
            $details['name'] = $this->name;
        }

        if (!isset($details['filename'])) {
            $details['filename'] = $this->name . '.' . $this->extension;
        }

        $models[$this->id] = [
            'order' => $order,
            'role' => $role,
            'details' => json_encode($details)
        ];

        if (in_array($order, ['next', 'last'])) {
            $order = count($models);
        } else if ($order == 'first') {
            $order = 1;
        } else {
            $order = min($order, count($models));
        }

        $newOrder = range(1, count($models));

        $index = 0;
        foreach ($models as $id => $model) {
            if ($this->id == $id) {
                $models[$id]['order'] = $order;
            } else {
                if ($order == $newOrder[$index]) {
                    $index--;
                }
                $models[$id]['order'] = $newOrder[$index];
            }
            $index++;
        }

        $relationship->syncWithoutDetaching($models);

        return $relationship->where('id', $this->id)->first();
    }
}
