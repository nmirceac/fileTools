<?php namespace ColorTools;

class Store
{
    const OBJECT_TYPE_IMAGE = 1;

    private $object = null;
    private $objectType = null;

    private $temporaryFile = null;

    private $storePath = null;
    private static $storeBasePath = 'store';
    private static $storePattern = '%hash_prefix%/%hash%';
    private $storeSuffix = '';

    private static $publicPath = 'images';
    private static $publicPattern = 'images/%hash%';

    public function __construct($storageItem=null)
    {
        if(is_null($storageItem)) {
            throw new Exception('No storage item here');
        }

        if(gettype($storageItem)=='object' and get_class($storageItem)=='ColorTools\Image')
        {
            $this->object = $storageItem;
            $this->objectType = self::OBJECT_TYPE_IMAGE;
            $this->setSuffix($this->object->getModifiersString());
        } else {
            try {
                $this->object = new Image($storageItem);
                $this->objectType = self::OBJECT_TYPE_IMAGE;
                $this->setSuffix($this->object->getModifiersString());
            } catch (Exception $e) {
                throw new Exception('I don\'t know what this storage item is: '.print_r($storageItem, true));
            }
        }
    }

    public function __destruct()
    {
        if(!is_null($this->temporaryFile) and file_exists($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }
    }

    public static function settings($settings=[])
    {
        if(empty($settings)) {
            return false;
        }

        if(isset($settings['storeBasePath'])) {
            self::$storeBasePath = $settings['storeBasePath'];
        }

        if(isset($settings['storePattern'])) {
            self::$storePattern = $settings['storePattern'];
        }

        if(isset($settings['publicPath'])) {
            self::$publicPath = $settings['publicPath'];
        }

        if(isset($settings['publicPattern'])) {
            self::$publicPattern = $settings['publicPattern'];
        }

    }

    public static function create($storageItem=null)
    {
        return (new Store($storageItem));
    }

    public function getObject()
    {
        return $this->object;
    }

    private function getTemporaryFile()
    {
        if(is_null($this->temporaryFile)) {
            $basePath = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
            $this->temporaryFile = $basePath.DIRECTORY_SEPARATOR.md5(time().rand(0, 10000000)).'.tmp';
        }

        return $this->temporaryFile;
    }

    private function getStorePath()
    {
        if(is_null($this->storePath)) {
            $path = self::$storeBasePath . DIRECTORY_SEPARATOR;
            $path.= str_replace([
                '%hash_prefix%',
                '%hash%'
            ], [
                substr($this->getHash(), 0, 2),
                $this->getHash()
            ], self::$storePattern);

            $this->storePath = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);
            $this->setSuffix($this->object->getModifiersString());
            $this->storePath = $this->storePath.$this->storeSuffix;
        }

        if(function_exists('public_path')) {
            return public_path($this->storePath);
        }

        return $this->storePath;
    }

    public function deleteFromStore()
    {
        if(file_exists($this->getStorePath())) {
            unlink($this->getStorePath());
            return true;
        } else {
            return false;
        }
    }

    public function getPublishPath($type='jpeg')
    {
        $path = self::$publicPath . DIRECTORY_SEPARATOR;
        $path.= str_replace([
            '%hash_prefix%',
            '%hash%'
        ], [
            substr($this->getHash(), 0, 2),
            $this->getHash()
        ], self::$storePattern);

        $this->setSuffix($this->object->getModifiersString());

        $path = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);
        $path.= $this->storeSuffix;
        $path.= '.'.$type;

        if(function_exists('public_path')) {
            $path = public_path($path);
        }

        return $path;
    }
    
    public function getPublishedGlobSelector()
    {
        return substr($this->getPublishPath(''), 0, -1).'*';
    }

    public function getPublishedFiles()
    {
        return glob($this->getPublishedGlobSelector());
    }

    public function deletePublished()
    {
        foreach($this->getPublishedFiles() as $publishedFile) {
            unlink($publishedFile);
        }
    }

    private function verifyPath($path=null)
    {
        if(is_null($path)) {
            throw new Exception('You must specify a path to verify');
        }

        $basePath = dirname($path);
        if(!file_exists($basePath)) {
            mkdir($basePath, 0777, true);
        } else {
            if(!is_dir($basePath)) {
                throw new Exception('Base path '.$basePath.' already exists but it\'s a file not a directory'.
                    ' - cannot store at '.$path);
            }
        }
    }

    private function writeAtPath($filePath=null, $type='jpeg')
    {
        if(is_null($filePath)) {
            throw new Exception('No file path specified');
        }

        if($this->objectType == self::OBJECT_TYPE_IMAGE) {
            file_put_contents($filePath, $this->object->getImageContent($type));
        }
    }

    public function getPath()
    {
        if($this->objectType == self::OBJECT_TYPE_IMAGE and !empty($this->object->getImagePath())){
            return $this->object->getImagePath();
        } else {
            $temporaryFile = $this->getTemporaryFile();
            $this->writeAtPath($temporaryFile);
            return $temporaryFile;
        }
    }

    public function getHash()
    {
        return $this->object->getHash();
    }

    public function getSize()
    {
        return filesize($this->getStorePath());
    }

    public function getType()
    {
        return $this->object->type;
    }

    public function setSuffix($suffix='')
    {
        $this->storeSuffix=trim($suffix);

        return $this;
    }

    public function store()
    {
        $this->verifyPath($this->getStorePath());
        $this->writeAtPath($this->getStorePath());

        return $this;
    }

    public function publish($type='jpeg')
    {
        $this->verifyPath($this->getPublishPath());
        $this->writeAtPath($this->getPublishPath($type), $type);

        $path = str_replace([
            '%hash_prefix%',
            '%hash%'
        ], [
            substr($this->getHash(), 0, 2),
            $this->getHash()
        ], self::$publicPattern);

        $path = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);
        $path.= $this->storeSuffix;

        return $path;
    }


    /**
     * @param string $hash
     * @return Store
     * @throws Exception
     */
    public static function findByHash($hash=null)
    {
        if(is_null($hash)) {
            throw new Exception('The hash cannot be empty', Exception::STORE_EXCEPTION_HASH_EMPTY);
        }

        if(strlen($hash)!=32) {
            throw new Exception('The hash must have 32 characters', Exception::STORE_EXCEPTION_HASH_NOT_32);
        }

        $path = self::$storeBasePath . DIRECTORY_SEPARATOR;
        $path.= str_replace([
            '%hash_prefix%',
            '%hash%'
        ], [
            substr($hash, 0, 2),
            $hash
        ], self::$storePattern);

        $path = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);
        if(function_exists('public_path')) {
            $path = public_path($path);
        }

        if(file_exists($path)) {
            $store = Store::create($path);
        } else if(file_exists($path.'.jpeg')) {
            $store = Store::create($path.'.jpeg');
        } else if(file_exists($path.'.png')) {
            $store = Store::create($path.'.png');
        } else {
            throw new Exception('The object with the hash '.$hash.' was not found', Exception::STORE_EXCEPTION_HASH_NOT_FOUND);
        }
        $store->object->refreshImageObject()->forceModify()->forceHash($hash);

        return $store;
    }

    public function modifyImage($closure=null)
    {
        if(is_null($closure)) {
            throw new Exception('Must pass a closure to the modifyImage function');
        }

        $closure($this->object);
        return $this;
    }

    public static function getUrl($hash, $modifiers=null, $type='jpeg')
    {
        $path = str_replace([
            '%hash_prefix%',
            '%hash%'
        ], [
            substr($hash, 0, 2),
            self::getHashAndTransformations($hash, $modifiers, $type)
        ], self::$publicPattern);

        return $path;
    }

    public static function getHashAndTransformations($hash, $modifiers=null, $type='jpeg')
    {
        $path = $hash;

        if(!is_null($modifiers)) {
            if(is_array($modifiers) or is_callable($modifiers)) {
                $path.=self::convertTransformationsToModifierString($modifiers);

            } else if(is_string($modifiers)) {
                $path.='-'.ltrim($modifiers, '-');
            }
        }

        $path.='.'.$type;
        return $path;
    }

    public static function convertTransformationsToModifierString($transformations = null)
    {
        if(is_null($transformations)) {
            throw new Exception('The transformations cannot be null');
        }

        if(!is_callable($transformations) and !is_array($transformations)) {
            throw new Exception('The transformations have to be callable');
        }

        $store = new Store(new Image(Image::FAKE_IMAGE));
        if(is_array($transformations)) {
            foreach($transformations as $transformationCallback) {
                if(!is_callable($transformationCallback)) {
                    throw new Exception('The transformations have to be callable');
                }
                $store->modifyImage($transformationCallback);
            }
        } else {
            $store->modifyImage($transformations);
        }
        return $store->object->getModifiersString();
    }

    public function processModifiersString($modifiersString='')
    {
        $this->object->processModifiersString($modifiersString);
        return $this;
    }

    public static function findAndProcess($hashAndModifiers=null, $publish=false)
    {
        if(is_null($hashAndModifiers)) {
            throw new Exception('The hash cannot be empty');
        }

        if(strlen($hashAndModifiers)<32) {
            throw new Exception('The hash must have at least 32 characters');
        }

        $hash = substr($hashAndModifiers, 0, 32);
        $modifiers = substr($hashAndModifiers, 32);

        $type='jpeg';
        if(strrpos($modifiers, '.')!==false) {
            $type = substr($modifiers, 1 + strrpos($modifiers, '.'));
            $modifiers = substr($modifiers,  0, strrpos($modifiers, '.'));
        }

        $store = Store::findByHash($hash);
        $store->object->autoRotate();
        $store->processModifiersString($modifiers);

        if($publish) {
            $store->publish($type);
        }

        return $store;
    }

    public static function optimizeFile($filePath=null)
    {
        if(is_null($filePath)) {
            throw new Exception('The file path cannot be empty');
        }

        if(!file_exists($filePath)) {
            throw new Exception('The file '.$filePath.' cannot be found');
        }

        if(strrpos($filePath, '.')) {
            $type = substr($filePath, 1 + strrpos($filePath, '.'));
        }

        if($type=='jpeg') {
            exec('which jpegoptim', $jpegoptim);
            if(empty($jpegoptim)) {
                throw new Exception('Cannot find jpegoptim binary');
            }
            
            $parameters = trim(config('colortools.store.optimizeCommand.jpegoptimParams',
                '-s --all-progressive -m90'));
            exec($jpegoptim[0].' '.$parameters.' '.$filePath);
            return true;
        }

        if($type=='png') {
            exec('which optipng', $optipng);
            if(empty($optipng)) {
                throw new Exception('Cannot find optipng binary');
            }

            $parameters = trim(config('colortools.store.optimizeCommand.optipngParams',
                '-strip all -o2'));
            exec($optipng[0].' -quiet '.$parameters.' '.$filePath, $output);
            return true;
        }

        return false;
    }
}