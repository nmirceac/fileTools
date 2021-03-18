<?php namespace FileTools;

use Illuminate\Support\Facades\File as Filesystem;

/**
 * Class File
 * @package FileTools
 */
class File extends \Illuminate\Database\Eloquent\Model
{
    /**
     * @var null
     */
    private $relationship = null;

    public static $withPivot = ['order', 'role', 'details'];
    protected $appends = ['basename', 'details'];

    protected static $backend = null;
    protected static $root = null;

    /**
     * Finds a file by id
     * @param $id
     * @return \FileTools\File
     */
    public static function find($id)
    {
        return parent::query()->find($id);
    }

    /**
     * Gets access to the FilesystemAdapter interface
     * @return \Illuminate\Filesystem\FilesystemAdapter
     * @throws \Exception
     */
    private static function getBackend()
    {
        if(is_null(self::$backend)) {
            if(strtolower(config('filetools.storage.backend'))=='s3') {
                $method = 'create'.ucfirst(strtolower(config('filetools.storage.backend'))).'Driver';
                self::$backend = \Illuminate\Support\Facades\Storage::getFacadeRoot()->$method(config('filetools.'.config('filetools.storage.backend')));
            }

            if(strtolower(config('filetools.storage.backend'))=='azure') {
                $client = \MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService(config('filetools.azure.connection'));
                $adapter = new \League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter($client, config('filetools.azure.root'));
                $filesystem = new \League\Flysystem\Filesystem($adapter);
                self::$backend = $filesystem;
            }
        }

        if(is_null(self::$backend)) {
            throw new \Exception('Backend configuration is invalid');
        }

        return self::$backend;
    }

    private static function checkMime($mime)
    {
        $mime = trim(strtolower($mime));
        if($mime == 'image/svg') {
            $mime = 'image/svg+xml';
        }

        return $mime;
    }

    /**
     * Clears the storage all the files, metadata and associations
     * @throws \Exception
     */
    public static function clearAll()
    {
        self::getBackend()->deleteDirectory(self::getPath(''));
        foreach(static::all() as $file) {
            $file->delete(true);
        }
    }

    /**
     * Gets the app storage folder
     * @return string
     */
    public static function getRoot()
    {
        if(is_null(self::$root))
        {
            self::$root = rtrim(config('filetools.storage.root'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return self::$root ;
    }

    /**
     * Gets the  storage file path
     * @param $path
     * @return string
     */
    public static function getPath($path)
    {
        return self::getRoot() . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Sets the file name
     * @param $name
     */
    public function setNameAttribute($name)
    {
        $this->attributes['name'] = iconv(mb_detect_encoding($name, mb_detect_order(), true), "UTF-8//IGNORE", $name);
    }

    /**
     * Sets the file metadata
     * @param $value
     */
    public function setMetadataAttribute($value)
    {
        $this->attributes['metadata'] = json_encode($value);
    }

    /**
     * Gets the file metadata
     * @return mixed
     */
    public function getMetadataAttribute()
    {
        return json_decode($this->attributes['metadata']);
    }

    /**
     * Gets the file's basename
     * @return string
     */
    public function getBasenameAttribute()
    {
        return $this->name . '.' . $this->extension;
    }

    /**
     * Gets the file's details for the association
     * @param $value
     * @return mixed
     */
    public function getDetailsAttribute($value)
    {
        if (isset($this->pivot['details'])) {
            return json_decode($this->pivot['details']);
        }
    }

    /**
     * Sets the file's details for the assocation
     * @param $value
     */
    public function setDetailsAttribute($value)
    {
        if (isset($this->pivot['details'])) {
            $this->pivot->update(['details' => json_encode($value)]);
        }
    }

    public static function tryToGetMimeMetadataBinaryToolPath($binaryTool)
    {
        exec('which '.$binaryTool, $binaryToolPath);
        $binaryToolPath = reset($binaryToolPath);
        if(empty($binaryToolPath)) {
            return null;
        }

        return $binaryToolPath;
    }

    public static function tryToGetMimeMetadata(string $content, $extension, $mime = null)
    {
        if(empty($mime)) {
            return false;
        }

        $mimeRepresentativePart = last(explode('/', $mime));
        $mimeMethod = str_replace(['/', '-'], '_', $mime);
        $mimeMethodPath = __DIR__.'/../metadata/'.$mimeMethod.'.php';

        if(file_exists($mimeMethodPath)) {
            require_once($mimeMethodPath);
        }
        if(function_exists($mimeMethod)) {
            $mimeMetadata = $mimeMethod($content, $extension);
        } else {
            return false;
        }

        if(is_null($mimeMetadata)) {
            return false;
        }

        return [
            'type'=>$mimeRepresentativePart,
            'data'=>$mimeMetadata,
        ];
    }

    /**
     * Creates a file
     * @param array $metadata
     * @param string|null $contents
     * @return \FileTools\File
     * @throws \Exception
     */
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

        $file = new static();
        $file->hash = $metadata['hash'];
        $file->name = $metadata['name'];
        $file->extension = strtolower($metadata['extension']);
        $file->mime = self::checkMime($metadata['mime']);
        $file->size = $metadata['size'];

        $mimeMetadata = self::tryToGetMimeMetadata($contents, $file->extension, $file->mime);
        if($mimeMetadata) {
            $metadata[$mimeMetadata['type']] = $mimeMetadata['data'];
        }

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

    /**
     * Creates a file from path
     * @param string $filePath
     * @return \FileTools\File
     * @throws \Exception
     */
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

    /**
     * Create a file from a laravel request
     * @param \Illuminate\Http\Request $request
     * @param string $fileKey
     * @return \FileTools\File|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
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

    /**
     * Replaces a file
     * @param array $metadata
     * @param string|null $contents
     * @return \FileTools\File
     * @throws \Exception
     */
    public function replace(array $metadata, string $contents = null)
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

        $oldHash = $this->hash;

        $this->hash = $metadata['hash'];
        $this->name = $metadata['name'];
        $this->extension = $metadata['extension'];
        $this->mime = $metadata['mime'];
        $this->size = $metadata['size'];
        $this->metadata = $metadata;

        $inStore = self::getBackend()->has(self::getPath($this->hash));

        if (!$inStore) {
            $inStore = self::getBackend()->put(self::getPath($this->hash), $contents);
        }

        if ($inStore) {
            $this->save();

            if (static::where('hash', $oldHash)->count() == 0) {
                self::deleteFromBackend($oldHash);
            }

            return $this;
        } else {
            throw new \Exception('There was a problem with storing the file: ' . json_encode($metadata));
        }
    }


    /**
     * Replaces a file from path
     * @param string $filePath
     * @return \FileTools\File
     * @throws \Exception
     */
    public function replaceFromPath(string $filePath)
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

        return $this->replace($metadata, Filesystem::get($filePath));
    }

    /**
     * Replace a file from a laravel request
     * @param \Illuminate\Http\Request $request
     * @param string $fileKey
     * @return \FileTools\File|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function replaceFromRequest(\Illuminate\Http\Request $request, $fileKey = 'file')
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

        return $this->replace($metadata, $contents);
    }




    /**
     * Gets the file's content
     * @return mixed
     */
    public function getContent()
    {
        if(strtolower(config('filetools.storage.backend'))=='azure') {
            return self::getBackend()->get(self::getPath($this->hash))->read();
        }
        return self::getBackend()->get(self::getPath($this->hash));
    }

    /**
     * Checks how many times a file is used within the application
     * @return integer
     */
    public function usageCount()
    {
        return $this->getConnection()
            ->table('file_associations')
            ->where('file_id', $this->id)
            ->count();
    }

    /**
     * Checks if a file is in use
     * @return bool
     */
    public function inUse()
    {
        if ($this->usageCount() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Tries to delete a file after a detach operation, if not in use somewhere else
     * @param $fileId
     */
    public static function tryToDelete($fileId)
    {
        $file = self::find($fileId);
        if (!$file->inUse()) {
            $file->delete();
        }
    }

    /**
     * Deletes a file
     * @return bool
     * @throws \Exception
     */
    public function delete($skipChecks = false)
    {
        if (!$skipChecks && static::where('id', '!=', $this->id)->where('hash', $this->hash)->count() == 0) {
            self::deleteFromBackend($this->hash);
        }
        return parent::delete();
    }

    private static function deleteFromBackend($hash)
    {
        if (self::getBackend()->has(self::getPath($hash))) {
            self::getBackend()->delete(self::getPath($hash));
        }
    }

    /**
     * Gets file by hash
     * @param $hash
     * @return mixed
     */
    public static function getByHash($hash)
    {
        return static::where('hash', $hash)->get();
    }

    /**
     * Serves a file (inline)
     * @return mixed
     */
    public function serve()
    {
        $content = $this->getContent();
        $size = $this->size;
        $returnCode = 200;
        $responseRange = null;

        // range support

        $range = request()->header('range');
        if($range and stripos($range, 'bytes')===0) {
            $range = explode('-', mb_strcut($range, 6));

            if($range[0]==0 and empty($range[1])) {
                // nothing
                $content = $content;
                $responseRange = 'bytes 0-'.($size-1).'/'.$size;
                $returnCode = 206;
            } else {
                $range[0] = min(max($range[0], 0), $this->size);
                if(!empty($range[1])) {
                    $range[1] = min(max($range[1], $range[0]), $this->size);
                } else {
                    $range[1] = $this->size;
                }

                $size = $range[1] - $range[0];
                $content = mb_strcut($content, $range[0], $size);
                $responseRange = 'bytes '.$range[0].'-'.($range[1]-1).'/'.$range[1];
                $returnCode = 206;
            }
        }

        // eo range support

        $response = response($content, $returnCode)
            ->header('Accept-Ranges', 'bytes')
            ->header('Content-Type', $this->mime)
            ->header('Content-Description', $this->name)
            ->header('Content-Length', $size)
            ->header('Content-Disposition', 'inline; filename="' . $this->basename . '"');

        if($responseRange) {
            $response->header('Content-Range', $responseRange);
        }

        return $response;
    }

    /**
     * Serves a file (download)
     * @return mixed
     */
    public function serveForceDownload()
    {
        return response($this->getContent())
            ->header('Content-Type', $this->mime)
            ->header('Content-Description', $this->name)
            ->header('Content-Length', $this->size)
            ->header('Content-Disposition', 'attachment; filename="' . $this->basename . '"');
    }

    /**
     * Get the file's public URL
     * @return mixed
     */
    public function getPublicUrl()
    {
        $this->checkPublic();

        if(strtolower(config('filetools.storage.backend'))=='s3') {
            return self::getBackend()->url(self::getPath($this->hash));
        }

        return route(config('filetools.router.namedPrefix').'.publicPreview', [$this->id, $this->hash]);
    }

    /**
     * Makes sure the file is public
     */
    public function checkPublic()
    {
        if (!$this->public) {
            $this->makePublic();
        }
    }

    /**
     * Get's the file's visibiliy (public or private)
     * @return mixed
     */
    public function getPublicVisibility()
    {
        if(strtolower(config('filetools.storage.backend'))=='s3') {
            return$visibility = self::getBackend()->getVisibility(self::getPath($this->hash));
            if ($visibility == 'public') {
                $this->public = true;
            } else {
                $this->public = false;
            }
            $this->save();
            return $visibility;
        } else {
            $this->public ? 'public' : 'private';
        }
    }

    /**
     * Sets the file's visibility (public or private)
     * @param string $visibility
     * @return $this
     */
    public function setPublicVisibility($visibility = 'private')
    {
        if(strtolower(config('filetools.storage.backend'))=='s3') {
            self::getBackend()->setVisibility(self::getPath($this->hash), $visibility);
        }


        if ($visibility == 'public') {
            $this->public = true;
        } else {
            $this->public = false;
        }
        $this->save();
        return $this;
    }

    /**
     * Makes a file public
     * @return $this
     */
    public function makePublic()
    {
        $this->setPublicVisibility('public');
        return $this;
    }

    /**
     * Makes a file private
     * @return $this
     */
    public function makePrivate()
    {
        $this->setPublicVisibility('private');
        return $this;
    }

    /**
     * Marks a file as hidden
     * @return $this
     */
    public function hide()
    {
        $this->attributes['hidden'] = true;
        $this->save();
        return $this;
    }

    /**
     * Unmarks a hidden file
     * @return $this
     */
    public function unhide()
    {
        $this->attributes['hidden'] = false;
        $this->save();
        return $this;
    }

    /**
     * Checks if a file is hidden
     * @return $this
     */
    public function isHidden()
    {
        return $this->attributes['hidden'];
    }

    private function getLocalSignatureUrl($expiryMinutes = 600)
    {
        $expiry = time() + $expiryMinutes * 60;
        $signature = $this->getSignatureForTimestamp($expiry);
        return [
            'expiry'=>$expiry,
            'signature'=>$signature,
            'urlSuffix'=>'?expiry='.$expiry.'&signature='.$signature,
        ];
    }

    private function getSignatureForTimestamp($timestamp)
    {
        if($timestamp < time()) {
            return false;
        }
        return md5($timestamp.'-'.$this->name.'-'.$this->mime.'-'.$this->size.'-'.$this->created_at);
    }

    public function checkSignatureForTimestamp($timestamp, $signature)
    {
        return $signature === $this->getSignatureForTimestamp($timestamp);
    }

    /**
     * Gets the private, temporary url for a file
     * @param int $expiryMinutes
     * @param array $options
     * @return mixed
     */
    public function getUrl($expiryMinutes = 600, $options = [])
    {
        if(strtolower(config('filetools.storage.backend'))!='s3') {
            return route(config('filetools.router.namedPrefix').'.publicPreview', [$this->id, $this->hash]).self::getLocalSignatureUrl($expiryMinutes)['urlSuffix'];
        }

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

    /**
     * Gets the private, temporary download url for a file
     * @param int $expiryMinutes
     * @param array $options
     * @return mixed
     */
    public function downloadUrl($expiryMinutes = 600, $options = [])
    {
        if(strtolower(config('filetools.storage.backend'))!='s3') {
            return route(config('filetools.router.namedPrefix').'.publicDownload', [$this->id, $this->hash]).self::getLocalSignatureUrl($expiryMinutes)['urlSuffix'];
        }

        $options['ResponseContentDisposition'] = 'attachment; filename="' . $this->basename . '"';

        return $this->getUrl($expiryMinutes, $options);
    }

    /**
     * Checks the relation ship with the associated attaching model
     * @param $model
     * @return |null
     * @throws \Exception
     */
    private function checkRelationship($model)
    {
        if (is_null($this->relationship)) {
            $modelName = get_class($model);
            $modelName = snake_case(substr($modelName, 1 + strrpos($modelName, '\\')));

            if (!method_exists($this, $modelName) and !method_exists($this, \Illuminate\Support\Str::plural($modelName))) {
                throw new \Exception(self::class . ' missing relationship to model of type ' . get_class($model));
            }

            if (!method_exists($model, 'files')) {
                throw new \Exception('Model of type ' . get_class($model) . ' is missing a relationship to ' . self::class);
            }


            $this->relationship = $model->filesRelationship();
        }

        return $this->relationship;
    }

    /**
     * Attaches a file to a related model
     * @param $model
     * @param string $role
     * @param int $order
     * @param array $details
     * @return mixed
     * @throws \Exception
     */
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

    /**
     * Replaces a file for a specific role for an attached model
     * @param $model
     * @param string $role
     * @param array $details
     * @param bool $deleteReplaced
     * @return mixed
     * @throws \Exception
     */
    public function set($model, $role='files', $details=[], $deleteReplaced = false)
    {
        $relationship = $this->checkRelationship($model);
        $this->clear($model, $role, $deleteReplaced);

        if(!isset($details['name'])) {
            $details['name'] = $this->name;
        }

        $pivotDetails = [
            'order'=>1,
            'role'=>$role,
            'details'=>json_encode($details)
        ];

        $relationship->attach($this->id, $pivotDetails);

        return $relationship->where('id', $this->id)->first();
    }

    /**
     * Clears all files for a specific role for an attached model
     * @param $model
     * @param string $role
     * @param bool $deleteReplaced
     * @throws \Exception
     */
    public function clear($model, $role='files', $deleteReplaced = false)
    {
        $relationship = $this->checkRelationship($model);

        if($deleteReplaced) {
            $relationship->wherePivot('role', $role)->delete();
        } else {
            $relationship->wherePivot('role', $role)->detach();
        }

        $model->reorderFilesByRole([], $role);
    }
}
