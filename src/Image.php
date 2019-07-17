<?php namespace ColorTools;

class Image
{
    public static $settings = [];

    private $image = NULL;
    private $imageType = NULL;
    private $imagePath = NULL;

    private $imageObject = NULL;
    private $imageObjectType = NULL;

    private $modified = false;

    protected $type = null;
    protected $mime = null;
    protected $width = null;
    protected $height = null;

    protected $exif = null;

    protected $hash = null;

    protected $skipNextModifier = false;
    protected $modifiers = [];

    const FAKE_IMAGE='76';

    const MODIFIER_FIT='ft';
    const MODIFIER_FILL_WIDTH='fw';
    const MODIFIER_FILL_HEIGHT='fh';
    const MODIFIER_CONTAIN='ct';
    const MODIFIER_COVER='cv';
    const MODIFIER_ANCHOR='an';
    const MODIFIER_CROP='cr';
    const MODIFIER_FILTER='fi';
    const MODIFIER_ROTATE='ro';
    const MODIFIER_FLIP='fl';

    protected $preferredEngine = self::ENGINE_GD; // or self::ENGINE_IMAGICK;

    private $resizingOptions = [
        'engine'=>self::RESIZE_ENGINE_NATIVE,
        'imagick'=>[
            'adaptive'=>true,
            'filter'=>self::RESIZE_FILTER_AUTO,
            'blur'=>0.25
        ],
        'gd'=>[
            'filter'=>self::RESIZE_FILTER_AUTO
        ]
    ];

    private $croppingOptions = [
        'anchor'=>self::CROP_ANCHOR_CENTER
    ];

    const ENGINE_GD=1;
    const ENGINE_IMAGICK=2;

    const CROP_ANCHOR_CENTER=0;
    const CROP_ANCHOR_LEFT=1;
    const CROP_ANCHOR_RIGHT=2;
    const CROP_ANCHOR_TOP=3;
    const CROP_ANCHOR_BOTTOM=4;

    const FLIP_HORIZONTAL = 1;
    const FLIP_VERTICAL = 2;
    const FLIP_BOTH = 3;

    const RESIZE_ENGINE_NATIVE=0;
    const RESIZE_ENGINE_GD=1;
    const RESIZE_ENGINE_IMAGICK=2;

    const RESIZE_FILTER_AUTO=99;

    const IMAGE_TYPE_STRING = 1;
    const IMAGE_TYPE_URL = 2;
    const IMAGE_TYPE_FILE = 3;
    const IMAGE_TYPE_GD = 4;
    const IMAGE_TYPE_IMAGICK = 5;
    const IMAGE_TYPE_FAKE = 76;

    const IMAGE_OBJECT_TYPE_GD = 1;
    const IMAGE_OBJECT_TYPE_IMAGICK = 2;

    const FILTER_NEGATE=0;
    const FILTER_GRAYSCALE=1;
    const FILTER_BRIGTHNESS=2;
    const FILTER_CONTRAST=3;
    const FILTER_COLORIZE=4;
    const FILTER_EDGEDETECT=5;
    const FILTER_EMBOSS=6;
    const FILTER_GAUSSIAN_BLUR=7;
    const FILTER_SELECTIVE_BLUR=8;
    const FILTER_MEAN_REMOVAL=9;
    const FILTER_SMOOTH=10;
    const FILTER_PIXELATE=11;
    const FILTER_SEPIA=21;
    const FILTER_ENHANCE=22;
    const FILTER_EQUALIZE=23;
    const FILTER_AUTO_LEVEL=24;
    const FILTER_MOTION_BLUR=25;
    const FILTER_OIL_PAINT=26;
    const FILTER_POSTERIZE=27;
    const FILTER_RADIAL_BLUR=28;
    const FILTER_SEGMENT=29;
    const FILTER_SIGMOIDAL_CONTRAST=30;
    const FILTER_SKETCH=31;
    const FILTER_SOLARIZE=32;
    const FILTER_SPREAD=33;
    const FILTER_THRESHOLD=34;
    const FILTER_BLACK_THRESHOLD=35;
    const FILTER_WAVE=36;
    const FILTER_VIGNETTE=37;
    const FILTER_SWIRL=38;
    const FILTER_NOISE=39;
    const FILTER_BLUE_SHIFT=40;
    const FILTER_CHARCOAL=41;
    const FILTER_GAMMA=42;
    const FILTER_BLUR=43;


    public function __construct($image)
    {
        if($image==self::FAKE_IMAGE) {
            $this->imageType = self::IMAGE_TYPE_FAKE;
            return $this;
        }

        if(gettype($image)=='string')
        {
            if(substr($image, 0, 7)=='http://' or substr($image, 0, 8)=='https://') {
                $this->imageType = self::IMAGE_TYPE_URL;
                $this->imagePath = $image;
                $image = file_get_contents($image);
                $this->hash = md5($image);
            } elseif ((strlen($image)>64 and strpos(substr($image, 0, 16), '/')===false) or strlen($image)>768) {
                //assume it's the content of an image file
                $this->imageType = self::IMAGE_TYPE_STRING;
                $this->hash = md5($image);
            } else {
                if (!file_exists($image)) {
                    throw new Exception('Invalid filename');
                }

                if (filesize($image) <= 11) {
                    throw new Exception('This is too small to be an image');
                }

                $this->imageType = self::IMAGE_TYPE_FILE;
                $this->imagePath = realpath($image);
                $this->hash = md5_file($image);
            }
        } else if(gettype($image)=='resource') {
            $resourceType = get_resource_type($image);
            switch ($resourceType) {
                case 'gd' :
                    $this->imageObjectType = self::IMAGE_OBJECT_TYPE_GD;
                    $this->imageType = self::IMAGE_TYPE_GD;

                default :
                break;
            }
        } else if(gettype($image)=='object') {
            $class = get_class($image);
            switch ($class) {
                case 'ColorTools\Image' :
                    return $image;

                case 'Imagick' :
                    $this->imageObjectType = self::IMAGE_OBJECT_TYPE_IMAGICK;
                    $this->imageType = self::IMAGE_TYPE_IMAGICK;

                default :
                    break;
            }
        }

        if($this->imageType == null) {
            throw new Exception('Cannot make anything of this image of type '.gettype($image));
        }

        $this->image = $image;
        $this->applySettings();
        $this->getExifInfo();
        $this->getImageDetails();
    }

    public static function settings($settings=[])
    {
        if(empty($settings)) {
            return false;
        }

        self::$settings = $settings;
    }

    public static function create($image)
    {
        if(isset($image) and gettype($image)=='object' and get_class($image) == 'ColorTools\Image') {
            return $image;
        } else {
            return new Image($image);
        }
    }

    public function __get($param) {
        $param = strtolower($param);

        if(in_array($param, ['type', 'mime', 'width', 'height'])) {
            return $this->$param;
        }

        if($param == 'imageObject') {
            return $this->getImageObject();
        }

        if($param == 'imageObjectType') {
            $this->getImageObject();
            return $this->$imageObjectType();
        }

        throw new Exception('Unknown property '.$param);

    }

    private function addModifier($modifier, $params=[])
    {
        if($this->skipNextModifier) {
            $this->skipNextModifier = false;
        } else {
            $this->modifiers[$modifier] = $params;
        }

        return $this;
    }

    private function skipNextModifier()
    {
        $this->skipNextModifier = true;
        return $this;
    }

    public function getModifiers()
    {
        return $this->modifiers;
    }

    public function getModifiersString()
    {
        $modifiers = $this->modifiers;
        ksort($modifiers);
        $output = '';
        foreach($modifiers as $modifier=>$params) {
            foreach($params as $paramId=>$param) {
                if(is_array($param)) {
                    $params[$paramId] = implode(':', $param);
                }
            }

            $output.='-'.$modifier.'='.implode('+', $params);
        }

        return $output;
    }

    public function processModifiersString($modifiersString='')
    {
        $filters = [];

        $modifiers = explode('-', $modifiersString);
        foreach($modifiers as $modifierPart) {
            if(empty($modifierPart)) {
                continue;
            }

            $modifierPart = explode('=', $modifierPart);
            $modifier = $modifierPart[0];
            if(isset($modifierPart[1])) {
                $params = explode('+', $modifierPart[1]);
            } else {
                $params = [];
            }

            if(!in_array($modifier, [
                self::MODIFIER_FIT,
                self::MODIFIER_FILL_WIDTH,
                self::MODIFIER_FILL_HEIGHT,
                self::MODIFIER_CONTAIN,
                self::MODIFIER_COVER,
                self::MODIFIER_ANCHOR,
                self::MODIFIER_CROP,
                self::MODIFIER_FILTER,
                self::MODIFIER_ROTATE,
                self::MODIFIER_FLIP
            ])) {
                throw new Exception('Problem processing the modifier string - unknown modifier: '.$modifier);
            }

            switch($modifier) {
                case self::MODIFIER_ANCHOR:
                    $this->setCropAnchor($params[0]);
                    break;

                case self::MODIFIER_FIT:
                    $this->fit($params[0], $params[1]);
                    break;

                case self::MODIFIER_FILL_WIDTH:
                    $this->fillWidth($params[0]);
                    break;

                case self::MODIFIER_FILL_HEIGHT:
                    $this->fillHeight($params[0]);
                    break;

                case self::MODIFIER_CONTAIN:
                    $this->resizeContain($params[0], $params[1]);
                    break;

                case self::MODIFIER_COVER:
                    $this->resizeCover($params[0], $params[1]);
                    break;

                case self::MODIFIER_CROP:
                    $this->doCrop($params[0], $params[1]);
                    break;

                case self::MODIFIER_FILTER:
                    $filters[$params[0]] = explode(':', $params[1]);
                    break;

                case self::MODIFIER_ROTATE:
                    $this->doRotate($params[0]);
                    break;

                case self::MODIFIER_FLIP:
                    $this->doFlip($params[0]);
                    break;


                default:
                    break;
            }
        }

        foreach($filters as $filter=>$params) {
            $this->applyFilter($filter, $params);
        }

        return $this;
    }

    private function applySettings()
    {
        if(empty(self::$settings)) {
            return false;
        }

        if(isset(self::$settings['preferredEngine'])) {
            if(!in_array(self::$settings['preferredEngine'], [
                self::ENGINE_GD,
                self::ENGINE_IMAGICK
            ])) {
                throw new Exception('Unsupported engine... Sorry m8... Try GD or Imagick - they are both great!');
            }
            $this->preferredEngine = self::$settings['preferredEngine'];
        }

        if(isset(self::$settings['resizing']['engine'])) {
            if(!in_array(self::$settings['resizing']['engine'], [
                self::RESIZE_ENGINE_NATIVE,
                self::RESIZE_ENGINE_GD,
                self::RESIZE_ENGINE_IMAGICK
            ])) {
                throw new Exception('Unsupported resizing engine... Try not touching anything...');
            }
            $this->resizingOptions['engine'] = self::$settings['resizing']['engine'];
        }

        if(isset(self::$settings['resizing']['imagick']['adaptive'])) {
            $this->resizingOptions['imagick']['adaptive'] = self::$settings['resizing']['imagick']['adaptive'];
        }

        if(isset(self::$settings['resizing']['imagick']['filter'])) {
            if(!in_array(self::$settings['resizing']['imagick']['filter'], [
                self::RESIZE_FILTER_AUTO,
                \Imagick::FILTER_POINT,
                \Imagick::FILTER_BOX,
                \Imagick::FILTER_TRIANGLE,
                \Imagick::FILTER_HERMITE,
                \Imagick::FILTER_HANNING,
                \Imagick::FILTER_HAMMING,
                \Imagick::FILTER_BLACKMAN,
                \Imagick::FILTER_GAUSSIAN,
                \Imagick::FILTER_QUADRATIC,
                \Imagick::FILTER_CUBIC,
                \Imagick::FILTER_CATROM,
                \Imagick::FILTER_MITCHELL,
                \Imagick::FILTER_LANCZOS,
                \Imagick::FILTER_BESSEL,
                \Imagick::FILTER_SINC
            ])) {
                throw new Exception('Unsupported resizing filter for Imagick');
            }
            $this->resizingOptions['imagick']['filter'] = self::$settings['resizing']['imagick']['filter'];
        }

        if(isset(self::$settings['resizing']['imagick']['blur'])) {
            $this->resizingOptions['imagick']['blur'] = floatval(self::$settings['resizing']['imagick']['blur']);
        }

        if(isset(self::$settings['resizing']['gd']['filter'])) {
            if(!in_array(self::$settings['resizing']['gd']['filter'], [
                self::RESIZE_FILTER_AUTO,
                IMG_BESSEL,
                IMG_BILINEAR_FIXED,
                IMG_BICUBIC,
                IMG_BICUBIC_FIXED,
                IMG_BLACKMAN,
                IMG_BOX,
                IMG_BSPLINE,
                IMG_CATMULLROM,
                IMG_GAUSSIAN,
                IMG_GENERALIZED_CUBIC,
                IMG_HERMITE,
                IMG_HAMMING,
                IMG_HANNING,
                IMG_MITCHELL,
                IMG_POWER,
                IMG_QUADRATIC,
                IMG_SINC,
                IMG_NEAREST_NEIGHBOUR,
                IMG_WEIGHTED4,
                IMG_TRIANGLE
            ])) {
                throw new Exception('Unsupported resizing filter for GD');
            }
            $this->resizingOptions['gd']['filter'] = self::$settings['resizing']['gd']['filter'];
        }
    }

    public static function createFromColors($colorsArray=array(), $width=0, $height=0)
    {
        if (empty($colorsArray)) {
            throw new Exception('Couldn\'t find any colors.');
        }

        if (empty($width)) {
            throw new Exception('I need the width of the image');
        }
        if (empty($height)) {
            throw new Exception('I also need the height of the image');
        }

        $image = imagecreatetruecolor($width, $height);
        for($x=0; $x<$width; $x++) {
            for($y=0; $y<$height; $y++) {
                imagesetpixel($image, $x, $y, $colorsArray[$x + $y * $width]->int);
            }
        }

        return new Image($image);
    }

    public function getImageType(){
        return $this->imageType;
    }

    private function processImageSizes()
    {
        $this->refreshImageObject();

        switch($this->imageObjectType) {
            case self::IMAGE_OBJECT_TYPE_GD :
                $this->width = imagesx($this->image);
                $this->height = imagesy($this->image);
                return true;
                break;

            case self::IMAGE_OBJECT_TYPE_IMAGICK :
                $this->width = $this->image->getImageWidth();
                $this->height = $this->image->getImageHeight();
                return true;
                break;

            default:
                break;
        }

        return $this;
    }

    public function getImageDetails()
    {
        switch ($this->imageType) {
            case self::IMAGE_TYPE_FILE :
                $size = getimagesize($this->image);
                break;

            case self::IMAGE_TYPE_URL :
                $size = getimagesizefromstring($this->image);
                break;

            case self::IMAGE_TYPE_STRING :
                $size = getimagesizefromstring($this->image);
                break;

            case self::IMAGE_TYPE_GD :
                $this->width = imagesx($this->image);
                $this->height = imagesy($this->image);
                return true;
                break;

            case self::IMAGE_TYPE_IMAGICK :
                $this->width = $this->image->getImageWidth();
                $this->height = $this->image->getImageHeight();
                return true;
                break;

            default:
                throw new Exception('Undefined image type');
        }


        if (empty($size)) {
            throw new Exception('This is not an image');
        }

        $this->type = substr($size['mime'], 6);
        $this->mime = $size['mime'];
        $this->width = $size[0];
        $this->height = $size[1];
    }

    public function forceModify()
    {
        $this->modified = true;
        return $this;
    }

    public function getImagePath()
    {
        return $this->imagePath;
    }

    public function getImageContent($outputType='jpeg', $outputTypeQuality=95)
    {
        if(!is_null($this->imagePath) and !$this->modified) {
            return file_get_contents($this->imagePath);
        } else {
            switch ($this->imageType) {
                case self::IMAGE_TYPE_STRING :
                    return $this->image;
                    break;

                case self::IMAGE_TYPE_GD : case self::IMAGE_TYPE_IMAGICK :
                    $outputTypeQuality = min($outputTypeQuality, 100);

                    if($outputType=='png' and $outputTypeQuality>9) {
                        if($this->imageType==self::IMAGE_TYPE_GD) {
                            imagealphablending($this->image, false);
                            imagesavealpha($this->image, true);
                        }
                        $outputTypeQuality = round($outputTypeQuality/11); //the png quality scale is 0..9
                    }

                    if($outputTypeQuality<0) {
                        $outputTypeQuality=0;
                    }

                    if($this->imageType==self::IMAGE_TYPE_GD) {
                        ob_start();
                        call_user_func('image' . $outputType, $this->image, null, $outputTypeQuality);
                        $image = ob_get_contents();
                        ob_end_clean();
                    } else if($this->imageType==self::IMAGE_TYPE_IMAGICK) {
                        $image = $this->image;
                        $image->setImageFormat($outputType);
                        $image->setImageCompressionQuality($outputTypeQuality);
                        $image = (string) $image;
                    }
                    return $image;
                    break;

                default:
                    break;
            }
        }
    }

    public function getImageSrc($outputType='jpeg', $outputTypeQuality=95) // the number of my old apartment
    {
        if(!is_null($this->imagePath) and $this->imageType != self::IMAGE_TYPE_FILE and !$this->modified) {
            return $this->imagePath;
        } else {
            switch ($this->imageType) {
                case self::IMAGE_TYPE_STRING : case self::IMAGE_TYPE_FILE :
                    return 'data:image/'.$this->type.';base64, '.base64_encode($this->getImageContent());
                    break;

                case self::IMAGE_TYPE_GD : case self::IMAGE_TYPE_IMAGICK :
                    return 'data:image/'.$outputType.';base64, '.base64_encode($this->getImageContent($outputType, $outputTypeQuality));
                    break;

                default:
                    break;
            }
        }
    }

    public function displayImage($outputType='jpeg', $outputTypeQuality=95)
    {
        if(!in_array($this->imageType, [self::IMAGE_TYPE_GD, self::IMAGE_TYPE_IMAGICK])) {
            $outputType = $this->type;
        }

        if (!headers_sent($filename, $linenum)) {
            header("Content-Type: image/".$outputType);
        } else {
            throw new Exception('Headers already sent by '.$filename.' at line '.$linenum);
        }

        return $this->getImageContent($outputType, $outputTypeQuality);
    }

    public function getImageObject()
    {
        if(is_null($this->imageObject)) {
            switch ($this->imageType) {
                case self::IMAGE_TYPE_FILE :
                    if($this->preferredEngine==self::ENGINE_GD) {
                        $this->imageObject = call_user_func('imagecreatefrom' . $this->type, $this->image);
                        $this->imageObjectType = self::IMAGE_OBJECT_TYPE_GD;
                    } else if($this->preferredEngine==self::ENGINE_IMAGICK) {
                        $this->imageObject = new \Imagick();
                        $this->imageObject->readImageBlob(file_get_contents($this->image));
                        $this->imageObjectType = self::IMAGE_OBJECT_TYPE_IMAGICK;
                    }
                    break;

                case self::IMAGE_TYPE_STRING : case self::IMAGE_TYPE_URL :
                    if($this->preferredEngine==self::ENGINE_GD) {
                        $this->imageObject = imagecreatefromstring($this->image);
                        $this->imageObjectType = self::IMAGE_OBJECT_TYPE_GD;
                    } else if($this->preferredEngine==self::ENGINE_IMAGICK) {
                        $this->imageObject = new \Imagick();
                        $this->imageObject->readImageBlob($this->image);
                        $this->imageObjectType = self::IMAGE_OBJECT_TYPE_IMAGICK;
                    }
                    break;

                case self::IMAGE_TYPE_GD :
                    $this->imageObjectType = self::IMAGE_OBJECT_TYPE_GD;
                    return $this->image;
                    break;

                case self::IMAGE_TYPE_IMAGICK :
                    $this->imageObjectType = self::IMAGE_OBJECT_TYPE_IMAGICK;
                    return $this->image;
                    break;

                default:
                    break;
            }
        }

        return $this->imageObject;
    }

    public function refreshImageObject()
    {
        if(empty($this->imageObjectType) or empty($this->imageObject)) {
            $this->getImageObject();
        }

        if($this->imageObjectType==self::IMAGE_OBJECT_TYPE_GD) {
            $this->image = $this->getImageObject();
            $this->imageObject = null;
            $this->imageType = self::IMAGE_TYPE_GD;
            $this->imageObjectType = self::IMAGE_OBJECT_TYPE_GD;
        } else if($this->imageObjectType==self::IMAGE_OBJECT_TYPE_IMAGICK) {
            $this->image = $this->getImageObject();
            $this->imageObject = null;
            $this->imageType = self::IMAGE_TYPE_IMAGICK;
            $this->imageObjectType = self::IMAGE_OBJECT_TYPE_IMAGICK;
        }

        $this->getImageObject();
        return $this;
    }

    public function convertObjectTypeTo($imageObjectType=null)
    {
        if(!in_array($imageObjectType, [self::IMAGE_OBJECT_TYPE_GD, self::IMAGE_OBJECT_TYPE_IMAGICK])) {
            throw new Exception('Invalid image object type: '.$imageObjectType);
        }

        if($this->imageObjectType==self::IMAGE_OBJECT_TYPE_GD and $imageObjectType==self::IMAGE_OBJECT_TYPE_IMAGICK) {
            $image = $this->getImageContent('png', 100); // changed from jpeg
            imagedestroy($this->image);
            $this->image = new \Imagick();
            $this->image->readImageBlob($image);
            $this->imageObject = null;
            $this->imageType = self::IMAGE_TYPE_IMAGICK;
            $this->imageObjectType = self::IMAGE_OBJECT_TYPE_IMAGICK;
            $this->getImageContent('png', 100); // changed from jpeg
            $this->modified = true;
        } else if($this->imageObjectType==self::IMAGE_OBJECT_TYPE_IMAGICK and $imageObjectType==self::IMAGE_OBJECT_TYPE_GD) {
            $this->image = imagecreatefromstring($this->getImageContent('png', 100)); // changed from jpeg
            $this->imageObject = null;
            $this->imageType = self::IMAGE_TYPE_GD;
            $this->imageObjectType = self::IMAGE_OBJECT_TYPE_GD;
            $this->modified = true;
        }

        return $this;
    }

    public function convertObjectTypeToGd()
    {
        return $this->convertObjectTypeTo(self::IMAGE_OBJECT_TYPE_GD);
    }

    public function convertObjectTypeToImagick()
    {
        return $this->convertObjectTypeTo(self::IMAGE_OBJECT_TYPE_IMAGICK);
    }

    public function fit($width=null, $height=null, $cropAnchor=null)
    {
        if(is_null($width) or is_null($height)) {
            throw new Exception('Invalid sizes');
        }

        $this->skipNextModifier()->resizeCover($width, $height);
        $this->skipNextModifier()->doCrop($width, $height, $cropAnchor);
        $this->addModifier(self::MODIFIER_FIT, [$width, $height]);

        return $this;
    }

    public function fillWidth($width=null)
    {
        if(is_null($width) or is_null($width)) {
            throw new Exception('Invalid width');
        }

        $ratio = $this->width / $width;
        $height = round($this->height / $ratio, 0);

        $this->skipNextModifier()->resizeCover($width, $height);
        $this->addModifier(self::MODIFIER_FILL_WIDTH, [$width]);

        return $this;
    }

    public function fillHeight($height=null)
    {
        if(is_null($height) or is_null($height)) {
            throw new Exception('Invalid height');
        }

        $ratio = $this->height / $height;
        $width = round($this->width / $ratio, 0);

        $this->skipNextModifier()->resizeCover($width, $height);
        $this->addModifier(self::MODIFIER_FILL_HEIGHT, [$height]);

        return $this;
    }

    public function resizeContain($width=null, $height=null)
    {
        if(is_null($width) or is_null($height)) {
            throw new Exception('Invalid sizes');
        }

        $this->addModifier(self::MODIFIER_CONTAIN, [$width, $height]);
        if($this->imageType==self::IMAGE_TYPE_FAKE) {
            return $this;
        }

        $horizontalRatio = $this->width / $width;
        $verticalRatio = $this->height / $height;

        $ratio = max($horizontalRatio, $verticalRatio);

        $width = $this->width / $ratio;
        $height = $this->height / $ratio;



        return $this->doResize($width, $height);
    }

    public function resizeCover($width=null, $height=null)
    {
        $this->addModifier(self::MODIFIER_COVER, [$width, $height]);
        if($this->imageType==self::IMAGE_TYPE_FAKE) {
            return $this;
        }

        if(is_null($width) or is_null($height)) {
            throw new Exception('Invalid sizes');
        }

        $horizontalRatio = $this->width / $width;
        $verticalRatio = $this->height / $height;

        $ratio = min($horizontalRatio, $verticalRatio);

        $width = $this->width / $ratio;
        $height = $this->height / $ratio;

        return $this->doResize($width, $height);
    }

    public function doResize($width=null, $height=null)
    {
        if(is_null($width) or is_null($height)) {
            throw new Exception('Invalid sizes');
        }

        $this->refreshImageObject();

        if($this->resizingOptions['engine']==self::RESIZE_ENGINE_GD) {
            $this->convertObjectTypeToGd();
        }

        if($this->resizingOptions['engine']==self::RESIZE_ENGINE_IMAGICK) {
            $this->convertObjectTypeToImagick();
        }

        switch ($this->imageObjectType) {
            case self::IMAGE_OBJECT_TYPE_GD:
                $filter = IMG_BICUBIC_FIXED;
                if(in_array($this->resizingOptions['gd']['filter'], [
                    IMG_BESSEL,
                    IMG_BILINEAR_FIXED,
                    IMG_BICUBIC,
                    IMG_BICUBIC_FIXED,
                    IMG_BLACKMAN,
                    IMG_BOX,
                    IMG_BSPLINE,
                    IMG_CATMULLROM,
                    IMG_GAUSSIAN,
                    IMG_GENERALIZED_CUBIC,
                    IMG_HERMITE,
                    IMG_HAMMING,
                    IMG_HANNING,
                    IMG_MITCHELL,
                    IMG_POWER,
                    IMG_QUADRATIC,
                    IMG_SINC,
                    IMG_NEAREST_NEIGHBOUR,
                    IMG_WEIGHTED4,
                    IMG_TRIANGLE
                ])) {
                    $filter = $this->resizingOptions['gd']['filter'];
                }

                $this->image = imagescale($this->getImageObject(), $width, $height, $filter);
                if($this->type=='png') {
                    imagealphablending($this->image, false);
                    imagesavealpha($this->image, true);
                }

                $this->imageType = self::IMAGE_TYPE_GD;
                break;

            case self::IMAGE_OBJECT_TYPE_IMAGICK:
                $this->imageType = self::IMAGE_TYPE_IMAGICK;

                if($this->resizingOptions['imagick']['adaptive']) {
                     $this->image->adaptiveResizeImage($width, $height);
                } else {
                    $filter = \Imagick::FILTER_BOX;
                    if(in_array($this->resizingOptions['imagick']['filter'], [
                        \Imagick::FILTER_POINT,
                        \Imagick::FILTER_BOX,
                        \Imagick::FILTER_TRIANGLE,
                        \Imagick::FILTER_HERMITE,
                        \Imagick::FILTER_HANNING,
                        \Imagick::FILTER_HAMMING,
                        \Imagick::FILTER_BLACKMAN,
                        \Imagick::FILTER_GAUSSIAN,
                        \Imagick::FILTER_QUADRATIC,
                        \Imagick::FILTER_CUBIC,
                        \Imagick::FILTER_CATROM,
                        \Imagick::FILTER_MITCHELL,
                        \Imagick::FILTER_LANCZOS,
                        \Imagick::FILTER_BESSEL,
                        \Imagick::FILTER_SINC
                    ])) {
                        $filter = $this->resizingOptions['imagick']['filter'];
                    }
                    $this->image->resizeImage($width, $height, $filter, $this->resizingOptions['imagick']['blur']);
                }

                break;

            default:
                break;
        }

//        $this->hash = null; // rehashing options
        $this->imageObject = null;
        $this->modified = true;

        $this->processImageSizes();

        return $this;
    }

    public function setCropAnchor($cropAnchor=null)
    {
        if(!in_array($cropAnchor, [
            self::CROP_ANCHOR_CENTER,
            self::CROP_ANCHOR_LEFT,
            self::CROP_ANCHOR_RIGHT,
            self::CROP_ANCHOR_TOP,
            self::CROP_ANCHOR_BOTTOM,
        ])) {
            throw new Exception('Invalid crop anchor: '.$cropAnchor);
        }

        $this->croppingOptions['anchor'] = $cropAnchor;

        $this->addModifier(self::MODIFIER_ANCHOR, [$cropAnchor]);

        return $this;
    }

    public function doCrop($width=null, $height=null, $cropAnchor=null)
    {
        if(is_null($width) or is_null($height)) {
            throw new Exception('Invalid sizes');
        }

        $this->addModifier(self::MODIFIER_CROP, [$width, $height, $cropAnchor]);
        if($this->imageType==self::IMAGE_TYPE_FAKE) {
            return $this;
        }

        if(!is_null($cropAnchor)) {
            $this->setCropAnchor($cropAnchor);
        }

        if($this->resizingOptions['engine']==self::RESIZE_ENGINE_GD) {
            $this->convertObjectTypeToGd();
        }

        if($this->resizingOptions['engine']==self::RESIZE_ENGINE_IMAGICK) {
            $this->convertObjectTypeToImagick();
        }

        if($this->width > $width) {
            $widthOffset = floor(($this->width - $width)/2);
        } else {
            $widthOffset = 0;
        }

        if($this->height > $height) {
            $heightOffset = floor(($this->height - $height)/2);
        } else {
            $heightOffset = 0;
        }

        if($this->croppingOptions['anchor']==self::CROP_ANCHOR_LEFT) {
            $widthOffset=0;
        } else if($this->croppingOptions['anchor']==self::CROP_ANCHOR_RIGHT) {
            $widthOffset*=2;
        }

        if($this->croppingOptions['anchor']==self::CROP_ANCHOR_TOP) {
            $heightOffset=0;
        } else if($this->croppingOptions['anchor']==self::CROP_ANCHOR_BOTTOM) {
            $heightOffset*=2;
        }


        switch ($this->imageObjectType) {
            case self::IMAGE_OBJECT_TYPE_GD:
                $image = imagecreatetruecolor($width, $height);
                if($this->type=='png') {
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                imagecopyresampled($image, $this->image, 0, 0, $widthOffset, $heightOffset, $width, $height, $width, $height);
                imagedestroy($this->image);
                $this->image = $image;
                $this->imageType = self::IMAGE_TYPE_GD;
                break;

            case self::IMAGE_OBJECT_TYPE_IMAGICK:
                $this->image->cropImage($width, $height, $widthOffset, $heightOffset);
                $this->imageType = self::IMAGE_TYPE_IMAGICK;
                break;

            default:
                break;
        }

//        $this->hash = null; # adding rehashing function
        $this->imageObject = null;
        $this->modified = true;
        $this->width=$width;
        $this->height=$height;

        return $this;
    }

    public function applyFilter($filter=null, $params=[])
    {
        if(is_null($filter)) {
            throw new Exception('No filter to apply');
        }

        if(!in_array($filter, [
            self::FILTER_NEGATE,
            self::FILTER_GRAYSCALE,
            self::FILTER_BRIGTHNESS,
            self::FILTER_CONTRAST,
            self::FILTER_COLORIZE,
            self::FILTER_EDGEDETECT,
            self::FILTER_EMBOSS,
            self::FILTER_GAUSSIAN_BLUR,
            self::FILTER_SELECTIVE_BLUR,
            self::FILTER_MEAN_REMOVAL,
            self::FILTER_SMOOTH,
            self::FILTER_PIXELATE,
            self::FILTER_SEPIA,
            self::FILTER_ENHANCE,
            self::FILTER_EQUALIZE,
            self::FILTER_AUTO_LEVEL,
            self::FILTER_MOTION_BLUR,
            self::FILTER_OIL_PAINT,
            self::FILTER_POSTERIZE,
            self::FILTER_RADIAL_BLUR,
            self::FILTER_SEGMENT,
            self::FILTER_SIGMOIDAL_CONTRAST,
            self::FILTER_SKETCH,
            self::FILTER_SOLARIZE,
            self::FILTER_SPREAD,
            self::FILTER_THRESHOLD,
            self::FILTER_BLACK_THRESHOLD,
            self::FILTER_WAVE,
            self::FILTER_VIGNETTE,
            self::FILTER_SWIRL,
            self::FILTER_NOISE,
            self::FILTER_BLUE_SHIFT,
            self::FILTER_CHARCOAL,
            self::FILTER_GAMMA,
            self::FILTER_BLUR
        ])) {
            throw new Exception('Invalid filter');
        }

        $this->addModifier(self::MODIFIER_FILTER, [$filter, implode(':', $params)]);
        if($this->imageType==self::IMAGE_TYPE_FAKE) {
            return $this;
        }

        if(in_array($filter, [self::FILTER_MEAN_REMOVAL, self::FILTER_SMOOTH])) {
            $this->convertObjectTypeToGd();
        }

        if($filter>=self::FILTER_SEPIA) {
            $this->convertObjectTypeToImagick();
        }

        $this->refreshImageObject();

        if($this->imageObjectType==self::IMAGE_OBJECT_TYPE_GD) {
            call_user_func_array('imagefilter', array_merge([$this->image, $filter], $params));
            $this->imageObject = null;
            $this->modified = true;
        }

        if($this->imageObjectType==self::IMAGE_OBJECT_TYPE_IMAGICK) {
            switch($filter) {
                case self::FILTER_NEGATE :
                    $this->image->negateImage(false);
                    break;

                case self::FILTER_GRAYSCALE :
                    $this->image->setImageColorspace(\Imagick::COLORSPACE_GRAY);
                    break;

                case self::FILTER_BRIGTHNESS :
                    if(!method_exists($this->image, 'brightnessContrastImage')) {
                        throw new Exception('brightnessContrastImage is only available in ImageMagick 3.3.0+');
                    }
                    $this->image->brightnessContrastImage($params[0]);
                    break;

                case self::FILTER_CONTRAST :
                    if(!method_exists($this->image, 'brightnessContrastImage')) {
                        throw new Exception('brightnessContrastImage is only available in ImageMagick 3.3.0+');
                    }
                    $this->image->brightnessContrastImage(0, $params[1]);
                    break;

                case self::FILTER_COLORIZE :
                    $this->image->colorizeImage(Color::create($params)->getHex(), 1);
                    break;

                case self::FILTER_EDGEDETECT:
                    $this->applyFilter(self::FILTER_GRAYSCALE);
                    $this->image->edgeImage(0.5);
                    break;

                case self::FILTER_EMBOSS:
                    $this->applyFilter(self::FILTER_GRAYSCALE);
                    $this->image->embossImage(3, 1);
                    break;

                case self::FILTER_GAUSSIAN_BLUR:
                    $this->image->gaussianBlurImage(1, 1);
                    break;

                case self::FILTER_SELECTIVE_BLUR:
                    $this->image->adaptiveBlurImage(1, .5); // #ikr
                    break;

                case self::FILTER_PIXELATE:
                    $width = $this->width;
                    $height = $this->height;
                    $this->image->adaptiveResizeImage($width/$params[0], $height/$params[0]);
                    $this->image->resizeImage($width, $height,\Imagick::FILTER_BOX, 1);
                    break;

                case self::FILTER_SEPIA:
                    $this->image->sepiaToneImage($params[0]);
                    break;

                case self::FILTER_ENHANCE:
                    $this->image->enhanceImage();
                    break;

                case self::FILTER_EQUALIZE:
                    $this->image->equalizeImage();
                    break;

                case self::FILTER_AUTO_LEVEL:
                    $this->image->autoLevelImage();
                    break;

                case self::FILTER_MOTION_BLUR:
                    $this->image->motionBlurImage($params[0], $params[1], $params[2]);
                    break;

                case self::FILTER_OIL_PAINT:
                    $this->image->oilPaintImage($params[0]);
                    break;

                case self::FILTER_POSTERIZE:
                    $this->image->posterizeImage($params[0], false);
                    break;

                case self::FILTER_RADIAL_BLUR:
                    $this->image->radialBlurImage($params[0]);
                    break;

                case self::FILTER_SEGMENT:
                    $this->image->segmentImage(\Imagick::COLORSPACE_RGB, $params[0], $params[1]);
                    break;

                case self::FILTER_SIGMOIDAL_CONTRAST:
                    $this->image->sigmoidalContrastImage($params[0], $params[1], $params[2]);
                    break;

                case self::FILTER_SKETCH:
                    $this->image->sketchImage($params[0], $params[1], $params[2]);
                    break;

                case self::FILTER_SOLARIZE:
                    $this->image->solarizeImage($params[0]);
                    break;

                case self::FILTER_SPREAD:
                    $this->image->spreadImage($params[0]);
                    break;

                case self::FILTER_THRESHOLD:
                    $this->image->whiteThresholdImage(Color::create($params)->getHex());
                    break;

                case self::FILTER_BLACK_THRESHOLD:
                    $this->image->blackThresholdImage(Color::create($params)->getHex());
                    break;

                case self::FILTER_WAVE:
                    $this->image->waveImage($params[0], $params[1]);
                    break;

                case self::FILTER_VIGNETTE:
                    $this->image->vignetteImage($params[0], $params[1], $params[2], $params[3]);
                    break;

                case self::FILTER_SWIRL:
                    $this->image->swirlImage($params[0]);
                    break;

                case self::FILTER_NOISE:
                    $this->image->addNoiseImage($params[0]);
                    break;

                case self::FILTER_BLUE_SHIFT:
                    $this->image->blueShiftImage($params[0]);
                    break;

                case self::FILTER_CHARCOAL:
                    $this->image->charcoalImage($params[0], $params[1]);
                    break;

                case self::FILTER_GAMMA:
                    $this->image->gammaImage($params[0]);
                    break;

                case self::FILTER_BLUR:
                    $this->image->blurImage($params[0], $params[1]);
                    break;

                default:
                    break;
            }

            $this->imageObject = null;
            $this->modified = true;
        }

        return $this;
    }

    public function doRotate($angle=null)
    {
        if(is_null($angle)) {
            throw new Exception('What\'s your angle?');
        }

        $angleValue = floatval($angle);

        if(empty($angleValue)) {
            throw new Exception('This is a strange angle: '.$angle);
        }

        $angle = $angleValue;

        if($angle>=360) {
            throw new Exception('Angle should be smaller than 360');
        }

        if($angle<=-360) {
            throw new Exception('Angle should be larger than -360');
        }

        $this->addModifier(self::MODIFIER_ROTATE, [$angle]);
        if($this->imageType==self::IMAGE_TYPE_FAKE) {
            return $this;
        }

        $this->refreshImageObject();

        switch ($this->imageObjectType) {
            case self::IMAGE_OBJECT_TYPE_GD:
                $image = imagerotate($this->image, -$angle, 0);
                imagedestroy($this->image);
                $this->image = $image;
                $this->imageType = self::IMAGE_TYPE_GD;
                break;

            case self::IMAGE_OBJECT_TYPE_IMAGICK:

                $this->image->rotateImage(new \ImagickPixel('#00000000'), $angle);
                $this->imageType = self::IMAGE_TYPE_IMAGICK;
                break;

            default:
                break;
        }

        $this->processImageSizes();
        $this->getImageObject();

        return $this;
    }

    public function autoRotate()
    {
        if (!empty($this->exif['Orientation'])) {
            switch ($this->exif['Orientation']) {
                case 3:
                    $this->doRotate(180);
                    break;

                case 6:
                    $this->doRotate(90);
                    break;

                case 8:
                    $this->doRotate(-90);
                    break;
            }
        }
    }

    public function doFlip($flipType=null)
    {
        if(is_null($flipType)) {
            throw new Exception('No flip type specified');
        }

        if(!in_array($flipType, [
            self::FLIP_HORIZONTAL,
            self::FLIP_VERTICAL,
            self::FLIP_BOTH
        ])) {
            throw new Exception('Invalid flip type');
        }

        $this->addModifier(self::MODIFIER_FLIP, [$flipType]);
        if($this->imageType==self::IMAGE_TYPE_FAKE) {
            return $this;
        }

        $this->refreshImageObject();

        switch ($this->imageObjectType) {
            case self::IMAGE_OBJECT_TYPE_GD:
                imageflip($this->image, $flipType);
                $this->imageType = self::IMAGE_TYPE_GD;
                break;

            case self::IMAGE_OBJECT_TYPE_IMAGICK:
                if(in_array($flipType, [self::FLIP_HORIZONTAL, self::FLIP_BOTH])) {
                    $this->image->flopImage();
                }

                if(in_array($flipType, [self::FLIP_VERTICAL, self::FLIP_BOTH])) {
                $this->image->flipImage();
                }

                break;

            default:
                break;
        }

        $this->getImageObject();

        return $this;
    }

    public function getExifInfo()
    {
        if($this->type == Image::IMAGE_TYPE_FILE and isset($this->imagePath) and !empty($this->imagePath)) {
            if(function_exists('exif_read_data')) {
                try {
                    $this->exif = exif_read_data($this->imagePath);
                } catch (\ErrorException $e) {
                    return false;
                }
            }
        }
    }

    public function getHash()
    {
        if(is_null($this->hash)) {
            $this->getImageObject();
            $this->hash = md5($this->getImageContent('jpeg', '0'));
        }
        return $this->hash;
    }

    public function forceHash($hash=null)
    {
        if(is_null($hash)) {
            throw new Exception('The hash cannot be empty');
        }

        if(strlen($hash)!=32) {
            throw new Exception('The hash must have 32 characters');
        }

        $this->hash = $hash;
        return $this;
    }

    public function rehash()
    {
        $this->refreshImageObject();
        $this->hash = md5($this->getImageContent('jpeg', '0'));
        $this->modifiers = [];
        return $this;
    }

    public function serializeDetails()
    {
        return [
            'width'=>$this->width,
            'height'=>$this->height,
            'exif'=>$this->exif,
            'hash'=>$this->getHash()
        ];
    }

    public function serializeAnalysis($analysisOptions=array())
    {
        $analysis = $this->getAnalysis($analysisOptions);

        return [
            'luma'=>$analysis->luma,
            'histogram'=>$analysis->histogram->toArray(),
            'colors'=>$analysis->getColors(),
        ];
    }

    public function serializeComplete($allowedParams = ['width', 'height', 'hash', 'luma', 'exif', 'colors', 'imageObjectType'], $analysisOptions=array())
    {
        $information = array_merge($this->serializeDetails(), $this->serializeAnalysis());
        return array_intersect_key($information, array_flip($allowedParams));
    }

    public function getAnalysis($analysisOptions=array())
    {
        return Analyze::getAnalysis($this, $analysisOptions);
    }
}

