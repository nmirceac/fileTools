<?php namespace ColorTools;

class Color
{
    private $value = null;
    private $name = null;
    private $details = [];
    private $similarColor = null;

    const COMPARE_FAST   = 1;
    const COMPARE_NORMAL = 2;
    const COMPARE_GREAT  = 3;

    public function __construct($color=0, $param1=null, $param2=null)
    {
        if(!is_integer($color) and empty($color)) {
            throw new Exception('There is nothing here');
        }

        switch(gettype($color)) {
            case 'resource':
                if(get_resource_type($color)=='gd') {
                    /*
                     * $color is a gd image - in that case we also need the X, Y coordinates of the pixel
                     * we want to analyze
                     */
                    if((is_int($param1) and $param1>=0) and (is_int($param2) and $param2>=0)) {
                        /*
                         * for images without an alpha channels, it's better to just use the int value returned by
                         * imagecoloarat(), instead of using imagecolorsforindex() functions for getting rgb values
                         * as it is considerably faster (much faster)
                         *
                         * the slow way is
                         * $this->setRgb(imagecolorsforindex($color, imagecolorat($color, $param1, $param2)));
                         * imagecolorat(...) will return something like
                         * [["red"]=> int(119), ["green"]=> int(123), ["blue"]=> int(180), ["alpha"]=> int(127)]
                         * but my support for alpha is non existent at the moment
                         *
                         */
                        $this->value = imagecolorat($color, $param1, $param2);
                        if($this->value === false) {
                            throw new Exception('Pixel out of bounds');
                        }
                    } else {
                        /*
                         * missing or invalid $param1 and $param2
                         */
                        throw new Exception('Missing pixel coordinates');
                    }
                } else {
                    throw new Exception('Unknown resource of type '.get_resource_type($color));
                }
                break;

            case 'array':
                if(isset($color['r']) and isset($color['g']) and isset($color['b'])) {
                    $red=$color['r'];
                    $green=$color['g'];
                    $blue=$color['b'];
                } else if(isset($color['red']) and isset($color['green']) and isset($color['blue'])) {
                    $red=$color['red'];
                    $green=$color['green'];
                    $blue=$color['blue'];
                } else if(isset($color[0]) and isset($color[1]) and isset($color[2]) and count($color) <= 4) {
                    $red=$color[0];
                    $green=$color[1];
                    $blue=$color[2];
                } else {
                    throw new Exception('I cannot make sense of this array, sorry...');
                }

                $this->value = 0;
                $this->setRed($red);
                $this->setGreen($green);
                $this->setBlue($blue);

                break;

            case 'string':
                $color=trim($color, "# \r\n\t");

                if(ctype_xdigit($color)) {
                    $this->setHex($color);
                } else if(strpos($color, 'hsl')!==false and strpos($color, ',')!==false) {
                    $originalString = $color;
                    $color = trim(str_replace(array('hsla', 'hsl', '(', ')'), '', $color), "\r\n\t ");
                    if(strpos($color, ',')!==false) {
                        $color = str_replace(' ', '', $color);
                        $color = explode(',', $color);
                        if(count($color)==3 or count($color)==4) {
                            $this->setHsl($color[0], $color[1], $color[2]);
                        } else {
                            throw new Exception('Can\'t really understand this HSL string: '.$originalString);
                        }
                    }
                } else if(strpos($color, 'hsv')!==false and strpos($color, ',')!==false) {
                    $originalString = $color;
                    $color = trim(str_replace(array('hsva', 'hsv', '(', ')'), '', $color), "\r\n\t ");
                    if(strpos($color, ',')!==false) {
                        $color = str_replace(' ', '', $color);
                        $color = explode(',', $color);
                        if(count($color)==3 or count($color)==4) {
                            $this->setHsv($color[0], $color[1], $color[2]);
                        } else {
                            throw new Exception('Can\'t really understand this HSV string: '.$originalString);
                        }
                    }
                } else if(strpos($color, 'cmyk')!==false and strpos($color, ',')!==false) {
                    $originalString = $color;
                    $color = trim(str_replace(array('cmyk', '(', ')'), '', $color), "\r\n\t ");
                    if(strpos($color, ',')!==false) {
                        $color = str_replace(' ', '', $color);
                        $color = explode(',', $color);
                        if(count($color)==4) {
                            $this->setCmyk($color[0], $color[1], $color[2], $color[3]);
                        } else {
                            throw new Exception('Can\'t really understand this CMYK string: '.$originalString);
                        }
                    }
                } else if(strpos($color, 'rgb')!==false or strpos($color, ',')!==false) {
                    // i hope this is some sort of rgb(r,g,b) kinda string, or maybe even rgba(r,g,b,a) - ignoring a
                    $color = trim(str_replace(array('rgba', 'rgb', '(', ')'), '', $color), "\r\n\t ");
                    if(strpos($color, ',')!==false) {
                        $color = str_replace(' ', '', $color);
                        $color = explode(',', $color);
                        if(count($color)==3 or count($color)==4) {
                            if(max($color)>255) {
                                throw new Exception('If this is rgb, one of the channels is over 255...');
                            }
                            $this->value = intval($color[0]) * 256*256 + intval($color[1]) * 256 + intval($color[2]);
                        }
                    }
                } else if(isset($this->cssColors[strtolower($color)])) {
                    $this->name = $color;
                    $this->value = $this->cssColors[strtolower($color)];
                } else if(isset($this->allColors[$color])) {
                    $color = $this->allColors[$color];
                    $this->name = $color;
                    if(is_array($color)) {
                        $this->value = $color[0];
                        if(isset($color[1]['url'])) {
                            $this->details['url'] = $color[1]['url'];
                        }
                    } else {
                        $this->value = $color;
                    }

                } else {
                    throw new Exception('I really don\'t know what this "'.print_r($color, true).'" is.');
                }



                break;

            case 'integer':
                if((is_int($param1) and $param1>=0) and (is_int($param2) and $param2>=0)) {
                    $this->setRgb($color, $param1, $param2);
                } else {
                    $this->setHex($color);
                }

                break;

            case 'object':
                if(get_class($color) == 'ColorTools\Color') {
                    $this->value = $color->int;
                } else if(get_class($color) == 'Imagick') {
                    /*
                     * $color is a Imagick image - in that case we also need the X, Y coordinates of the pixel
                     * we want to analyze
                     */
                    if((is_int($param1) and $param1>=0) and (is_int($param2) and $param2>=0)) {
                        /*
                         * No way go get an int out of a ImagickPixel object.
                         * If you know an efficient and elegant way (without using getColour()), please let me know.
                         */
                        try {
                            $this->setRgb($color->getImagePixelColor($param1, $param2)->getColor());
                        } catch (\ImagickException $e) {
                            throw new Exception('Problem getting the Imagick pixel: '.$e->getMessage());
                        }
                    } else {
                        /*
                         * missing or invalid $param1 and $param2
                         */
                        throw new Exception('Missing pixel coordinates');
                    }
                } else if(get_class($color) == 'ImagickPixel') {
                    $this->setRgb($color->getColor());
                } else {
                    throw new Exception('Cannot handle object of type '.get_class($color));
                }

                break;

            default:
                throw new Exception('I really don\'t know what that color is');
                break;
        }
    }

    public function __toString() {
        return $this->getHex();
    }

    public function __get($param) {
        $param=strtolower($param);

        if($param == 'csscolors') {
            return $this->getCssColors();
        }

        if($param == 'allcolors') {
            return $this->getAllColors();
        }

        if($param == 'hex') {
            return $this->getHex();
        }

        if($param == 'safe' or $param == 'safeHex') {
            return $this->getSafeHex();
        }

        if($param == 'rgb') {
            $rgb = $this->getRgb();
            return 'rgb('.implode(', ', $rgb).')';
        }

        if($param == 'red' or $param == 'r') {
            return $this->getRed();
        }

        if($param == 'green' or $param == 'g') {
            return $this->getGreen();
        }

        if($param == 'blue' or $param == 'b') {
            return $this->getBlue();
        }

        if(in_array($param, ['grayscale', 'gray', 'mono'])) {
            return $this->getGrayscale();
        }

        if($param=='luma') {
            return round($this->getLuma()*100).'%';
        }

        if($param == 'hsl') {
            $hsl = $this->getHsl();
            $hsl['saturation']=round($hsl['saturation']*100, 2).'%';
            $hsl['lightness']=round($hsl['lightness']*100, 2).'%';
            return 'hsl('.implode(', ', $hsl).')';
        }

        if($param == 'hsv') {
            $hsv = $this->getHsv();
            $hsv['saturation']=round($hsv['saturation']*100, 2).'%';
            $hsv['value']=round($hsv['value']*100, 2).'%';
            return 'hsv('.implode(', ', $hsv).')';
        }

        if($param == 'cmyk') {
            $cmyk = $this->getCmyk();
            foreach($cmyk as $channel=>$value) {
                $cmyk[$channel] = round($value*100, 2).'%';
            }
            return 'cmyk('.implode(', ', $cmyk).')';
        }

        if($param == 'int') {
            return $this->value;
        }

        if($param == 'name') {
            return $this->getName();
        }

        if($param == 'details') {
            return $this->details;
        }

        if(isset($this->details[$param])) {
            return $this->details[$param];
        }

        if($param == 'fullname') {
            $this->getFullName();
        }

        if($param == 'url') {
            if(!isset($this->details['color'])) {
                $this->details['color'] = $this->findSimilar(null, $this->allColors);
            }

            if(isset($this->details['color']->details['url'])) {
                $this->details['url'] = $this->details['color']->details['url'];
                return $this->details['url'];
            } else {
                return null;
            }
        }

        /*
         * custom property that wasn't found in $this->details
         */
        if(substr($param, 0, 1)=='_') {
            return null;
        }

        throw new Exception('Cannot find property "'.$param.'"".');
    }

    public function __set($param, $value) {
        $param=strtolower($param);

        if($param == 'name') {
            $this->name = $value;
        } else if($param == 'details') {
            $this->details = $value;
        } else if($param == 'r' or $param=='red') {
            $this->setRed($value);
        } else if($param == 'g' or $param=='green') {
            $this->setGreen($value);
        } else if($param == 'b' or $param=='blue') {
            $this->setBlue($value);
        } else if(substr($param, 0, 1)=='_') {
            $this->details[$param] = $value;
        } else {
            throw new Exception('What are you trying to do here with "'.$param.'""?');
        }
    }

    public static function create($color=0, $param1=null, $param2=null)
    {
        return new Color($color, $param1, $param2);
    }

    public function resetAttributes()
    {
        $this->similarColor = null;
        $this->name = null;
        $this->details= [];
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getHex()
    {
        return '#'.str_pad(dechex($this->value), 6, '0', STR_PAD_LEFT);
    }

    public function setHex($value)
    {
        switch (gettype($value)) {
            case 'string' :
                $color=trim($value, "# \r\n\t");
                if(ctype_xdigit($color)) {
                    if(strlen($color) == 3) {
                        $color = $color{0}.$color{0}.$color{1}.$color{1}.$color{2}.$color{2};
                    }

                    if(strlen($color) < 6) {
                        $color = str_pad($color, 6, '0', STR_PAD_LEFT);
                    }

                    if(strlen($color) == 6) {
                        $this->value = hexdec($color);
                    } else {
                        throw new Exception('Not sure what this hex string is "'.$value.'", please let me know');
                    }
                } else {
                    throw new Exception('This string is not hex "'.$value.'".');
                }

                break;

            case 'integer' :
                // checking if the integer (hex) is in range
                if($value>=0 and $value<=0xffffff) {
                    $this->value = $value;
                } else {
                    throw new Exception('This integer is out of range');
                }
                break;

            default :
                throw new Exception('I have no idea what the HEX this is: '.print_r($value, true));
                break;
        }

        return $this;
    }

    public function getSafeHex()
    {
        return '#'.dechex(round($this->red/16)).dechex(round($this->green/16)).dechex(round($this->blue/16));
    }

    public function getRed()
    {
        return ($this->value >> 16) & 0xFF;
    }

    public function setRed($value)
    {
        if(is_numeric($value) and $value >= 0 and $value<256) {
            $this->value += (ceil($value) - $this->red) * 256 * 256;
            return $this;
        } else {
            throw new Exception('There is something wrong with this red: '.print_r($value, true));
        }
    }

    public function getGreen()
    {
        return $this->value >> 8 & 0xFF;
    }

    public function setGreen($value)
    {
        if(is_numeric($value) and $value >= 0 and $value<256) {
            $this->value += (ceil($value) - $this->green) * 256;
            return $this;
        } else {
            throw new Exception('There is something wrong with this green: '.print_r($value, true));
        }
    }

    public function getBlue()
    {
        return $this->value & 0xFF;
    }

    public function setBlue($value)
    {
        if(is_numeric($value) and $value >= 0 and $value<256) {
            $this->value += (ceil($value) - $this->blue);
            return $this;
        } else {
            throw new Exception('There is something wrong with this blue: '.print_r($value, true));
        }
    }

    public function getRgb()
    {
        $rgb['red']=$this->getRed();
        $rgb['green']=$this->getGreen();
        $rgb['blue']=$this->getBlue();
        return $rgb;
    }

    public function setRgb($red = null, $green = null, $blue = null)
    {
        if(is_array($red) and isset($red['r']) and isset($red['g']) and isset($red['b'])) {
            $this->setRed($red['r']);
            $this->setGreen($red['g']);
            $this->setBlue($red['b']);
        } else if(is_array($red) and isset($red['red']) and isset($red['green']) and isset($red['blue'])) {
            $this->setRed($red['red']);
            $this->setGreen($red['green']);
            $this->setBlue($red['blue']);
        } else if(is_array($red) and (isset($red[0]) and isset($red[1]) and isset($red[2]) and count($red) <= 4)) {
            $this->setRed($red[0]);
            $this->setGreen($red[1]);
            $this->setBlue($red[2]);
        } else {
            if(!is_null($red)) {
                $this->setRed($red);
            }

            if(!is_null($green)) {
                $this->setGreen($green);
            }

            if(!is_null($blue)) {
                $this->setBlue($blue);
            }
        }

        return $this;
    }

    public function getHsl()
    {
        $r = $this->r / 255;
        $g = $this->g / 255;
        $b = $this->b / 255;


        $cMax = max($r, $g, $b);
        $cMin = min($r, $g, $b);
        $cDif = $cMax - $cMin;

        $lightness = ($cMin + $cMax) / 2;

        if($cDif == 0) {
            $hue = 0;
            $saturation = 0;
        } else if($cMax == $r) {
            $hue = ($g - $b)/$cDif;
            if($hue < 0) {
                $hue = 6 + $hue;
            }
            $hue = deg2rad(60) * $hue;
        } else if($cMax == $g) {
            $hue = deg2rad(60) * (($b - $r)/$cDif + 2);
        } else if($cMax == $b) {
            $hue = deg2rad(60) * (($r - $g)/$cDif + 4);
        }

        if($cDif != 0) {
            $saturation = $cDif / (1 - abs(2 * $lightness - 1));
        }

        return ['hue'=> (int) round(rad2deg($hue)), 'saturation'=>round($saturation, 2), 'lightness'=>round($lightness, 2)];
    }

    public function setHsl($hue=null, $saturation=null, $lightness=null)
    {
        if(is_array($hue)) {
            /*
             * imagemagick uses for some reason the term "luminosity" instead of "ligthness"
             * check yourself:
             * http://php.net/manual/en/imagickpixel.gethsl.php
             * ..."Returns the HSL value in an array with the keys 'hue', 'saturation', and 'luminosity'"...
             * but, according to most people (https://en.wikipedia.org/wiki/HSL_and_HSV),
             * "HSL stands for hue, saturation, and lightness, and is also often called HLS".
             * I'm going to accept both lightness and luminosity. Tolerance is the key to a better world!
             */

            if(isset($hue['hue']) and isset($hue['saturation']) and (isset($hue['lightness']) or isset($hue['luminosity']))) {
                $hsl = $hue;
                if(isset($hue['lightness'])) {
                    $hsl['lightness'] = $hue['lightness'];
                } else if(isset($hue['lightness'])) {
                    $hsl['lightness'] = $hue['luminosity'];
                }

            } else if (isset($hue['h']) and isset($hue['s']) and isset($hue['l'])) {
                $hsl['hue'] = $hue['h'];
                $hsl['saturation'] = $hue['s'];
                $hsl['lightness'] = $hue['l'];
            } else {
                throw new Exception('Don\'t understand this hsl array: '.print_r($hue, true));
            }
        } else if (!is_null($hue) and !is_null($saturation) and !is_null($lightness)) {
            $hsl['hue'] = $hue;
            $hsl['saturation'] = $saturation;
            $hsl['lightness'] = $lightness;
        } else {
            throw new Exception('Can\'t get this HSL');
        }

        if(strpos($hsl['saturation'],'%') or $hsl['saturation'] > 1) {
            $hsl['saturation'] = trim($hsl['saturation'],"\r\n\t %") / 100;
        }

        if(strpos($hsl['lightness'],'%') or $hsl['lightness'] > 1) {
            $hsl['lightness'] = trim($hsl['lightness'],"\r\n\t %") / 100;
        }

        if($hsl['hue'] < 0 or $hsl['hue'] > 360) {
            throw new Exception('This hue is out of range (0 - 360): '.$hsl['hue']);
        }

        if($hsl['saturation'] < 0 or $hsl['saturation'] > 1) {
            throw new Exception('This saturation is out of range (0 - 1): '.$hsl['saturation']);
        }

        if($hsl['lightness'] < 0 or $hsl['lightness'] > 1) {
            throw new Exception('This lightness is out of range (0 - 1): '.$hsl['lightness']);
        }

        $c = (1 - abs(2*$hsl['lightness'] - 1)) * $hsl['saturation'];
        $x = $c * (1 - abs(fmod(deg2rad($hsl['hue']) / deg2rad(60), 2) - 1));
        $m = $hsl['lightness'] - $c/2;

        if($hsl['hue'] < 60) {
            $r = $c;
            $g = $x;
            $b = 0;
        } else if($hsl['hue'] < 120) {
            $r = $x;
            $g = $c;
            $b = 0;
        } else if($hsl['hue'] < 180) {
            $r = 0;
            $g = $c;
            $b = $x;
        } else if($hsl['hue'] < 240) {
            $r = 0;
            $g = $x;
            $b = $c;
        } else if($hsl['hue'] < 300) {
            $r = $x;
            $g = 0;
            $b = $c;
        } else {
            $r = $c;
            $g = 0;
            $b = $x;
        }

        $r = round(($r+$m) * 255);
        $g = round(($g+$m) * 255);
        $b = round(($b+$m) * 255);

        $this->setRed($r);
        $this->setGreen($g);
        $this->setBlue($b);

        return $this;
    }

    public function getHsv()
    {
        $r = $this->r / 255;
        $g = $this->g / 255;
        $b = $this->b / 255;


        $cMax = max($r, $g, $b);
        $cMin = min($r, $g, $b);
        $cDif = $cMax - $cMin;

        if($cDif==0) {
            $hue = 0;
        } else if ($cMax == $r) {
            $hue = deg2rad(60) * fmod(($g - $b)/$cDif, 6);
            if($hue<0) {
                $hue+=2*M_PI;
            }
        } else if ($cMax == $g) {
            $hue = deg2rad(60) * ((($b - $r)/$cDif) + 2);
        } else if ($cMax == $b) {
            $hue = deg2rad(60) * ((($r - $g)/$cDif) + 4);
        }

        if($cMax==0) {
            $saturation=0;
        } else {
            $saturation = $cDif / $cMax;
        }

        $value = $cMax;
        return ['hue'=> (int) round(rad2deg($hue)), 'saturation'=>round($saturation, 2), 'value'=>round($value, 2)];
    }

    public function setHsv($hue=null, $saturation=null, $value=null)
    {
        if(is_array($hue)) {
            if(isset($hue['hue']) and isset($hue['saturation']) and isset($hue['value'])) {
                $hsv = $hue;
            } else if (isset($hue['h']) and isset($hue['s']) and isset($hue['v'])) {
                $hsv['hue'] = $hue['h'];
                $hsv['saturation'] = $hue['s'];
                $hsv['value'] = $hue['v'];
            } else {
                throw new Exception('Don\'t understand this HSV array: '.print_r($hsv, true));
            }
        } else if (!is_null($hue) and !is_null($saturation) and !is_null($value)) {
            $hsv['hue'] = $hue;
            $hsv['saturation'] = $saturation;
            $hsv['value'] = $value;
        } else {
            throw new Exception('Can\'t get this HSV');
        }

        if(strpos($hsv['saturation'],'%') or $hsv['saturation'] > 1) {
            $hsv['saturation'] = trim($hsv['saturation'],"\r\n\t %") / 100;
        }

        if(strpos($hsv['value'],'%') or $hsv['value'] > 1) {
            $hsv['value'] = trim($hsv['value'],"\r\n\t %") / 100;
        }

        if($hsv['hue'] < 0 or $hsv['hue'] > 360) {
            throw new Exception('This hue is out of range (0 - 360): '.$hsv['hue']);
        }

        if($hsv['saturation'] < 0 or $hsv['saturation'] > 1) {
            throw new Exception('This saturation is out of range (0 - 1): '.$hsv['saturation']);
        }

        if($hsv['value'] < 0 or $hsv['value'] > 1) {
            throw new Exception('This value is out of range (0 - 1): '.$hsv['value']);
        }

        $c = $hsv['value'] * $hsv['saturation'];
        $x = $c * (1 - abs(fmod($hsv['hue'] / 60, 2) - 1));
        $m = $hsv['value'] - $c;

        if($hsv['hue'] < 60) {
            $r = $c;
            $g = $x;
            $b = 0;
        } else if($hsv['hue'] < 120) {
            $r = $x;
            $g = $c;
            $b = 0;
        } else if($hsv['hue'] < 180) {
            $r = 0;
            $g = $c;
            $b = $x;
        } else if($hsv['hue'] < 240) {
            $r = 0;
            $g = $x;
            $b = $c;
        } else if($hsv['hue'] < 300) {
            $r = $x;
            $g = 0;
            $b = $c;
        } else {
            $r = $c;
            $g = 0;
            $b = $x;
        }

        $r = round(($r+$m) * 255);
        $g = round(($g+$m) * 255);
        $b = round(($b+$m) * 255);

        $this->setRed($r);
        $this->setGreen($g);
        $this->setBlue($b);

        return $this;
    }

    public function getCmyk()
    {
        $rgb = $this->getRgb();

        $r = $rgb['red']/255;
        $g = $rgb['green']/255;
        $b = $rgb['blue']/255;
        $max = max($r, $g, $b);

        $k = 1 - $max;
        if($max==0) {
            $c = $m = $y = 0;
        } else {
            $c = ($max - $r) / $max;
            $m = ($max - $g) / $max;
            $y = ($max - $b) / $max;
        }


        return ['cyan'=>$c, 'magenta'=>$m, 'yellow'=>$y, 'black'=>$k];
    }

    public function setCmyk($cyan=null, $magenta=null, $yellow=null, $black=null)
    {
        if(is_array($cyan)) {
            if(isset($cyan['cyan']) and isset($cyan['magenta']) and isset($cyan['yellow'])) {
                $cmyk=$cyan;
            } else if (isset($cyan['c']) and isset($cyan['m']) and isset($cyan['y']) and isset($cyan['k'])) {
                $cmyk['cyan'] = $cyan['c'];
                $cmyk['magenta'] = $cyan['m'];
                $cmyk['yellow'] = $cyan['y'];
                $cmyk['black'] = $cyan['black'];
            } else {
                throw new Exception('Don\'t understand this CMYK array: '.print_r($cyan, true));
            }
        } else if (!is_null($cyan) and !is_null($magenta) and !is_null($yellow) and !is_null($black)) {
            $cmyk['cyan'] = $cyan;
            $cmyk['magenta'] = $magenta;
            $cmyk['yellow'] = $yellow;
            $cmyk['black'] = $black;
        } else {
            throw new Exception('Can\'t get this CMYK');
        }

        foreach($cmyk as $channel=>$value) {
            if(strpos($value, '%')!==false or $value>1) {
                $cmyk[$channel] = trim($value,"\r\n\t %")/100;
            } else {
                $cmyk[$channel] = trim($value,"\r\n\t ");
            }

            if(!is_numeric($cmyk[$channel])) {
                throw new Exception('This CMYK\'s '.$channel.' is invalid: '.$cmyk[$channel]);
            }

            if($cmyk[$channel] < 0 or $cmyk[$channel] > 1) {
                throw new Exception('This CMYK\'s '.$channel.' is out of range (0-1): '.$cmyk[$channel]);
            }
        }

        $r = round(255 * (1 - $cmyk['cyan']) * (1 - $cmyk['black']));
        $g = round(255 * (1 - $cmyk['magenta']) * (1 - $cmyk['black']));
        $b = round(255 * (1 - $cmyk['yellow']) * (1 - $cmyk['black']));

        $this->setRgb($r, $g, $b);
        return $this;
    }

    public function getName()
    {
        if(is_null($this->name)) {
            /*
             * By default finding a similar Css Color
             */
            if(is_null($this->similarColor)) {
                $this->similarColor = $this->findSimilar();
            }
            $this->name = $this->similarColor->name;
        }
        return $this->name;
    }

    public function getFullName()
    {
        if(!isset($this->details['color'])) {
            $this->details['color'] = $this->findSimilar(null, $this->allColors);
        }
        $this->details['fullname'] = $this->details['color']->name;
        return $this->details['fullname'];
    }

    public function getGrayscale()
    {
        return ceil(($this->red + $this->green + $this->blue) / 3);
    }

    public function getLuma()
    {
        $luma['r'] = 0.2126 * $this->r / 255;
        $luma['g'] = 0.7152 * $this->g / 255;
        $luma['b'] = 0.0722 * $this->b / 255;
        return array_sum($luma);
    }

    public function invert()
    {
        return $this->rgbTransformation(function($value) {
            return 1 - $value;
        });
    }

    public function complement()
    {
        return $this->spin(180)->resetAttributes();
    }

    public function triad($count=1)
    {
        $spin = 360 / 3 * $count;
        return $this->spin($spin)->resetAttributes();
    }

    public function tetrad($count=1)
    {
        $spin = 360 / 4 * $count;
        return $this->spin($spin)->resetAttributes();
    }

    public function mix($secondColor, $weight=0.5)
    {
        if($weight>=1) { //not sure if no one will ever mix 100%
            $weight/=100;
        }

        $weight=min($weight, 1);

        if($weight<0) {
            $weight=0;
        }

        return $this->rgbTransformation(function($value, $secondValue) use ($weight) {
            return $value * (1 - $weight) + $secondValue * $weight;
        }, $secondColor);
    }

    public function tint($weight=0.1)
    {
        return $this->mix(0xffffff, $weight);
    }

    public function shade($weight=0.1)
    {
        return $this->mix(0, $weight);
    }

    public function grayscale()
    {
        return $this->desaturate(100);
    }

    public function spin($hueAngle=0)
    {
        $hsl = $this->getHsl();
        $hsl['hue'] += $hueAngle;
        while($hsl['hue'] < 0) {
            $hsl['hue']+= 360;
        }
        $hsl['hue'] = $hsl['hue'] % 360;
        $this->setHsl($hsl)->resetAttributes();
        return $this;
    }

    public function saturate($saturationAdjustement=0)
    {
        $hsl = $this->getHsl();
        if($saturationAdjustement>=1 or $saturationAdjustement<=-1) { //not sure if no one will ever [de]saturate 100%
            $saturationAdjustement/=100;
        }
        $hsl['saturation'] += $saturationAdjustement;

        if($hsl['saturation']>1) {
            $hsl['saturation']=1;
        }
        if($hsl['saturation']<0) {
            $hsl['saturation']=0;
        }

        $this->setHsl($hsl)->resetAttributes();
        return $this;
    }

    public function desaturate($saturationAdjustement=0)
    {
        return $this->saturate(0-$saturationAdjustement);
    }

    public function lighten($lightnessAdjustement=0)
    {
        $hsl = $this->getHsl();
        if($lightnessAdjustement>=1 or $lightnessAdjustement<=-1) { //not sure if no one will ever [de]lighten 100%
            $lightnessAdjustement/=100;
        }
        $hsl['lightness'] += $lightnessAdjustement;

        if($hsl['lightness']>1) {
            $hsl['lightness']=1;
        }
        if($hsl['lightness']<0) {
            $hsl['lightness']=0;
        }

        $this->setHsl($hsl)->resetAttributes();
        return $this;
    }

    public function darken($lightnessAdjustement=0)
    {
        return $this->lighten(0-$lightnessAdjustement);
    }

    public function rgbTransformation($transformation, $secondColor=null)
    {
        if(!is_null($secondColor)) {
            $secondColor=Color::create($secondColor);
        }
        foreach(['red', 'green', 'blue'] as $channel) {
            if(!is_null($secondColor)) {
                $value = $transformation($this -> $channel / 255, $secondColor -> $channel / 255, $channel);
            } else {
                $value = $transformation($this -> $channel / 255, $channel);
            }
            $value = min($value, 1);
            if($value<0) {
                $value = 0;
            }
            $this -> $channel = round($value * 255);
        }

        $this->resetAttributes();
        return $this;
    }

    public function multiply($secondColor)
    {
        return $this->rgbTransformation(function($value, $secondValue) {
            return $value * $secondValue;
        }, $secondColor);
    }

    public function screen($secondColor)
    {
        return $this->rgbTransformation(function($value, $secondValue) {
            return $value + $secondValue - ($value * $secondValue);
        }, $secondColor);
    }

    public function overlay($secondColor)
    {
        return $this->rgbTransformation(function($value, $secondValue) {
            $value *= 2;

            if($value<=1) {
                return $value * $secondValue;
            } else {
                $value -= 1;
                return $value + $secondValue - ($value * $secondValue);
            }
        }, $secondColor);
    }

    public function softlight($secondColor)
    {
        return $this->rgbTransformation(function($value, $secondValue) {
            $d = 1;
            $e = $value;

            if($secondValue > 0.5) {
                $e = 1;
                if($value > 0.25) {
                    $d = sqrt($value);
                } else {
                    $d = ((16 * $value - 12) * $value + 4) * $value;
                }
            }
            return $value - (1 - 2 * $secondValue) * $e * ($d - $value);
        }, $secondColor);
    }

    public function hardlight($secondColor)
    {
        return Color::create($secondColor)->overlay($this);
    }

    public function difference($secondColor)
    {
        return $this->rgbTransformation(function($value, $secondValue) {
            return abs($value - $secondValue);
        }, $secondColor);
    }

    public function exclusion($secondColor)
    {
        return $this->rgbTransformation(function($value, $secondValue) {
            return $value + $secondValue - 2 * $value * $secondValue;
        }, $secondColor);
    }

    public function average($secondColor)
    {
        return $this->rgbTransformation(function($value, $secondValue) {
            return ($value + $secondValue) / 2;
        }, $secondColor);
    }

    public function negate($secondColor)
    {
        return $this->rgbTransformation(function($value, $secondValue) {
            return 1 - abs($value + $secondValue - 1);
        }, $secondColor);
    }

    public function compare($color, $comparisonType = Color::COMPARE_FAST)
    {
        if(!$color instanceof Color) {
            $color = Color::create($color);
        }

        if($comparisonType <= 3)
        {
            return abs(pow($this->red - $color->red, $comparisonType))
            + abs(pow($this->green - $color->green, $comparisonType))
            + abs(pow($this->blue - $color->blue, $comparisonType));
        }
    }

    //https://www.w3.org/TR/WCAG20/#contrast-ratiodef
    public function findConstrast($darkColor=0x0, $lightColor=0xffffff, $threshold=50)
    {
        $darkColor=Color::create($darkColor);
        $lightColor=Color::create($lightColor);

        $darkContrast =  ($this->getLuma() > $darkColor->getLuma()) ?
                            ($this->getLuma() + 0.05) / ($darkColor->getLuma() + 0.05) :
                            ($darkColor->getLuma() + 0.05) / ($this->getLuma() + 0.05);

        $lightContrast =  ($this->getLuma() > $lightColor->getLuma()) ?
            ($this->getLuma() + 0.05) / ($lightColor->getLuma() + 0.05) :
            ($lightColor->getLuma() + 0.05) / ($this->getLuma() + 0.05);

        $lightContrast += $threshold/21;
        $darkContrast -= $threshold/21;

        if($darkContrast > $lightContrast) {
            return $darkColor;
        } else {
            return $lightColor;
        }
    }

    public function findSimilar($comparisonType = null, $collection = null, $avoidBlacks=false)
    {
        $comparisonType = (is_null($comparisonType)) ? Color::COMPARE_GREAT : $comparisonType;

        if(is_null($collection)) {
            $collection = $this->cssColors;
        }

        if($avoidBlacks)
        {
            if($this->getLuma()>0.04 and $this->getLuma()<0.16) {
                $this->lighten(ceil(5+$this->getLuma()*100));
            }
        }

        $minDiff = 0xffffff;
        $similarColor = 0x0;
        $colorName = null;
        $colorDetails = [];

        foreach($collection as $name=>$color) {
            if(!is_array($color)) {
                $details = [];
                $color=Color::create($color);
            } else {
                $details = $color[1];
                $color=Color::create($color[0]);
            }

            $diff = $this->compare($color, $comparisonType);
            if($diff < $minDiff) {
                $minDiff = $diff;
                $colorName = $name;
                $colorDetails = $details;
                $similarColor = $color;

                // stop looking if the colors are identical
                if($this->int == $similarColor->int) {
                    break;
                }
            }
        }

        if($comparisonType!=Color::COMPARE_GREAT) {
            pow(pow($minDiff, 1/$comparisonType), 3);
        }

        if($minDiff<pow(7, $comparisonType) and $this->int != $similarColor->int)
        {
            $minDiff += pow(16, $comparisonType);
        }

        $difference = ($minDiff / 0xffffff);
        $similarity = (0xffffff - $minDiff) / 0xffffff;

        $colorDetails['difference'] = round($difference * 100, 3).'%';
        $colorDetails['similarity'] = round($similarity * 100, 3).'%';
        $similarColor = Color::create($similarColor);
        $similarColor -> name = $colorName;
        $similarColor -> details = $colorDetails;
        return $similarColor;
    }







    public function getCssColors() {
        /*
         * The list of css colors
        * https://drafts.csswg.org/css-color/
        */
        return ['aliceblue'=>0xf0f8ff,'antiquewhite'=>0xfaebd7,'aqua'=>0x00ffff,'aquamarine'=>0x7fffd4,
            'azure'=>0xf0ffff,'beige'=>0xf5f5dc,'bisque'=>0xffe4c4,'black'=>0x000000,'blanchedalmond'=>0xffebcd,
            'blue'=>0x0000ff,'blueviolet'=>0x8a2be2,'brown'=>0xa52a2a,'burlywood'=>0xdeb887,'cadetblue'=>0x5f9ea0,
            'chartreuse'=>0x7fff00,'chocolate'=>0xd2691e,'coral'=>0xff7f50,'cornflowerblue'=>0x6495ed,'cornsilk'=>0xfff8dc,
            'crimson'=>0xdc143c,'cyan'=>0x00ffff,'darkblue'=>0x00008b,'darkcyan'=>0x008b8b,'darkgoldenrod'=>0xb8860b,
            'darkgray'=>0xa9a9a9,'darkgreen'=>0x006400,'darkgrey'=>0xa9a9a9,'darkkhaki'=>0xbdb76b,'darkmagenta'=>0x8b008b,
            'darkolivegreen'=>0x556b2f,'darkorange'=>0xff8c00,'darkorchid'=>0x9932cc,'darkred'=>0x8b0000,
            'darksalmon'=>0xe9967a,'darkseagreen'=>0x8fbc8f,'darkslateblue'=>0x483d8b,'darkslategray'=>0x2f4f4f,
            'darkslategrey'=>0x2f4f4f,'darkturquoise'=>0x00ced1,'darkviolet'=>0x9400d3,'deeppink'=>0xff1493,
            'deepskyblue'=>0x00bfff,'dimgray'=>0x696969,'dimgrey'=>0x696969,'dodgerblue'=>0x1e90ff,'firebrick'=>0xb22222,
            'floralwhite'=>0xfffaf0,'forestgreen'=>0x228b22,'fuchsia'=>0xff00ff,'gainsboro'=>0xdcdcdc,
            'ghostwhite'=>0xf8f8ff,'gold'=>0xffd700,'goldenrod'=>0xdaa520,'gray'=>0x808080,'green'=>0x008000,
            'greenyellow'=>0xadff2f,'grey'=>0x808080,'honeydew'=>0xf0fff0,'hotpink'=>0xff69b4,'indianred'=>0xcd5c5c,
            'indigo'=>0x4b0082,'ivory'=>0xfffff0,'khaki'=>0xf0e68c,'lavender'=>0xe6e6fa,'lavenderblush'=>0xfff0f5,
            'lawngreen'=>0x7cfc00,'lemonchiffon'=>0xfffacd,'lightblue'=>0xadd8e6,'lightcoral'=>0xf08080,
            'lightcyan'=>0xe0ffff,'lightgoldenrodyellow'=>0xfafad2,'lightgray'=>0xd3d3d3,'lightgreen'=>0x90ee90,
            'lightgrey'=>0xd3d3d3,'lightpink'=>0xffb6c1,'lightsalmon'=>0xffa07a,'lightseagreen'=>0x20b2aa,
            'lightskyblue'=>0x87cefa,'lightslategray'=>0x778899,'lightslategrey'=>0x778899,'lightsteelblue'=>0xb0c4de,
            'lightyellow'=>0xffffe0,'lime'=>0x00ff00,'limegreen'=>0x32cd32,'linen'=>0xfaf0e6,'magenta'=>0xff00ff,
            'maroon'=>0x800000,'mediumaquamarine'=>0x66cdaa,'mediumblue'=>0x0000cd,'mediumorchid'=>0xba55d3,
            'mediumpurple'=>0x9370db,'mediumseagreen'=>0x3cb371,'mediumslateblue'=>0x7b68ee,'mediumspringgreen'=>0x00fa9a,
            'mediumturquoise'=>0x48d1cc,'mediumvioletred'=>0xc71585,'midnightblue'=>0x191970,'mintcream'=>0xf5fffa,
            'mistyrose'=>0xffe4e1,'moccasin'=>0xffe4b5,'navajowhite'=>0xffdead,'navy'=>0x000080,'oldlace'=>0xfdf5e6,
            'olive'=>0x808000,'olivedrab'=>0x6b8e23,'orange'=>0xffa500,'orangered'=>0xff4500,'orchid'=>0xda70d6,
            'palegoldenrod'=>0xeee8aa,'palegreen'=>0x98fb98,'paleturquoise'=>0xafeeee,'palevioletred'=>0xdb7093,
            'papayawhip'=>0xffefd5,'peachpuff'=>0xffdab9,'peru'=>0xcd853f,'pink'=>0xffc0cb,'plum'=>0xdda0dd,
            'powderblue'=>0xb0e0e6,'purple'=>0x800080,'rebeccapurple'=>0x663399,'red'=>0xff0000,'rosybrown'=>0xbc8f8f,
            /*
             * On 21 June 2014, the CSS WG added the color RebeccaPurple to the Editor's Draft of the CSS4 Colors module,
             * to commemorate Eric Meyer's daughter Rebecca who died on 7 June 2014, her sixth birthday.
             * https://lists.w3.org/Archives/Public/www-style/2014Jun/0312.html
             */
            'royalblue'=>0x4169e1,'saddlebrown'=>0x8b4513,'salmon'=>0xfa8072,'sandybrown'=>0xf4a460,
            'seagreen'=>0x2e8b57,'seashell'=>0xfff5ee,'sienna'=>0xa0522d,'silver'=>0xc0c0c0,
            'skyblue'=>0x87ceeb,'slateblue'=>0x6a5acd,'slategray'=>0x708090,'slategrey'=>0x708090,
            'snow'=>0xfffafa,'springgreen'=>0x00ff7f,'steelblue'=>0x4682b4,'tan'=>0xd2b48c,'teal'=>0x008080,
            'thistle'=>0xd8bfd8,'tomato'=>0xff6347,'turquoise'=>0x40e0d0,'violet'=>0xee82ee,'wheat'=>0xf5deb3,
            'white'=>0xffffff,'whitesmoke'=>0xf5f5f5,'yellow'=>0xffff00,'yellowgreen'=>0x9acd32];
    }

    public function getAllColors()
    {
        /*
         *  Wikipedia color list - generated with buildColorList.php
         */

        return
            ['Absolute Zero'=>[0x0048ba,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Extreme_Twistables_colors']],
                'Acajou'=>0x4c2f27,
                'Acid green'=>[0xb0bf1a,['url'=>'http://en.wikipedia.org/wiki/Acid_green']],
                'Aero'=>[0x7cb9e8,['url'=>'http://en.wikipedia.org/wiki/Aero_(color)']],
                'Aero blue'=>[0xc9ffe5,['url'=>'http://en.wikipedia.org/wiki/Aero_blue']],
                'African violet'=>[0xb284be,['url'=>'http://en.wikipedia.org/wiki/African_violet_(color)']],
                'Air Force blue (RAF)'=>[0x5d8aa8,['url'=>'http://en.wikipedia.org/wiki/Air_Force_blue_(RAF)']],
                'Air Force blue (USAF)'=>[0x00308f,['url'=>'http://en.wikipedia.org/wiki/Air_Force_blue_(USAF)']],
                'Air superiority blue'=>[0x72a0c1,['url'=>'http://en.wikipedia.org/wiki/Air_superiority_blue']],
                'Alabama crimson'=>[0xaf002a,['url'=>'http://en.wikipedia.org/wiki/Alabama_crimson']],
                'Alice blue'=>[0xf0f8ff,['url'=>'http://en.wikipedia.org/wiki/Alice_blue']],
                'Alien Armpit'=>[0x84de02,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Rose madder'=>[0xe32636,['url'=>'http://en.wikipedia.org/wiki/Alizarin']],
                'Alloy orange'=>[0xc46210,['url'=>'http://en.wikipedia.org/wiki/Alloy_orange']],
                'Almond'=>[0xefdecd,['url'=>'http://en.wikipedia.org/wiki/Almond_(color)']],
                'Amaranth'=>[0xe52b50,['url'=>'http://en.wikipedia.org/wiki/Amaranth_(color)']],
                'Amaranth deep purple'=>[0x9f2b68,['url'=>'http://en.wikipedia.org/wiki/Amaranth_(color)#Amaranth_deep_purple']],
                'Amaranth pink'=>[0xf19cbb,['url'=>'http://en.wikipedia.org/wiki/Amaranth_(color)#Amaranth_pink']],
                'Amaranth purple'=>[0xab274f,['url'=>'http://en.wikipedia.org/wiki/Amaranth_(color)#Amaranth_purple']],
                'Amaranth red'=>[0xd3212d,['url'=>'http://en.wikipedia.org/wiki/Amaranth_(color)']],
                'Amazon'=>[0x3b7a57,['url'=>'http://en.wikipedia.org/wiki/Amazon_(color)']],
                'Fluorescent orange'=>[0xffbf00,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Twistables']],
                'Amber (SAE/ECE)'=>[0xff7e00,['url'=>'http://en.wikipedia.org/wiki/Amber_(color)']],
                'American rose'=>[0xff033e,['url'=>'http://en.wikipedia.org/wiki/American_rose']],
                'Amethyst'=>[0x9966cc,['url'=>'http://en.wikipedia.org/wiki/Amethyst_(color)']],
                'Android green'=>[0xa4c639,['url'=>'http://en.wikipedia.org/wiki/Android_green']],
                'Anti-flash white'=>[0xf2f3f4,['url'=>'http://en.wikipedia.org/wiki/Anti-flash_white']],
                'Antique brass'=>[0xcd9575,['url'=>'http://en.wikipedia.org/wiki/Antique_brass']],
                'Antique bronze'=>[0x665d1e,['url'=>'http://en.wikipedia.org/wiki/Antique_bronze']],
                'Antique fuchsia'=>[0x915c83,['url'=>'http://en.wikipedia.org/wiki/Antique_fuchsia']],
                'Antique ruby'=>[0x841b2d,['url'=>'http://en.wikipedia.org/wiki/Antique_ruby']],
                'Moccasin'=>[0xfaebd7,['url'=>'http://en.wikipedia.org/wiki/Moccasin']],
                'Office green'=>[0x008000,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Office_green']],
                'Apple green'=>[0x8db600,['url'=>'http://en.wikipedia.org/wiki/Apple_green']],
                'Apricot'=>[0xfbceb1,['url'=>'http://en.wikipedia.org/wiki/Apricot_(color)']],
                'Spanish sky blue'=>[0x00ffff,['url'=>'http://en.wikipedia.org/wiki/Sky_blue#Spanish_sky_blue']],
                'Aquamarine'=>[0x7fffd4,['url'=>'http://en.wikipedia.org/wiki/Aquamarine_(color)']],
                'Arctic lime'=>[0xd0ff14,['url'=>'http://en.wikipedia.org/wiki/Lime_(color)#Arctic_Lime']],
                'Army green'=>[0x4b5320,['url'=>'http://en.wikipedia.org/wiki/Army_green']],
                'Arsenic'=>0x3b444b,
                'Artichoke'=>[0x8f9779,['url'=>'http://en.wikipedia.org/wiki/Artichoke_(color)']],
                'Hansa yellow'=>[0xe9d66b,['url'=>'http://en.wikipedia.org/wiki/Hansa_yellow']],
                'Ash grey'=>[0xb2beb5,['url'=>'http://en.wikipedia.org/wiki/Ash_grey']],
                'Asparagus'=>[0x87a96b,['url'=>'http://en.wikipedia.org/wiki/Asparagus_(color)']],
                'Pink-orange'=>0xff9966,
                'Red-brown'=>[0xa52a2a,['url'=>'http://en.wikipedia.org/wiki/Brown#Red-brown_.28web_color_.22brown.22.29']],
                'Aureolin'=>[0xfdee00,['url'=>'http://en.wikipedia.org/wiki/Aureolin']],
                'AuroMetalSaurus'=>[0x6e7f80,['url'=>'http://en.wikipedia.org/wiki/AuroMetalSaurus']],
                'Avocado'=>[0x568203,['url'=>'http://en.wikipedia.org/wiki/Avocado_(color)']],
                'Aztec Gold'=>[0xc39953,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Azure'=>[0x007fff,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)']],
                'Azure mist'=>[0xf0ffff,['url'=>'http://en.wikipedia.org/wiki/Azure_mist']],
                'Azureish white'=>[0xdbe9f4,['url'=>'http://en.wikipedia.org/wiki/White']],
                'Baby blue eyes'=>[0xa1caf1,['url'=>'http://en.wikipedia.org/wiki/Baby_blue#Baby_blue_eyes']],
                'Tea rose'=>[0xf4c2c2,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Tea_rose']],
                'Baby powder'=>[0xfefefa,['url'=>'http://en.wikipedia.org/wiki/Shades_of_white#Baby_powder']],
                'Schauss pink'=>[0xff91af,['url'=>'http://en.wikipedia.org/wiki/Baker-Miller_Pink']],
                'Ball blue'=>[0x21abcd,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Standard_colors']],
                'Banana Mania'=>[0xfae7b5,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Banana yellow'=>[0xffe135,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Banana_yellow']],
                'Bottle green'=>[0x006a4e,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Bottle_green']],
                'Barbie pink'=>[0xe0218a,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Barbie_pink']],
                'Barn red'=>[0x7c0a02,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Barn_red']],
                'Bright cerulean'=>[0x1dacd6,['url'=>'http://en.wikipedia.org/wiki/Cerulean#Bright_cerulean']],
                'Old silver'=>[0x848482,['url'=>'http://en.wikipedia.org/wiki/Silver_(color)#Old_silver']],
                'Bazaar'=>[0x98777b,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Bazaar']],
                'Pale aqua'=>[0xbcd4e6,['url'=>'http://en.wikipedia.org/wiki/Aqua_(color)#Pale_aqua']],
                'Beaver'=>[0x9f8170,['url'=>'http://en.wikipedia.org/wiki/Variations_of_brown#Beaver']],
                'Beige'=>[0xf5f5dc,['url'=>'http://en.wikipedia.org/wiki/Beige']],
                'B\'dazzled blue'=>[0x2e5894,['url'=>'http://en.wikipedia.org/wiki/Sapphire_(color)#B.27dazzled_blue']],
                'Big dip o’ruby'=>[0x9c2542,['url'=>'http://en.wikipedia.org/wiki/Ruby_(color)#Big_dip_o.27ruby']],
                'Big Foot Feet'=>[0xe88e5a,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Bisque'=>[0xffe4c4,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_name_charts']],
                'Bistre'=>[0x3d2b1f,['url'=>'http://en.wikipedia.org/wiki/Bistre']],
                'Sandy taupe'=>[0x967117,['url'=>'http://en.wikipedia.org/wiki/Taupe#Sandy_taupe']],
                'Bitter lemon'=>[0xcae00d,['url'=>'http://en.wikipedia.org/wiki/Lemon_(color)#Bitter_lemon']],
                'Lime (color wheel)'=>[0xbfff00,['url'=>'http://en.wikipedia.org/wiki/Lime_(color)']],
                'Bittersweet'=>[0xfe6f5e,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Bittersweet']],
                'Bittersweet shimmer'=>[0xbf4f51,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Bittersweet_shimmer']],
                'Registration black'=>[0x000000,['url'=>'http://en.wikipedia.org/wiki/Rich_black']],
                'Black bean'=>[0x3d0c02,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black_(colors)#Black_bean']],
                'Black Coral'=>[0x54626f,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Pearl_Brite']],
                'Black leather jacket'=>[0x253529,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black_(colors)#Black_leather_jacket']],
                'Black olive'=>[0x3b3c36,['url'=>'http://en.wikipedia.org/wiki/Olive_(color)#Black_olive']],
                'Black Shadows'=>[0xbfafb2,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Blanched almond'=>[0xffebcd,['url'=>'http://en.wikipedia.org/wiki/X11_color_names']],
                'Blast-off bronze'=>[0xa57164,['url'=>'http://en.wikipedia.org/wiki/Bronze_(color)#Blast-off_bronze']],
                'Bleu de France'=>[0x318ce7,['url'=>'http://en.wikipedia.org/wiki/Bleu_de_France_(colour)']],
                'Blue Lagoon'=>[0xace5ee,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Blond'=>[0xfaf0be,['url'=>'http://en.wikipedia.org/wiki/Blond']],
                'Blue'=>[0x0000ff,['url'=>'http://en.wikipedia.org/wiki/Blue']],
                'Blue (Crayola)'=>[0x1f75fe,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Blue_.28Crayola.29']],
                'Blue (Munsell)'=>[0x0093af,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Blue_.28Munsell.29']],
                'Blue (NCS)'=>[0x0087bd,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Blue_.28NCS.29']],
                'Blue (Pantone)'=>[0x0018a8,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Blue_.28Pantone.29']],
                'Blue (pigment)'=>[0x333399,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Blue_.28CMYK.29_.28pigment_blue.29']],
                'Blue (RYB)'=>[0x0247fe,['url'=>'http://en.wikipedia.org/wiki/RYB_color_model']],
                'Blue Bell'=>[0xa2a2d0,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Blue Bolt'=>[0x00b9fb,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Livid'=>[0x6699cc,['url'=>'http://en.wikipedia.org/wiki/Blue-gray']],
                'Blue-green'=>[0x0d98ba,['url'=>'http://en.wikipedia.org/wiki/Blue-green']],
                'Blue Jeans'=>[0x5dadec,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Blue-magenta violet'=>[0x553592,['url'=>'http://en.wikipedia.org/wiki/Magenta']],
                'Blue sapphire'=>[0x126180,['url'=>'http://en.wikipedia.org/wiki/Sapphire_(color)#Blue_sapphire']],
                'Blue-violet'=>[0x8a2be2,['url'=>'http://en.wikipedia.org/wiki/Indigo#Deep_indigo_.28web_color_blue-violet.29']],
                'Blue yonder'=>[0x5072a7,['url'=>'http://en.wikipedia.org/wiki/Air_Force_blue#Blue_yonder']],
                'Blueberry'=>[0x4f86f7,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Bluebonnet'=>[0x1c1cf0,['url'=>'http://en.wikipedia.org/wiki/Bluebonnet_(plant)']],
                'Blush'=>[0xde5d83,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Blush']],
                'Medium Tuscan red'=>[0x79443b,['url'=>'http://en.wikipedia.org/wiki/Tuscan_red#Medium_Tuscan_red']],
                'Bondi blue'=>[0x0095b6,['url'=>'http://en.wikipedia.org/wiki/Bondi_blue']],
                'Bone'=>[0xe3dac9,['url'=>'http://en.wikipedia.org/wiki/Shades_of_white#Bone']],
                'Booger Buster'=>[0xdde26a,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Boston University Red'=>[0xcc0000,['url'=>'http://en.wikipedia.org/wiki/Crimson#Boston_University_Red']],
                'Boysenberry'=>[0x873260,['url'=>'http://en.wikipedia.org/wiki/Boysenberry_(color)']],
                'Brandeis blue'=>[0x0070ff,['url'=>'http://en.wikipedia.org/wiki/Brandeis_blue']],
                'Brass'=>[0xb5a642,['url'=>'http://en.wikipedia.org/wiki/Brass']],
                'Brick red'=>[0xcb4154,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Bright green'=>[0x66ff00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Bright_green']],
                'Bright lavender'=>[0xbf94e4,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Bright_lavender_.28light_floral_lavender.29_.28lavender_bandana.29']],
                'Bright lilac'=>[0xd891ef,['url'=>'http://en.wikipedia.org/wiki/Lilac_(color)#Bright_lilac']],
                'Maroon (Crayola)'=>[0xc32148,['url'=>'http://en.wikipedia.org/wiki/Maroon_(color)#Maroon_.28Crayola.29']],
                'Bright navy blue'=>[0x1974d2,['url'=>'http://en.wikipedia.org/wiki/Navy_blue#Bright_navy_blue']],
                'Rose'=>[0xff007f,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)']],
                'Bright turquoise'=>[0x08e8de,['url'=>'http://en.wikipedia.org/wiki/Turquoise_(color)#Bright_Turquoise']],
                'Bright ube'=>[0xd19fe8,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Bright_.C3.BAbe']],
                'Bright Yellow (Crayola)'=>[0xffaa1d,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Brilliant azure'=>[0x3399ff,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)']],
                'Electric lavender'=>[0xf4bbff,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Brilliant_lavender_.28electric_lavender.29']],
                'Magenta (Crayola)'=>[0xff55a3,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Magenta_.28Crayola.29']],
                'Brink pink'=>[0xfb607f,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Brink_pink']],
                'British racing green'=>[0x004225,['url'=>'http://en.wikipedia.org/wiki/British_racing_green']],
                'Bronze'=>[0xcd7f32,['url'=>'http://en.wikipedia.org/wiki/Bronze_(color)']],
                'Bronze Yellow'=>[0x737000,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Standard_colors']],
                'Brown (traditional)'=>[0x964b00,['url'=>'http://en.wikipedia.org/wiki/Brown']],
                'Kobicha'=>[0x6b4423,['url'=>'http://en.wikipedia.org/wiki/Kobicha']],
                'Brown Sugar'=>[0xaf6e4d,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Brown Yellow'=>0xcc9966,
                'English green'=>[0x1b4d3e,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Brunswick_green']],
                'Bubble gum'=>[0xffc1cc,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Standard_colors']],
                'Bubbles'=>[0xe7feff,['url'=>'http://en.wikipedia.org/wiki/Baby_blue#Bubbles']],
                'Bud green'=>[0x7bb661,['url'=>'http://en.wikipedia.org/wiki/Spring_bud#Bud_green']],
                'Buff'=>[0xf0dc82,['url'=>'http://en.wikipedia.org/wiki/Buff_(colour)']],
                'Bulgarian rose'=>[0x480607,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Bulgarian_Rose']],
                'Burgundy'=>[0x800020,['url'=>'http://en.wikipedia.org/wiki/Burgundy_(color)']],
                'Burlywood'=>[0xdeb887,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_name_charts']],
                'Burnished Brown'=>[0xa17a74,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Burnt orange'=>[0xcc5500,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Burnt_orange']],
                'Light red ochre'=>[0xe97451,['url'=>'http://en.wikipedia.org/wiki/Ochre']],
                'Burnt umber'=>[0x8a3324,['url'=>'http://en.wikipedia.org/wiki/Burnt_umber']],
                'Byzantine'=>[0xbd33a4,['url'=>'http://en.wikipedia.org/wiki/Byzantium_(color)#Byzantine']],
                'Byzantium'=>[0x702963,['url'=>'http://en.wikipedia.org/wiki/Byzantium_(color)']],
                'Cadet blue'=>[0x5f9ea0,['url'=>'http://en.wikipedia.org/wiki/Cadet_grey#Cadet_blue']],
                'Cadet grey'=>[0x91a3b0,['url'=>'http://en.wikipedia.org/wiki/Cadet_grey']],
                'Cadmium green'=>[0x006b3c,['url'=>'http://en.wikipedia.org/wiki/Cadmium_pigments']],
                'Cadmium orange'=>[0xed872d,['url'=>'http://en.wikipedia.org/wiki/Cadmium_pigments']],
                'Cadmium red'=>[0xe30022,['url'=>'http://en.wikipedia.org/wiki/Cadmium_pigments']],
                'Cadmium yellow'=>[0xfff600,['url'=>'http://en.wikipedia.org/wiki/Cadmium_pigments']],
                'Tuscan tan'=>[0xa67b5b,['url'=>'http://en.wikipedia.org/wiki/Tan_(color)#Tuscan_tan']],
                'Café noir'=>[0x4b3621,['url'=>'http://en.wikipedia.org/wiki/Coffee_(color)#Caf.C3.A9_Noir']],
                'Cal Poly green'=>[0x1e4d2b,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Cal_Poly_green']],
                'Cambridge Blue'=>[0xa3c1ad,['url'=>'http://en.wikipedia.org/wiki/Cambridge_Blue_(colour)']],
                'Wood brown'=>[0xc19a6b,['url'=>'http://en.wikipedia.org/wiki/Variations_of_brown#Wood_brown']],
                'Cameo pink'=>[0xefbbcc,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Cameo_pink']],
                'Camouflage green'=>[0x78866b,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Camouflage_green']],
                'Canary'=>[0xffff99,['url'=>'http://en.wikipedia.org/wiki/History_of_Crayola_crayons#Crayola_No._64']],
                'Yellow (process)'=>[0xffef00,['url'=>'http://en.wikipedia.org/wiki/Yellow#Electric_yellow_vs._process_yellow']],
                'Candy apple red'=>[0xff0800,['url'=>'http://en.wikipedia.org/wiki/Candy_apple_red_(color)']],
                'Tango pink'=>[0xe4717a,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Tango_pink']],
                'Deep sky blue'=>[0x00bfff,['url'=>'http://en.wikipedia.org/wiki/Sky_blue#Deep_sky_blue']],
                'Caput mortuum'=>[0x592720,['url'=>'http://en.wikipedia.org/wiki/Caput_mortuum']],
                'Cardinal'=>[0xc41e3a,['url'=>'http://en.wikipedia.org/wiki/Cardinal_(color)']],
                'Caribbean green'=>[0x00cc99,['url'=>'http://en.wikipedia.org/wiki/Spring_green#Caribbean_green']],
                'Heidelberg Red'=>[0x960018,['url'=>'http://en.wikipedia.org/wiki/Heidelberg_University']],
                'Rich carmine'=>[0xd70040,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Rich_carmine']],
                'Carmine pink'=>[0xeb4c42,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Carmine_pink']],
                'Carmine red'=>[0xff0038,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Carmine_Red']],
                'Carnation pink'=>[0xffa6c9,['url'=>'http://en.wikipedia.org/wiki/Carnation_pink']],
                'Cornell Red'=>[0xb31b1b,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Cornell_Red']],
                'Carolina blue'=>[0x56a0d3,['url'=>'http://en.wikipedia.org/wiki/Carolina_blue']],
                'Carrot orange'=>[0xed9121,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Carrot_orange']],
                'Castleton green'=>[0x00563f,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Castleton_green']],
                'Catalina blue'=>[0x062a78,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Catalina_blue']],
                'Catawba'=>[0x703642,['url'=>'http://en.wikipedia.org/wiki/Catawba_(grape)#Catawba_.28color.29']],
                'Cedar Chest'=>[0xc95a49,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Ceil'=>[0x92a1cf,['url'=>'http://en.wikipedia.org/wiki/Variations_of_blue#Ceil']],
                'Celadon'=>[0xace1af,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Celadon']],
                'Cerulean'=>[0x007ba7,['url'=>'http://en.wikipedia.org/wiki/Cerulean']],
                'Celadon green'=>[0x2f847c,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Celadon_green']],
                'Italian sky blue'=>[0xb2ffff,['url'=>'http://en.wikipedia.org/wiki/Sky_blue#Celeste']],
                'Celestial blue'=>[0x4997d0,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Celestial_blue']],
                'Cherry'=>[0xde3163,['url'=>'http://en.wikipedia.org/wiki/Cerise_(color)']],
                'Cerise pink'=>[0xec3b83,['url'=>'http://en.wikipedia.org/wiki/Cerise_(color)#Cerise_pink']],
                'Cerulean blue'=>[0x2a52be,['url'=>'http://en.wikipedia.org/wiki/Cerulean#Cerulean_blue']],
                'Cerulean frost'=>[0x6d9bc3,['url'=>'http://en.wikipedia.org/wiki/Cerulean#Cerulean_frost']],
                'CG Blue'=>[0x007aa5,['url'=>'http://en.wikipedia.org/wiki/CG_Blue']],
                'CG Red'=>[0xe03c31,['url'=>'http://en.wikipedia.org/wiki/CG_Red']],
                'Chamoisee'=>[0xa0785a,['url'=>'http://en.wikipedia.org/wiki/Chamoisee']],
                'Champagne'=>[0xf7e7ce,['url'=>'http://en.wikipedia.org/wiki/Champagne_(color)#Champagne']],
                'Champagne pink'=>[0xf1ddcf,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Other_notable_pink_colors']],
                'Charcoal'=>[0x36454f,['url'=>'http://en.wikipedia.org/wiki/Charcoal_(color)']],
                'Charleston green'=>[0x232b2b,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black_(colors)#Charleston_green']],
                'Light Thulian pink'=>[0xe68fac,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Light_Thulian_Pink']],
                'Chartreuse (traditional)'=>[0xdfff00,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)']],
                'Chartreuse (web)'=>[0x7fff00,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)']],
                'Cherry blossom pink'=>[0xffb7c5,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Cherry_blossom_pink']],
                'Chestnut'=>[0x954535,['url'=>'http://en.wikipedia.org/wiki/Chestnut_(color)']],
                'Thulian pink'=>[0xde6fa1,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Thulian_pink']],
                'China rose'=>[0xa8516e,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#China_rose']],
                'Chinese red'=>[0xaa381e,['url'=>'http://en.wikipedia.org/wiki/Vermilion#Chinese_red_2']],
                'Chinese violet'=>[0x856088,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#Chinese_violet']],
                'Chlorophyll green'=>[0x4aff00,['url'=>'http://en.wikipedia.org/wiki/Chlorophyll']],
                'Chocolate (traditional)'=>[0x7b3f00,['url'=>'http://en.wikipedia.org/wiki/Chocolate_(color)']],
                'Cocoa brown'=>[0xd2691e,['url'=>'http://en.wikipedia.org/wiki/Chocolate_(color)#Variations_of_chocolate']],
                'Chrome yellow'=>[0xffa700,['url'=>'http://en.wikipedia.org/wiki/Chrome_yellow']],
                'Cinereous'=>[0x98817b,['url'=>'http://en.wikipedia.org/wiki/Cinereous']],
                'Vermilion'=>[0xe34234,['url'=>'http://en.wikipedia.org/wiki/Vermilion']],
                'Cinnamon Satin'=>[0xcd607e,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Citrine'=>[0xe4d00a,['url'=>'http://en.wikipedia.org/wiki/Citrine_(colour)']],
                'Citron'=>[0x9fa91f,['url'=>'http://en.wikipedia.org/wiki/Lemon_(color)#Citron']],
                'Claret'=>[0x7f1734,['url'=>'http://en.wikipedia.org/wiki/Wine_(color)#Claret']],
                'Classic rose'=>[0xfbcce7,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Classic_rose']],
                'Cobalt Blue'=>[0x0047ab,['url'=>'http://en.wikipedia.org/wiki/Cobalt_blue']],
                'Coconut'=>[0x965a3e,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Tuscan brown'=>[0x6f4e37,['url'=>'http://en.wikipedia.org/wiki/Tuscan_red#Tuscan_brown']],
                'Columbia Blue'=>[0xc4d8e2,['url'=>'http://en.wikipedia.org/wiki/Columbia_Blue']],
                'Cool Black'=>[0x002e63,['url'=>'http://en.wikipedia.org/wiki/Cool_Black']],
                'Gray-blue'=>[0x8c92ac,['url'=>'http://en.wikipedia.org/wiki/Variations_of_gray#Cool_gray']],
                'Copper'=>[0xb87333,['url'=>'http://en.wikipedia.org/wiki/Copper_(color)']],
                'Pale copper'=>[0xda8a67,['url'=>'http://en.wikipedia.org/wiki/Copper_(color)#Pale_copper']],
                'Copper penny'=>[0xad6f69,['url'=>'http://en.wikipedia.org/wiki/Copper_(color)#Copper_penny']],
                'Copper red'=>[0xcb6d51,['url'=>'http://en.wikipedia.org/wiki/Copper_(color)#Copper_red']],
                'Copper rose'=>[0x996666,['url'=>'http://en.wikipedia.org/wiki/Copper_(color)#Copper_rose']],
                'Coquelicot'=>[0xff3800,['url'=>'http://en.wikipedia.org/wiki/Coquelicot']],
                'Coral'=>[0xff7f50,['url'=>'http://en.wikipedia.org/wiki/Coral_(color)']],
                'Coral red'=>[0xff4040,['url'=>'http://en.wikipedia.org/wiki/Coral_(color)#Coral_red']],
                'Coral Reef'=>[0xfd7c6e,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Cordovan'=>[0x893f45,['url'=>'http://en.wikipedia.org/wiki/Cordovan_(color)']],
                'Maize'=>[0xfbec5d,['url'=>'http://en.wikipedia.org/wiki/Maize_(color)']],
                'Cornflower blue'=>[0x6495ed,['url'=>'http://en.wikipedia.org/wiki/Cornflower_blue']],
                'Cornsilk'=>[0xfff8dc,['url'=>'http://en.wikipedia.org/wiki/Shades_of_white#Cornsilk']],
                'Cosmic Cobalt'=>[0x2e2d88,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Cosmic latte'=>[0xfff8e7,['url'=>'http://en.wikipedia.org/wiki/Cosmic_latte']],
                'Coyote brown'=>[0x81613e,['url'=>'http://en.wikipedia.org/wiki/Coyote_brown']],
                'Cotton candy'=>[0xffbcd9,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Cotton_candy']],
                'Cream'=>[0xfffdd0,['url'=>'http://en.wikipedia.org/wiki/Cream_(colour)']],
                'Crimson'=>[0xdc143c,['url'=>'http://en.wikipedia.org/wiki/Crimson']],
                'Crimson glory'=>[0xbe0032,['url'=>'http://en.wikipedia.org/wiki/Crimson#Crimson_glory']],
                'USC Cardinal'=>[0x990000,['url'=>'http://en.wikipedia.org/wiki/University_of_Southern_California']],
                'White smoke'=>[0xf5f5f5,['url'=>'http://en.wikipedia.org/wiki/Smoke']],
                'Cyan azure'=>[0x4e82b4,['url'=>'http://en.wikipedia.org/wiki/Shades_of_cyan']],
                'Cyan-blue azure'=>[0x4682bf,['url'=>'http://en.wikipedia.org/wiki/Shades_of_cyan']],
                'Cyan cobalt blue'=>[0x28589c,['url'=>'http://en.wikipedia.org/wiki/Shades_of_cyan']],
                'Cyan cornflower blue'=>[0x188bc2,['url'=>'http://en.wikipedia.org/wiki/Shades_of_cyan']],
                'Cyan (process)'=>[0x00b7eb,['url'=>'http://en.wikipedia.org/wiki/Cyan#Process_cyan_.28pigment_cyan.29_.28printer.27s_cyan.29']],
                'Cyber grape'=>[0x58427c,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Yellow (NCS)'=>[0xffd300,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Yellow_.28NCS.29_.28psychological_primary_yellow.29']],
                'Cyclamen'=>[0xf56fa1,['url'=>'http://en.wikipedia.org/wiki/Cyclamen_(color)']],
                'Daffodil'=>[0xffff31,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Daffodil']],
                'Dandelion'=>[0xf0e130,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Dandelion']],
                'Dark blue'=>[0x00008b,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Dark_blue']],
                'Dark blue-gray'=>[0x666699,['url'=>'http://en.wikipedia.org/wiki/Blue-gray#Dark_blue-gray']],
                'Otter brown'=>[0x654321,['url'=>'http://en.wikipedia.org/wiki/Brown#Otter_brown']],
                'Dark brown-tangelo'=>[0x88654e,['url'=>'http://en.wikipedia.org/wiki/Variations_of_brown']],
                'Dark byzantium'=>[0x5d3954,['url'=>'http://en.wikipedia.org/wiki/Byzantium_(color)#Dark_byzantium']],
                'Dark candy apple red'=>[0xa40000,['url'=>'http://en.wikipedia.org/wiki/Candy_apple_red_(color)#Dark_candy_apple_red']],
                'Dark cerulean'=>[0x08457e,['url'=>'http://en.wikipedia.org/wiki/Cerulean#Dark_cerulean']],
                'Dark chestnut'=>[0x986960,['url'=>'http://en.wikipedia.org/wiki/Chestnut_(color)#Dark_chestnut']],
                'Dark coral'=>[0xcd5b45,['url'=>'http://en.wikipedia.org/wiki/Coral_(color)#Dark_coral']],
                'Dark cyan'=>[0x008b8b,['url'=>'http://en.wikipedia.org/wiki/Variations_of_cyan#Dark_cyan']],
                'Payne\'s grey'=>[0x536878,['url'=>'http://en.wikipedia.org/wiki/Payne%27s_grey']],
                'Dark goldenrod'=>[0xb8860b,['url'=>'http://en.wikipedia.org/wiki/Goldenrod_(color)#Dark_goldenrod']],
                'Dark medium gray'=>[0xa9a9a9,['url'=>'http://en.wikipedia.org/wiki/Variations_of_gray#Dark_medium_gray_.28dark_gray_.28X11.29.29']],
                'Dark green'=>[0x013220,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Dark_green']],
                'Dark green (X11)'=>[0x006400,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Dark_green_.28X11.29']],
                'Dark gunmetal'=>[0x1f262a,['url'=>'http://en.wikipedia.org/wiki/Shades_of_grey#Dark_Gunmetal']],
                'Dark imperial blue'=>[0x00416a,['url'=>'http://en.wikipedia.org/wiki/Indigo#Dark_imperial_blue']],
                'Dark jungle green'=>[0x1a2421,['url'=>'http://en.wikipedia.org/wiki/Jungle_green#Dark_jungle_green']],
                'Dark khaki'=>[0xbdb76b,['url'=>'http://en.wikipedia.org/wiki/Khaki_(color)#Dark_khaki']],
                'Taupe'=>[0x483c32,['url'=>'http://en.wikipedia.org/wiki/Taupe']],
                'Dark lavender'=>[0x734f96,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Dark_lavender']],
                'Dark liver'=>[0x534b4f,['url'=>'http://en.wikipedia.org/wiki/Liver_(color)#Dark_liver_.28web.29']],
                'Dark liver (horses)'=>[0x543d37,['url'=>'http://en.wikipedia.org/wiki/Liver_(color)#Dark_liver_.28horses.29']],
                'Dark magenta'=>[0x8b008b,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Dark_magenta']],
                'Dark midnight blue'=>[0x003366,['url'=>'http://en.wikipedia.org/wiki/Midnight_blue#Dark_midnight_blue']],
                'Dark moss green'=>[0x4a5d23,['url'=>'http://en.wikipedia.org/wiki/Moss_green#Dark_moss_green']],
                'Deep spring bud'=>[0x556b2f,['url'=>'http://en.wikipedia.org/wiki/Spring_bud']],
                'Dark orange'=>[0xff8c00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Dark_orange_.28web_color.29']],
                'Dark orchid'=>[0x9932cc,['url'=>'http://en.wikipedia.org/wiki/Orchid_(color)#Dark_orchid']],
                'Dark pastel blue'=>[0x779ecb,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Dark_pastel_blue']],
                'Dark pastel green'=>[0x03c03c,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Dark_pastel_green']],
                'Dark pastel purple'=>[0x966fd6,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Dark_pastel_purple']],
                'Dark pastel red'=>[0xc23b22,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Dark_pastel_red']],
                'Dark pink'=>[0xe75480,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Dark_pink']],
                'Smalt (Dark powder blue)'=>[0x003399,['url'=>'http://en.wikipedia.org/wiki/Powder_blue']],
                'Dark puce'=>[0x4f3a3c,['url'=>'http://en.wikipedia.org/wiki/Puce#Dark_puce']],
                'Dark purple'=>[0x301934,['url'=>'http://en.wikipedia.org/wiki/Dark_purple']],
                'Dark raspberry'=>[0x872657,['url'=>'http://en.wikipedia.org/wiki/Raspberry_(color)#Dark_raspberry']],
                'Dark red'=>[0x8b0000,['url'=>'http://en.wikipedia.org/wiki/Maroon_(color)#Dark_red']],
                'Dark salmon'=>[0xe9967a,['url'=>'http://en.wikipedia.org/wiki/Salmon_(color)#Dark_salmon']],
                'Dark scarlet'=>[0x560319,['url'=>'http://en.wikipedia.org/wiki/Scarlet_(color)#Dark_scarlet']],
                'Dark sea green'=>[0x8fbc8f,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_name_charts']],
                'Dark sienna'=>[0x3c1414,['url'=>'http://en.wikipedia.org/wiki/Sienna']],
                'Dark sky blue'=>[0x8cbed6,['url'=>'http://en.wikipedia.org/wiki/Sky_blue#Dark_sky_blue']],
                'Dark slate blue'=>[0x483d8b,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_name_charts']],
                'Dark slate gray'=>[0x2f4f4f,['url'=>'http://en.wikipedia.org/wiki/Slate_gray#Dark_slate_gray']],
                'Dark spring green'=>[0x177245,['url'=>'http://en.wikipedia.org/wiki/Spring_green#Dark_spring_green']],
                'Dark tan'=>[0x918151,['url'=>'http://en.wikipedia.org/wiki/Tan_(color)#Dark_tan']],
                'Dark tangerine'=>[0xffa812,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Dark_tangerine']],
                'Dark terra cotta'=>[0xcc4e5c,['url'=>'http://en.wikipedia.org/wiki/Terracotta#Dark_terra_cotta']],
                'Dark turquoise'=>[0x00ced1,['url'=>'http://en.wikipedia.org/wiki/Turquoise_(color)#Dark_Turquoise']],
                'Dark vanilla'=>[0xd1bea8,['url'=>'http://en.wikipedia.org/wiki/Vanilla_(color)#Dark_vanilla']],
                'Dark violet'=>[0x9400d3,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#Pigment_violet_.28web_color_dark_violet.29']],
                'Dark yellow'=>[0x9b870c,['url'=>'http://en.wikipedia.org/wiki/Yellow#Dark_yellow']],
                'Dartmouth green'=>[0x00703c,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Dartmouth_green']],
                'Davy\'s grey'=>[0x555555,['url'=>'http://en.wikipedia.org/wiki/Davy%27s_grey']],
                'Debian red'=>[0xd70a53,['url'=>'http://en.wikipedia.org/wiki/Debian']],
                'Viridian'=>[0x40826d,['url'=>'http://en.wikipedia.org/wiki/Viridian']],
                'Deep carmine'=>[0xa9203e,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Deep_carmine']],
                'Deep carmine pink'=>[0xef3038,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Deep_Carmine_Pink']],
                'Deep carrot orange'=>[0xe9692c,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Deep_carrot_orange']],
                'Deep cerise'=>[0xda3287,['url'=>'http://en.wikipedia.org/wiki/Cerise_(color)#Deep_cerise']],
                'Tuscan'=>[0xfad6a5,['url'=>'http://en.wikipedia.org/wiki/Beige#Tuscan']],
                'Deep chestnut'=>[0xb94e48,['url'=>'http://en.wikipedia.org/wiki/Chestnut_(color)#Deep_chestnut']],
                'Roast coffee'=>[0x704241,['url'=>'http://en.wikipedia.org/wiki/Coffee_(color)#Roast_coffee']],
                'Fuchsia (Crayola)'=>[0xc154c1,['url'=>'http://en.wikipedia.org/wiki/Fuchsia_(color)#Deep_fuchsia']],
                'Deep Green'=>[0x056608,['url'=>'http://en.wikipedia.org/wiki/Deep_Green']],
                'Deep green-cyan turquoise'=>[0x0e7c61,['url'=>'http://en.wikipedia.org/wiki/Shades_of_cyan']],
                'Deep jungle green'=>[0x004b49,['url'=>'http://en.wikipedia.org/wiki/Jungle_green#Deep_jungle_green']],
                'Deep koamaru'=>0x333366,
                'Deep lemon'=>[0xf5c71a,['url'=>'http://en.wikipedia.org/wiki/Lemon_(color)#Deep_lemon']],
                'Deep lilac'=>[0x9955bb,['url'=>'http://en.wikipedia.org/wiki/Lilac_(color)#Deep_lilac']],
                'Deep magenta'=>[0xcc00cc,['url'=>'http://en.wikipedia.org/wiki/Magenta#Deep_magenta']],
                'Deep maroon'=>[0x820000,['url'=>'http://en.wikipedia.org/wiki/Maroon_(color)']],
                'French mauve'=>[0xd473d4,['url'=>'http://en.wikipedia.org/wiki/Mauve#French_mauve_.28deep_mauve.29']],
                'Hunter green'=>[0x355e3b,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Hunter_green']],
                'Peach'=>[0xffcba4,['url'=>'http://en.wikipedia.org/wiki/Peach_(color)#Deep_peach']],
                'Fluorescent pink'=>[0xff1493,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Twistables']],
                'Deep puce'=>[0xa95c68,['url'=>'http://en.wikipedia.org/wiki/Puce#Deep_puce']],
                'Deep Red'=>[0x850101,['url'=>'http://en.wikipedia.org/wiki/Deep_Red']],
                'Deep ruby'=>[0x843f5b,['url'=>'http://en.wikipedia.org/wiki/Ruby_(color)#Deep_ruby']],
                'Deep saffron'=>[0xff9933,['url'=>'http://en.wikipedia.org/wiki/Saffron_(color)#India_saffron_and_deep_saffron']],
                'Deep Space Sparkle'=>[0x4a646c,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Deep Taupe'=>[0x7e5e60,['url'=>'http://en.wikipedia.org/wiki/Taupe#Deep_taupe']],
                'Deep Tuscan red'=>[0x66424d,['url'=>'http://en.wikipedia.org/wiki/Tuscan_red#Deep_Tuscan_red']],
                'Deep violet'=>[0x330066,['url'=>'http://en.wikipedia.org/wiki/Violet_(color)']],
                'Deer'=>[0xba8759,['url'=>'http://en.wikipedia.org/wiki/Variations_of_brown#Deer']],
                'Denim'=>[0x1560bd,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Denim Blue'=>[0x2243b6,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Desaturated cyan'=>[0x669999,['url'=>'http://en.wikipedia.org/wiki/Shades_of_cyan']],
                'Desert sand'=>[0xedc9af,['url'=>'http://en.wikipedia.org/wiki/Desert_sand_(color)']],
                'Desire'=>[0xea3c53,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Desire']],
                'Diamond'=>[0xb9f2ff,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Diamond']],
                'Dim gray'=>[0x696969,['url'=>'http://en.wikipedia.org/wiki/Grey#Web_colors']],
                'Dingy Dungeon'=>[0xc53151,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Dirt'=>[0x9b7653,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Dodger blue'=>[0x1e90ff,['url'=>'http://en.wikipedia.org/wiki/Dodger_blue']],
                'Dogwood rose'=>[0xd71868,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Dogwood_rose']],
                'Dollar bill'=>[0x85bb65,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Standard_colors']],
                'Mummy\'s Tomb'=>[0x828e84,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Donkey brown'=>[0x664c28,['url'=>'http://en.wikipedia.org/wiki/Shades_of_brown']],
                'Duke blue'=>[0x00009c,['url'=>'http://en.wikipedia.org/wiki/Duke_blue']],
                'Dust storm'=>[0xe5ccc9,['url'=>'http://en.wikipedia.org/wiki/Variations_of_gray#Dust_storm']],
                'Dutch white'=>[0xefdfbb,['url'=>'http://en.wikipedia.org/wiki/Shades_of_white#Dutch_white']],
                'Ebony'=>[0x555d50,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black_(colors)#Ebony']],
                'Sand'=>[0xc2b280,['url'=>'http://en.wikipedia.org/wiki/Desert_sand_(color)#Sand']],
                'Eerie black'=>[0x1b1b1b,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black#Eerie_black']],
                'Eggplant'=>[0x614051,['url'=>'http://en.wikipedia.org/wiki/Eggplant_(color)']],
                'Eggshell'=>[0xf0ead6,['url'=>'http://en.wikipedia.org/wiki/Eggshell_(color)']],
                'Egyptian blue'=>[0x1034a6,['url'=>'http://en.wikipedia.org/wiki/Egyptian_blue']],
                'Electric blue'=>[0x7df9ff,['url'=>'http://en.wikipedia.org/wiki/Electric_blue_(color)']],
                'Electric crimson'=>[0xff003f,['url'=>'http://en.wikipedia.org/wiki/Crimson#Electric_crimson']],
                'Lime (web) (X11 green)'=>[0x00ff00,['url'=>'http://en.wikipedia.org/wiki/Lime_(color)#Web_color_.22lime.22_.28X11_Green.29']],
                'Electric indigo'=>[0x6f00ff,['url'=>'http://en.wikipedia.org/wiki/Indigo#Electric_indigo']],
                'Fluorescent yellow'=>[0xccff00,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Twistables']],
                'Electric purple'=>[0xbf00ff,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Electric_purple:_2000s']],
                'Ultramarine'=>[0x3f00ff,['url'=>'http://en.wikipedia.org/wiki/Ultramarine']],
                'Violet'=>[0x8f00ff,['url'=>'http://en.wikipedia.org/wiki/Violet_(color)']],
                'Electric yellow'=>[0xffff33,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Electric_yellow']],
                'Paris Green'=>[0x50c878,['url'=>'http://en.wikipedia.org/wiki/Paris_Green']],
                'Eminence'=>[0x6c3082,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Eminence']],
                'English lavender'=>[0xb48395,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#English_lavender']],
                'English red'=>[0xab4b52,['url'=>'http://en.wikipedia.org/wiki/Indian_red_(color)#English_red']],
                'English vermillion'=>[0xcc474b,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors']],
                'Pineapple'=>[0x563c5c,['url'=>'http://en.wikipedia.org/wiki/Pineapple#Pineapple']],
                'Eton blue'=>[0x96c8a2,['url'=>'http://en.wikipedia.org/wiki/Eton_blue#Eton_blue']],
                'Eucalyptus'=>[0x44d7a8,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Falu red'=>[0x801818,['url'=>'http://en.wikipedia.org/wiki/Falu_red']],
                'Fandango'=>[0xb53389,['url'=>'http://en.wikipedia.org/wiki/Fuchsia_(color)#Fandango']],
                'Fandango pink'=>[0xde5285,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Fandango_pink']],
                'Hollywood cerise'=>[0xf400a1,['url'=>'http://en.wikipedia.org/wiki/Cerise_(color)#Hollywood_cerise']],
                'Fawn'=>[0xe5aa70,['url'=>'http://en.wikipedia.org/wiki/Fawn_(colour)']],
                'Feldgrau'=>[0x4d5d53,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Feldgrau']],
                'Light apricot'=>[0xfdd5b1,['url'=>'http://en.wikipedia.org/wiki/Apricot_(color)#Light_apricot']],
                'Fern green'=>[0x4f7942,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Fern_green']],
                'Ferrari Red'=>[0xff2800,['url'=>'http://en.wikipedia.org/wiki/Ferrari']],
                'Field drab'=>[0x6c541e,['url'=>'http://en.wikipedia.org/wiki/Desert_sand_(color)#Field_drab']],
                'Fiery Rose'=>[0xff5470,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Extreme_Twistables_colors']],
                'Firebrick'=>[0xb22222,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_name_charts']],
                'Fire engine red'=>[0xce2029,['url'=>'http://en.wikipedia.org/wiki/Fire_engine_red']],
                'Flame'=>[0xe25822,['url'=>'http://en.wikipedia.org/wiki/Flame_(color)']],
                'Flamingo pink'=>[0xfc8eac,['url'=>'http://en.wikipedia.org/wiki/Flamingo']],
                'Flavescent'=>[0xf7e98e,['url'=>'http://en.wikipedia.org/wiki/Buff_(colour)#Flavescent']],
                'Flax'=>[0xeedc82,['url'=>'http://en.wikipedia.org/wiki/Flax_(color)']],
                'Flirt'=>[0xa2006d,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Flirt']],
                'Floral white'=>[0xfffaf0,['url'=>'http://en.wikipedia.org/wiki/Shades_of_white#Floral_white']],
                'Folly'=>[0xff004f,['url'=>'http://en.wikipedia.org/wiki/Crimson#Folly']],
                'UP Forest green'=>[0x014421,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#UP_Forest_Green']],
                'Forest green (web)'=>[0x228b22,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Forest_green']],
                'French bistre'=>[0x856d4d,['url'=>'http://en.wikipedia.org/wiki/Bistre#French_bistre']],
                'French blue'=>[0x0072bb,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#French_blue']],
                'French fuchsia'=>[0xfd3f92,['url'=>'http://en.wikipedia.org/wiki/Fuchsia_(color)#French_fuchsia']],
                'Pomp and Power'=>[0x86608e,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Pomp_and_Power']],
                'French lime'=>[0x9efd38,['url'=>'http://en.wikipedia.org/wiki/Lime_(color)#French_lime']],
                'French pink'=>[0xfd6c9e,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#French_pink']],
                'French plum'=>[0x811453,['url'=>'http://en.wikipedia.org/wiki/Plum_(color)#French_plum']],
                'French puce'=>[0x4e1609,['url'=>'http://en.wikipedia.org/wiki/Puce#French_puce']],
                'French raspberry'=>[0xc72c48,['url'=>'http://en.wikipedia.org/wiki/Raspberry_(color)#French_raspberry']],
                'French rose'=>[0xf64a8a,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#French_rose']],
                'French sky blue'=>[0x77b5fe,['url'=>'http://en.wikipedia.org/wiki/Sky_blue#French_sky_blue']],
                'French violet'=>[0x8806ce,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#French_violet']],
                'French wine'=>[0xac1e44,['url'=>'http://en.wikipedia.org/wiki/Wine_(color)#French_wine']],
                'Fresh Air'=>[0xa6e7ff,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Frostbite'=>[0xe936a7,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Extreme_Twistables_colors']],
                'Magenta'=>[0xff00ff,['url'=>'http://en.wikipedia.org/wiki/Magenta']],
                'Fuchsia pink'=>[0xff77ff,['url'=>'http://en.wikipedia.org/wiki/Fuchsia_(color)#Fuchsia_pink_.28light_magenta.29']],
                'Fuchsia purple'=>[0xcc397b,['url'=>'http://en.wikipedia.org/wiki/Fuchsia_(color)#Fuchsia_purple']],
                'Fuchsia rose'=>[0xc74375,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Fuchsia_rose']],
                'Fulvous'=>[0xe48400,['url'=>'http://en.wikipedia.org/wiki/Fulvous']],
                'Fuzzy Wuzzy'=>[0xcc6666,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Gamboge'=>[0xe49b0f,['url'=>'http://en.wikipedia.org/wiki/Gamboge']],
                'Gamboge orange (brown)'=>[0x996600,['url'=>'http://en.wikipedia.org/wiki/Gamboge']],
                'Gargoyle Gas'=>[0xffdf46,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Generic viridian'=>[0x007f66,['url'=>'http://en.wikipedia.org/wiki/Viridian#Generic_viridian']],
                'Ghost white'=>[0xf8f8ff,['url'=>'http://en.wikipedia.org/wiki/Ghost_white']],
                'Giant\'s Club'=>[0xb05c52,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Giants orange'=>[0xfe5a1d,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Giants_orange']],
                'Ginger'=>0xb06500,
                'Glaucous'=>[0x6082b6,['url'=>'http://en.wikipedia.org/wiki/Glaucous']],
                'Glitter'=>[0xe6e8fa,['url'=>'http://en.wikipedia.org/wiki/Glitter']],
                'Glossy Grape'=>[0xab92b3,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'GO green'=>[0x00ab66,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#GO_Transit_green']],
                'Gold (metallic)'=>[0xd4af37,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#Gold_.28metallic_gold.29']],
                'Gold (web) (Golden)'=>[0xffd700,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)']],
                'Gold Fusion'=>[0x85754e,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Golden brown'=>[0x996515,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#Golden_brown']],
                'Golden poppy'=>[0xfcc200,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#Golden_poppy']],
                'Golden yellow'=>[0xffdf00,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#Golden_yellow']],
                'Goldenrod'=>[0xdaa520,['url'=>'http://en.wikipedia.org/wiki/Goldenrod_(color)']],
                'Granite Gray'=>[0x676767,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Granny Smith Apple'=>[0xa8e4a0,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors']],
                'Grape'=>[0x6f2da8,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Trolley Grey'=>[0x808080,['url'=>'http://en.wikipedia.org/wiki/Grey']],
                'Gray (X11 gray)'=>[0xbebebe,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_names']],
                'Gray-asparagus'=>[0x465945,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Gray-asparagus']],
                'Green (Crayola)'=>[0x1cac78,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Green_.28Crayola.29']],
                'Green (Munsell)'=>[0x00a877,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Green_.28Munsell.29']],
                'Green (NCS)'=>[0x009f6b,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Green_.28NCS.29']],
                'Green (Pantone)'=>[0x00ad43,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Green_.28Pantone.29']],
                'Green (pigment)'=>[0x00a550,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Green_.28CMYK.29_.28pigment_green.29']],
                'Green (RYB)'=>[0x66b032,['url'=>'http://en.wikipedia.org/wiki/RYB_color_model']],
                'Green-blue'=>[0x1164b4,['url'=>'http://en.wikipedia.org/wiki/Blue-green']],
                'Green-cyan'=>[0x009966,['url'=>'http://en.wikipedia.org/wiki/Shades_of_cyan']],
                'Green Lizard'=>[0xa7f432,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Green Sheen'=>[0x6eaea1,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Green-yellow'=>[0xadff2f,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Yellow-green']],
                'Grizzly'=>[0x885818,['url'=>'http://en.wikipedia.org/wiki/Grizzly']],
                'Grullo'=>[0xa99a86,['url'=>'http://en.wikipedia.org/wiki/Grullo']],
                'Spring green'=>[0x00ff7f,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)']],
                'Gunmetal'=>[0x2a3439,['url'=>'http://en.wikipedia.org/wiki/Gunmetal#Color']],
                'Han blue'=>[0x446ccf,['url'=>'http://en.wikipedia.org/wiki/Han_blue']],
                'Han purple'=>[0x5218fa,['url'=>'http://en.wikipedia.org/wiki/Han_purple']],
                'Harlequin'=>[0x3fff00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Harlequin']],
                'Harlequin green'=>[0x46cb18,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Harlequin']],
                'Harvard crimson'=>[0xc90016,['url'=>'http://en.wikipedia.org/wiki/Crimson#Harvard_crimson']],
                'Harvest gold'=>[0xda9100,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#Harvest_gold']],
                'Olive'=>[0x808000,['url'=>'http://en.wikipedia.org/wiki/Olive_(color)']],
                'Heat Wave'=>[0xff7a00,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Extreme_Twistables_colors']],
                'Heliotrope'=>[0xdf73ff,['url'=>'http://en.wikipedia.org/wiki/Heliotrope_(color)']],
                'Rose quartz'=>[0xaa98a9,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rose_quartz']],
                'Heliotrope magenta'=>[0xaa00bb,['url'=>'http://en.wikipedia.org/wiki/Heliotrope_(color)']],
                'Honeydew'=>[0xf0fff0,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)#Honeydew']],
                'Honolulu blue'=>[0x006db0,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Honolulu_blue']],
                'Hooker\'s green'=>[0x49796b,['url'=>'http://en.wikipedia.org/wiki/Hooker%27s_green']],
                'Hot magenta'=>[0xff1dce,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Hot_magenta']],
                'Hot pink'=>[0xff69b4,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Hot_pink']],
                'Icterine'=>[0xfcf75e,['url'=>'http://en.wikipedia.org/wiki/Icterine']],
                'Iguana Green'=>[0x71bc78,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Illuminating Emerald'=>[0x319177,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Imperial'=>[0x602f6b,['url'=>'http://en.wikipedia.org/wiki/Byzantium_(color)#Imperial']],
                'Imperial blue'=>[0x002395,['url'=>'http://en.wikipedia.org/wiki/Indigo#Imperial_blue']],
                'Tyrian purple'=>[0x66023c,['url'=>'http://en.wikipedia.org/wiki/Tyrian_purple#Tyrian_purple']],
                'Red (Pantone)'=>[0xed2939,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Red_.28Pantone.29']],
                'Inchworm'=>[0xb2ec5d,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Independence'=>[0x4c516d,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Independence']],
                'India green'=>[0x138808,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#India_green']],
                'Indian red'=>[0xcd5c5c,['url'=>'http://en.wikipedia.org/wiki/Indian_red_(color)']],
                'Indian yellow'=>[0xe3a857,['url'=>'http://en.wikipedia.org/wiki/Indian_yellow']],
                'Indigo (web)'=>[0x4b0082,['url'=>'http://en.wikipedia.org/wiki/Indigo#Pigment_indigo_.28web_color_indigo.29']],
                'Indigo dye'=>[0x091f92,['url'=>'http://en.wikipedia.org/wiki/Indigo#Indigo_dye']],
                'Infra Red'=>[0xff496c,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'International Klein Blue'=>[0x002fa7,['url'=>'http://en.wikipedia.org/wiki/International_Klein_Blue']],
                'International orange (aerospace)'=>[0xff4f00,['url'=>'http://en.wikipedia.org/wiki/International_orange']],
                'International orange (engineering)'=>[0xba160c,['url'=>'http://en.wikipedia.org/wiki/International_orange#International_orange_.28Engineering.29']],
                'International orange (Golden Gate Bridge)'=>[0xc0362c,['url'=>'http://en.wikipedia.org/wiki/International_orange#Golden_Gate_Bridge']],
                'Iris'=>[0x5a4fcf,['url'=>'http://en.wikipedia.org/wiki/Iris_(color)']],
                'Raspberry rose'=>[0xb3446c,['url'=>'http://en.wikipedia.org/wiki/Raspberry_(color)#Raspberry_rose']],
                'Isabelline'=>[0xf4f0ec,['url'=>'http://en.wikipedia.org/wiki/Isabelline_(colour)']],
                'Islamic green'=>[0x009000,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Islamic_green']],
                'Ivory'=>[0xfffff0,['url'=>'http://en.wikipedia.org/wiki/Ivory_(color)']],
                'Japanese carmine'=>[0x9d2933,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Japanese_carmine']],
                'Japanese indigo'=>[0x264348,['url'=>'http://en.wikipedia.org/wiki/Indigo#Japanese_indigo']],
                'Japanese violet'=>[0x5b3256,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#Japanese_violet']],
                'Mellow yellow'=>[0xf8de7e,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Mellow_yellow']],
                'Jasper'=>[0xd73b3e,['url'=>'http://en.wikipedia.org/wiki/Jasper']],
                'Jazzberry jam'=>[0xa50b5e,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Jazzberry_jam']],
                'Jelly Bean'=>[0xda614e,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Jet'=>[0x343434,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black_(colors)#Jet']],
                'Jonquil'=>[0xf4ca16,['url'=>'http://en.wikipedia.org/wiki/Jonquil_(color)']],
                'Jordy blue'=>[0x8ab9f1,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Jordy_blue']],
                'June bud'=>[0xbdda57,['url'=>'http://en.wikipedia.org/wiki/Spring_bud#June_bud']],
                'Jungle green'=>[0x29ab87,['url'=>'http://en.wikipedia.org/wiki/Jungle_green']],
                'Kenyan copper'=>[0x7c1c05,['url'=>'http://en.wikipedia.org/wiki/Copper_(color)#Kenyan_copper']],
                'Keppel'=>[0x3ab09e,['url'=>'http://en.wikipedia.org/wiki/Variations_of_cyan#Keppel']],
                'Key Lime'=>[0xe8f48c,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Pearl_Brite']],
                'Khaki (HTML/CSS) (Khaki)'=>[0xc3b091,['url'=>'http://en.wikipedia.org/wiki/Khaki_(color)#Khaki']],
                'Light khaki'=>[0xf0e68c,['url'=>'http://en.wikipedia.org/wiki/Khaki_(color)#Light_khaki']],
                'Kiwi'=>[0x8ee53f,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Sienna'=>[0x882d17,['url'=>'http://en.wikipedia.org/wiki/Sienna']],
                'Kobi'=>[0xe79fc4,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Kobi']],
                'Kombu green'=>[0x354230,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Kombu_green']],
                'KSU Purple'=>[0x512888,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#KSU_Purple']],
                'KU Crimson'=>[0xe8000d,['url'=>'http://en.wikipedia.org/wiki/Crimson#KU_Crimson']],
                'Languid lavender'=>[0xd6cadd,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Languid_lavender']],
                'Lapis lazuli'=>[0x26619c,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Lapis_lazuli']],
                'Unmellow yellow'=>[0xffff66,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Unmellow_yellow']],
                'Laurel green'=>[0xa9ba9d,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Laurel_green']],
                'Lava'=>[0xcf1020,['url'=>'http://en.wikipedia.org/wiki/Lava_(color)']],
                'Lavender (floral)'=>[0xb57edc,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Lavender_.28floral.29']],
                'Lavender mist'=>[0xe6e6fa,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Lavender_mist']],
                'Periwinkle'=>[0xccccff,['url'=>'http://en.wikipedia.org/wiki/Periwinkle_(color)']],
                'Lavender blush'=>[0xfff0f5,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Lavender_blush']],
                'Lavender gray'=>[0xc4c3d0,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Lavender_gray']],
                'Navy purple'=>[0x9457eb,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Navy_purple']],
                'Violet (web)'=>[0xee82ee,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#Web_color_.22violet.22']],
                'Lavender pink'=>[0xfbaed2,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Lavender_pink']],
                'Lavender purple'=>[0x967bb6,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Lavender_purple_.28purple_mountain_majesty.29']],
                'Lavender rose'=>[0xfba0e3,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Lavender_rose']],
                'Lawn green'=>[0x7cfc00,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Lawn_green']],
                'Yellow Sunshine'=>[0xfff700,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Lemon chiffon'=>[0xfffacd,['url'=>'http://en.wikipedia.org/wiki/Lemon_chiffon']],
                'Lemon curry'=>[0xcca01d,['url'=>'http://en.wikipedia.org/wiki/Lemon_(color)#Lemon_curry']],
                'Lemon glacier'=>[0xfdff00,['url'=>'http://en.wikipedia.org/wiki/Lemon_(color)#Lemon_glacier']],
                'Lemon lime'=>[0xe3ff00,['url'=>'http://en.wikipedia.org/wiki/Lemon_lime']],
                'Lemon meringue'=>[0xf6eabe,['url'=>'http://en.wikipedia.org/wiki/Lemon_(color)#Lemon_meringue']],
                'Lemon yellow'=>[0xfff44f,['url'=>'http://en.wikipedia.org/wiki/Lemon_(color)#Lemon_yellow']],
                'Licorice'=>[0x1a1110,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black_(colors)#Licorice']],
                'Liberty'=>[0x545aa7,['url'=>'http://en.wikipedia.org/wiki/Variations_of_blue#Liberty']],
                'Light blue'=>[0xadd8e6,['url'=>'http://en.wikipedia.org/wiki/Blue#Light_blue']],
                'Light brown'=>[0xb5651d,['url'=>'http://en.wikipedia.org/wiki/Light_brown']],
                'Light carmine pink'=>[0xe66771,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Light_Carmine_Pink']],
                'Light cobalt blue'=>[0x88ace0,['url'=>'http://en.wikipedia.org/wiki/Cobalt_blue']],
                'Light coral'=>[0xf08080,['url'=>'http://en.wikipedia.org/wiki/Coral_(color)#Light_coral']],
                'Light cornflower blue'=>[0x93ccea,['url'=>'http://en.wikipedia.org/wiki/Cornflower_blue#Light_cornflower_blue']],
                'Light crimson'=>[0xf56991,['url'=>'http://en.wikipedia.org/wiki/Crimson#Light_Crimson']],
                'Light cyan'=>[0xe0ffff,['url'=>'http://en.wikipedia.org/wiki/Cyan#Light_cyan']],
                'Light deep pink'=>[0xff5ccd,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Light_deep_pink']],
                'Light French beige'=>[0xc8ad7f,['url'=>'http://en.wikipedia.org/wiki/Beige#Light_French_beige']],
                'Light fuchsia pink'=>[0xf984ef,['url'=>'http://en.wikipedia.org/wiki/Fuchsia_(color)#Light_fuchsia_pink_.28pale_magenta.29']],
                'Light goldenrod yellow'=>[0xfafad2,['url'=>'http://en.wikipedia.org/wiki/Goldenrod_(color)#Light_goldenrod_yellow']],
                'Light gray'=>[0xd3d3d3,['url'=>'http://en.wikipedia.org/wiki/Grey#Web_colors']],
                'Light grayish magenta'=>[0xcc99cc,['url'=>'http://en.wikipedia.org/wiki/Shades_of_magenta']],
                'Light green'=>[0x90ee90,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_names']],
                'Light hot pink'=>[0xffb3de,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Light_hot_pink']],
                'Light medium orchid'=>[0xd39bcb,['url'=>'http://en.wikipedia.org/wiki/Orchid_(color)#Light_medium_orchid']],
                'Light moss green'=>[0xaddfad,['url'=>'http://en.wikipedia.org/wiki/Moss_green#Light_moss_green']],
                'Light orchid'=>[0xe6a8d7,['url'=>'http://en.wikipedia.org/wiki/Orchid_(color)#Light_orchid']],
                'Light pastel purple'=>[0xb19cd9,['url'=>'http://en.wikipedia.org/wiki/Purple#Light_pastel_purple']],
                'Light pink'=>[0xffb6c1,['url'=>'http://en.wikipedia.org/wiki/Pink#Light_pink']],
                'Light salmon'=>[0xffa07a,['url'=>'http://en.wikipedia.org/wiki/Salmon_(color)#Light_salmon']],
                'Light salmon pink'=>[0xff9999,['url'=>'http://en.wikipedia.org/wiki/Salmon_pink#Light_salmon_pink']],
                'Light sea green'=>[0x20b2aa,['url'=>'http://en.wikipedia.org/wiki/Cyan#Light_sea_green']],
                'Light sky blue'=>[0x87cefa,['url'=>'http://en.wikipedia.org/wiki/Sky_blue#Light_sky_blue']],
                'Light slate gray'=>[0x778899,['url'=>'http://en.wikipedia.org/wiki/Slate_gray#Light_slate_gray']],
                'Light steel blue'=>[0xb0c4de,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_name_charts']],
                'Light taupe'=>[0xb38b6d,['url'=>'http://en.wikipedia.org/wiki/Taupe#Light_taupe']],
                'Light yellow'=>[0xffffe0,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Light_yellow']],
                'Lilac'=>[0xc8a2c8,['url'=>'http://en.wikipedia.org/wiki/Lilac_(color)']],
                'Lilac Luster'=>[0xae98aa,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Lime green'=>[0x32cd32,['url'=>'http://en.wikipedia.org/wiki/Lime_(color)#Lime_green']],
                'Limerick'=>[0x9dc209,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Limerick']],
                'Lincoln green'=>[0x195905,['url'=>'http://en.wikipedia.org/wiki/Lincoln_green']],
                'Linen'=>[0xfaf0e6,['url'=>'http://en.wikipedia.org/wiki/Linen_(color)']],
                'Little boy blue'=>[0x6ca0dc,['url'=>'http://en.wikipedia.org/wiki/Baby_blue#Little_boy_blue']],
                'Medium taupe'=>[0x674c47,['url'=>'http://en.wikipedia.org/wiki/Taupe#Medium_taupe']],
                'Liver (dogs)'=>[0xb86d29,['url'=>'http://en.wikipedia.org/wiki/Liver_(color)#Liver_.28dogs.29']],
                'Liver (organ)'=>[0x6c2e1f,['url'=>'http://en.wikipedia.org/wiki/Liver_(color)#Liver_.28organ.29']],
                'Liver chestnut'=>[0x987456,['url'=>'http://en.wikipedia.org/wiki/Liver_(color)#Liver_.28organ.29']],
                'Lumber'=>[0xffe4cd,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Lust'=>[0xe62020,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Lust']],
                'Maastricht Blue'=>[0x001c3d,['url'=>'http://en.wikipedia.org/wiki/Maastricht_University']],
                'Madder Lake'=>[0xcc3336,['url'=>'http://en.wikipedia.org/wiki/History_of_Crayola_crayons#1903:_the_original_Crayola_colors']],
                'Magenta (dye)'=>[0xca1f7b,['url'=>'http://en.wikipedia.org/wiki/Magenta#Historical_development_of_magenta']],
                'Magenta (Pantone)'=>[0xd0417e,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Magenta_.28Pantone.29']],
                'Magenta (process)'=>[0xff0090,['url'=>'http://en.wikipedia.org/wiki/Magenta#Historical_development_of_magenta']],
                'Magenta haze'=>[0x9f4576,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Magenta_haze']],
                'Magenta-pink'=>[0xcc338b,['url'=>'http://en.wikipedia.org/wiki/Magenta']],
                'Magic mint'=>[0xaaf0d1,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)#Magic_mint']],
                'Magic Potion'=>[0xff4466,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Magnolia'=>[0xf8f4ff,['url'=>'http://en.wikipedia.org/wiki/Magnolia_(color)']],
                'Mahogany'=>[0xc04000,['url'=>'http://en.wikipedia.org/wiki/Mahogany_(color)']],
                'Majorelle Blue'=>[0x6050dc,['url'=>'http://en.wikipedia.org/wiki/Majorelle_Blue']],
                'Malachite'=>[0x0bda51,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Malachite']],
                'Manatee'=>[0x979aaa,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Mandarin'=>[0xf37a48,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Pearl_Brite']],
                'Mango Tango'=>[0xff8243,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Mantis'=>[0x74c365,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Mantis']],
                'Mardi Gras'=>[0x880085,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Mardi_Gras']],
                'Marigold'=>[0xeaa221,['url'=>'http://en.wikipedia.org/wiki/Marigold_(color)']],
                'Maroon (HTML/CSS)'=>[0x800000,['url'=>'http://en.wikipedia.org/wiki/Maroon_(color)']],
                'Rich maroon'=>[0xb03060,['url'=>'http://en.wikipedia.org/wiki/Maroon_(color)#Rich_maroon_.28maroon_.28X11.29.29']],
                'Mauve'=>[0xe0b0ff,['url'=>'http://en.wikipedia.org/wiki/Mauve']],
                'Raspberry glace'=>[0x915f6d,['url'=>'http://en.wikipedia.org/wiki/Raspberry_(color)#Raspberry_glace']],
                'Mauvelous'=>[0xef98aa,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Maximum Blue'=>[0x47abcc,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Maximum Blue Green'=>[0x30bfbf,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Maximum Blue Purple'=>[0xacace6,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Maximum Green'=>[0x5e8c31,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Maximum Green Yellow'=>[0xd9e650,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Maximum Purple'=>[0x733380,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Maximum Red'=>[0xd92121,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Maximum_red']],
                'Maximum Red Purple'=>[0xa63a79,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Maximum Yellow'=>[0xfafa37,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Maximum_yellow']],
                'Maximum Yellow Red'=>[0xf2ba49,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Munsell_Crayola.2C_1926.E2.80.931944']],
                'May green'=>[0x4c9141,['url'=>'http://en.wikipedia.org/wiki/Spring_bud#May_green']],
                'Maya blue'=>[0x73c2fb,['url'=>'http://en.wikipedia.org/wiki/Maya_Blue']],
                'Meat brown'=>[0xe5b73b,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Standard_colors']],
                'Medium aquamarine'=>[0x66ddaa,['url'=>'http://en.wikipedia.org/wiki/Aquamarine_(color)#Medium_aquamarine']],
                'Medium blue'=>[0x0000cd,['url'=>'http://en.wikipedia.org/wiki/Dark_blue_(color)#Medium_blue']],
                'Medium candy apple red'=>[0xe2062c,['url'=>'http://en.wikipedia.org/wiki/Candy_apple_red_(color)#Medium_candy_apple_red']],
                'Pale carmine'=>[0xaf4035,['url'=>'http://en.wikipedia.org/wiki/Pale_carmine']],
                'Vanilla'=>[0xf3e5ab,['url'=>'http://en.wikipedia.org/wiki/Vanilla_(color)']],
                'Medium electric blue'=>[0x035096,['url'=>'http://en.wikipedia.org/wiki/Electric_blue_(color)#Medium_electric_blue']],
                'Medium jungle green'=>[0x1c352d,['url'=>'http://en.wikipedia.org/wiki/Jungle_green#Medium_jungle_green']],
                'Plum (web)'=>[0xdda0dd,['url'=>'http://en.wikipedia.org/wiki/Plum_(color)#Pale_plum']],
                'Medium orchid'=>[0xba55d3,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_names']],
                'Sapphire blue'=>[0x0067a5,['url'=>'http://en.wikipedia.org/wiki/Sapphire_(color)#Sapphire_blue']],
                'Medium purple'=>[0x9370db,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Medium_purple_.28X11.29']],
                'Medium red-violet'=>[0xbb3385,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Medium_red-violet']],
                'Medium ruby'=>[0xaa4069,['url'=>'http://en.wikipedia.org/wiki/Ruby_(color)#Medium_ruby']],
                'Medium sea green'=>[0x3cb371,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)#Medium_sea_green']],
                'Medium sky blue'=>[0x80daeb,['url'=>'http://en.wikipedia.org/wiki/Sky_blue#Medium_sky_blue']],
                'Medium slate blue'=>[0x7b68ee,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_names']],
                'Medium spring bud'=>[0xc9dc87,['url'=>'http://en.wikipedia.org/wiki/Spring_bud#Medium_spring_bud']],
                'Medium spring green'=>[0x00fa9a,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)#Medium_spring_green']],
                'Medium turquoise'=>[0x48d1cc,['url'=>'http://en.wikipedia.org/wiki/Turquoise_(color)#Medium_Turquoise']],
                'Medium vermilion'=>[0xd9603b,['url'=>'http://en.wikipedia.org/wiki/Vermilion#Medium_vermilion']],
                'Red-violet'=>[0xc71585,['url'=>'http://en.wikipedia.org/wiki/Red-violet']],
                'Mellow apricot'=>[0xf8b878,['url'=>'http://en.wikipedia.org/wiki/Apricot_(color)#Mellow_apricot']],
                'Melon'=>[0xfdbcb4,['url'=>'http://en.wikipedia.org/wiki/Variations_of_orange#Melon']],
                'Metallic Seaweed'=>[0x0a7e8c,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Metallic Sunburst'=>[0x9c7c38,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Metal Pink'=>[0xff00fd,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Metal_Pink']],
                'Mexican pink'=>[0xe4007c,['url'=>'http://en.wikipedia.org/wiki/Mexican_pink']],
                'Middle Blue'=>[0x7ed4e6,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Blue Green'=>[0x8dd9cc,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Blue Purple'=>[0x8b72be,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Red Purple'=>[0x8b8680,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Green'=>[0x4d8c57,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Green Yellow'=>[0xacbf60,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Purple'=>[0xd982b5,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Red'=>[0xe58e73,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Yellow'=>[0xffeb00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Middle Yellow Red'=>[0xecb176,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Munsell_Crayola.2C_1926.E2.80.931944']],
                'Midnight'=>[0x702670,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Pearl_Brite']],
                'Midnight blue'=>[0x191970,['url'=>'http://en.wikipedia.org/wiki/Midnight_blue']],
                'Midnight green (eagle green)'=>[0x004953,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Midnight_green']],
                'Mikado yellow'=>[0xffc40c,['url'=>'http://en.wikipedia.org/wiki/Mikado_yellow']],
                'Mimi Pink'=>[0xffdae9,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Mimi_pink']],
                'Mindaro'=>[0xe3f988,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Mindaro']],
                'Ming'=>[0x36747d,['url'=>'http://en.wikipedia.org/wiki/Blue-green#Ming']],
                'Minion Yellow'=>[0xf5e050,['url'=>'http://en.wikipedia.org/wiki/Minions_(film)#Marketing']],
                'Mint'=>[0x3eb489,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)#Mint']],
                'Mint cream'=>[0xf5fffa,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)#Mint_cream']],
                'Mint green'=>[0x98ff98,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)#Mint_green']],
                'Misty Moss'=>[0xbbb477,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Misty rose'=>[0xffe4e1,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Misty_rose']],
                'Moonstone blue'=>[0x73a9c2,['url'=>'http://en.wikipedia.org/wiki/Moonstone_(gemstone)']],
                'Mordant red 19'=>[0xae0c00,['url'=>'http://en.wikipedia.org/wiki/Mordant_red_19']],
                'Turtle green'=>[0x8a9a5b,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Turtle_green']],
                'Mountain Meadow'=>[0x30ba8f,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Mountbatten pink'=>[0x997a8d,['url'=>'http://en.wikipedia.org/wiki/Mountbatten_pink']],
                'MSU Green'=>[0x18453b,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#MSU_green']],
                'Mughal green'=>[0x306030,['url'=>'http://en.wikipedia.org/wiki/Moss_green#Mughal_green']],
                'Mulberry'=>[0xc54b8c,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Mulberry']],
                'Mustard'=>[0xffdb58,['url'=>'http://en.wikipedia.org/wiki/Mustard_(color)']],
                'Myrtle green'=>[0x317873,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Myrtle_green']],
                'Mystic'=>[0xd65282,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Pearl_Brite']],
                'Mystic Maroon'=>[0xad4379,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Napier green'=>[0x2a8000,['url'=>'http://en.wikipedia.org/wiki/British_racing_green']],
                'Stil de grain yellow'=>[0xfada5e,['url'=>'http://en.wikipedia.org/wiki/Stil_de_grain_yellow']],
                'Navajo white'=>[0xffdead,['url'=>'http://en.wikipedia.org/wiki/Navajo_white']],
                'Navy'=>[0x000080,['url'=>'http://en.wikipedia.org/wiki/Navy_blue']],
                'Neon Carrot'=>[0xffa343,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Fluorescent_crayons']],
                'Neon fuchsia'=>[0xfe4164,['url'=>'http://en.wikipedia.org/wiki/Fuchsia_(color)#Neon_fuchsia']],
                'Neon green'=>[0x39ff14,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Neon_green']],
                'New Car'=>[0x214fc6,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'New York pink'=>[0xd7837f,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#New_York_pink']],
                'Nickel'=>[0x727472,['url'=>'http://en.wikipedia.org/wiki/Shades_of_gray#Nickel']],
                'Non-photo blue'=>[0xa4dded,['url'=>'http://en.wikipedia.org/wiki/Non-photo_blue']],
                'North Texas Green'=>[0x059033,['url'=>'http://www.unt.edu/identityguide/web-electronic.htm']],
                'Nyanza'=>[0xe9ffdb,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Nyanza']],
                'Ocean Boat Blue'=>[0x0077be,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Ocean_boat_blue']],
                'Ocean Green'=>[0x48bf91,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Pearl_Brite']],
                'Ochre'=>[0xcc7722,['url'=>'http://en.wikipedia.org/wiki/Ochre']],
                'Ogre Odor'=>[0xfd5240,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Old burgundy'=>[0x43302e,['url'=>'http://en.wikipedia.org/wiki/Burgundy_(color)#Old_burgundy']],
                'Old gold'=>[0xcfb53b,['url'=>'http://en.wikipedia.org/wiki/Old_gold']],
                'Old lace'=>[0xfdf5e6,['url'=>'http://en.wikipedia.org/wiki/Old_lace_(color)']],
                'Old lavender'=>[0x796878,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Old_lavender']],
                'Wine dregs'=>[0x673147,['url'=>'http://en.wikipedia.org/wiki/Wine_(color)#Wine_dregs']],
                'Old moss green'=>[0x867e36,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Old_moss_green']],
                'Old rose'=>[0xc08081,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Old_rose']],
                'Olive Drab (#3)'=>[0x6b8e23,['url'=>'http://en.wikipedia.org/wiki/Olive_(color)#Olive_drab']],
                'Olive Drab #7'=>[0x3c341f,['url'=>'http://en.wikipedia.org/wiki/Olive_(color)#Olive_drab']],
                'Olivine'=>[0x9ab973,['url'=>'http://en.wikipedia.org/wiki/Olive_(color)#Olivine']],
                'Onyx'=>[0x353839,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black_(colors)#Onyx']],
                'Opera mauve'=>[0xb784a7,['url'=>'http://en.wikipedia.org/wiki/Mauve#Opera_mauve']],
                'Orange (color wheel)'=>[0xff7f00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Orange_.28color_wheel.29']],
                'Orange (Crayola)'=>[0xff7538,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Orange_.28Crayola.29']],
                'Orange (Pantone)'=>[0xff5800,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Orange_.28Pantone.29']],
                'Orange (RYB)'=>[0xfb9902,['url'=>'http://en.wikipedia.org/wiki/RYB_color_model']],
                'Orange (web)'=>[0xffa500,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Orange_.28web_color.29']],
                'Orange peel'=>[0xff9f00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Orange_peel']],
                'Orange-red'=>[0xff4500,['url'=>'http://en.wikipedia.org/wiki/Vermilion#Orange-red']],
                'Orange Soda'=>[0xfa5b3d,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Orange-yellow'=>[0xf8d568,['url'=>'http://en.wikipedia.org/wiki/Marigold_(color)#Orange-yellow']],
                'Orchid'=>[0xda70d6,['url'=>'http://en.wikipedia.org/wiki/Orchid_(colour)']],
                'Orchid pink'=>[0xf2bdcd,['url'=>'http://en.wikipedia.org/wiki/Orchid_(colour)#Orchid_pink']],
                'Orioles orange'=>[0xfb4f14,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange']],
                'Outer Space'=>[0x414a4c,['url'=>'http://en.wikipedia.org/wiki/Shades_of_black#Outer_space']],
                'Outrageous Orange'=>[0xff6e4a,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Fluorescent_crayons']],
                'Oxford Blue'=>[0x002147,['url'=>'http://en.wikipedia.org/wiki/Oxford_University']],
                'Pakistan green'=>[0x006600,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Pakistan_green']],
                'Palatinate blue'=>[0x273be2,['url'=>'http://en.wikipedia.org/wiki/Palatinate_(colour)#Palatinate_Blue']],
                'Palatinate purple'=>[0x682860,['url'=>'http://en.wikipedia.org/wiki/Palatinate_(colour)#Palatinate_Purple']],
                'Pale turquoise'=>[0xafeeee,['url'=>'http://en.wikipedia.org/wiki/Turquoise_(color)#Pale_turquoise']],
                'Pale brown'=>[0x987654,['url'=>'http://en.wikipedia.org/wiki/Brown#Pale_brown']],
                'Pale cerulean'=>[0x9bc4e2,['url'=>'http://en.wikipedia.org/wiki/Cerulean#Pale_cerulean']],
                'Pale chestnut'=>[0xddadaf,['url'=>'http://en.wikipedia.org/wiki/Pale_chestnut']],
                'Pale cornflower blue'=>[0xabcdef,['url'=>'http://en.wikipedia.org/wiki/Pale_cornflower_blue']],
                'Pale cyan'=>[0x87d3f8,['url'=>'http://en.wikipedia.org/wiki/Cyan']],
                'Pale gold'=>[0xe6be8a,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#Pale_gold']],
                'Pale goldenrod'=>[0xeee8aa,['url'=>'http://en.wikipedia.org/wiki/Goldenrod_(color)#Light_goldenrod']],
                'Pale green'=>[0x98fb98,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Mint_green']],
                'Pale lavender'=>[0xdcd0ff,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Pale_lavender']],
                'Pale magenta'=>[0xf984e5,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Pale_magenta_.28light_fuchsia_pink.29']],
                'Pale magenta-pink'=>0xff99cc,
                'Pale pink'=>[0xfadadd,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Pale_pink']],
                'Pale violet-red'=>[0xdb7093,['url'=>'http://en.wikipedia.org/wiki/Red-violet']],
                'Pale robin egg blue'=>[0x96ded1,['url'=>'http://en.wikipedia.org/wiki/Robin_egg_blue#Pale_robin_egg_blue']],
                'Pale silver'=>[0xc9c0bb,['url'=>'http://en.wikipedia.org/wiki/Silver_(color)#Pale_silver']],
                'Pale spring bud'=>[0xecebbd,['url'=>'http://en.wikipedia.org/wiki/Spring_bud#Pale_spring_bud']],
                'Pale taupe'=>[0xbc987e,['url'=>'http://en.wikipedia.org/wiki/Taupe#Pale_taupe_.28mouse.29']],
                'Pale violet'=>[0xcc99ff,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet']],
                'Palm Leaf'=>[0x6f9940,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Pansy purple'=>[0x78184a,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Pansy_purple']],
                'Paolo Veronese green'=>[0x009b7d,['url'=>'http://en.wikipedia.org/wiki/Viridian#Paolo_Veronese_green']],
                'Papaya whip'=>[0xffefd5,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Papaya_whip']],
                'Paradise pink'=>[0xe63e62,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Paradise_pink']],
                'Parrot Pink'=>[0xd998a0,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Pastel blue'=>[0xaec6cf,['url'=>'http://en.wikipedia.org/wiki/Blue#Pastel_blue']],
                'Pastel brown'=>[0x836953,['url'=>'http://en.wikipedia.org/wiki/Brown#Pastel_brown']],
                'Pastel gray'=>[0xcfcfc4,['url'=>'http://en.wikipedia.org/wiki/Grey#Pastel_gray']],
                'Pastel green'=>[0x77dd77,['url'=>'http://en.wikipedia.org/wiki/Pastel_green']],
                'Pastel magenta'=>[0xf49ac2,['url'=>'http://en.wikipedia.org/wiki/Magenta#Pastel_magenta']],
                'Pastel orange'=>[0xffb347,['url'=>'http://en.wikipedia.org/wiki/Orange_(colour)#Pastel_orange']],
                'Pastel pink'=>[0xdea5a4,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Pastel_pink']],
                'Pastel purple'=>[0xb39eb5,['url'=>'http://en.wikipedia.org/wiki/Purple#Pastel_purple']],
                'Pastel red'=>[0xff6961,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Pastel_red']],
                'Pastel violet'=>[0xcb99c9,['url'=>'http://en.wikipedia.org/wiki/Violet_(color)#Pastel_violet']],
                'Pastel yellow'=>[0xfdfd96,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Pastel_yellow']],
                'Purple (HTML)'=>[0x800080,['url'=>'http://en.wikipedia.org/wiki/Purple#Purple_.28HTML.2FCSS_color.29_.28patriarch.29']],
                'Peach-orange'=>[0xffcc99,['url'=>'http://en.wikipedia.org/wiki/Peach_(color)#Peach-orange']],
                'Peach puff'=>[0xffdab9,['url'=>'http://en.wikipedia.org/wiki/Peach_(color)#Peach_puff']],
                'Peach-yellow'=>[0xfadfad,['url'=>'http://en.wikipedia.org/wiki/Peach_(color)#Peach-yellow']],
                'Pear'=>[0xd1e231,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Pear']],
                'Pearl'=>[0xeae0c8,['url'=>'http://en.wikipedia.org/wiki/Pearl_(color)']],
                'Pearl Aqua'=>[0x88d8c0,['url'=>'http://en.wikipedia.org/wiki/Aqua_(color)']],
                'Pearly purple'=>[0xb768a2,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Pearly_purple']],
                'Peridot'=>[0xe6e200,['url'=>'http://en.wikipedia.org/wiki/Peridot']],
                'Permanent Geranium Lake'=>[0xe12c2c,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Persian blue'=>[0x1c39bb,['url'=>'http://en.wikipedia.org/wiki/Persian_blue']],
                'Persian green'=>[0x00a693,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Persian_green']],
                'Persian indigo'=>[0x32127a,['url'=>'http://en.wikipedia.org/wiki/Persian_blue#Persian_indigo']],
                'Persian orange'=>[0xd99058,['url'=>'http://en.wikipedia.org/wiki/Persian_orange']],
                'Persian pink'=>[0xf77fbe,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Persian_pink']],
                'Prune'=>[0x701c1c,['url'=>'http://en.wikipedia.org/wiki/Plum_(color)#Persian_plum_.28prune.29']],
                'Persian red'=>[0xcc3333,['url'=>'http://en.wikipedia.org/wiki/Persian_red']],
                'Persian rose'=>[0xfe28a2,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Persian_rose']],
                'Persimmon'=>[0xec5800,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Persimmon']],
                'Peru'=>[0xcd853f,['url'=>'http://en.wikipedia.org/wiki/Brown#Peru']],
                'Pewter Blue'=>[0x8ba8b7,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Psychedelic purple'=>[0xdf00ff,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Psychedelic_purple_.28phlox.29']],
                'Phthalo blue'=>[0x000f89,['url'=>'http://en.wikipedia.org/wiki/Phthalocyanine_Blue_BN']],
                'Phthalo green'=>[0x123524,['url'=>'http://en.wikipedia.org/wiki/Phthalocyanine_Green_G']],
                'Picton blue'=>[0x45b1e8,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Picton_blue']],
                'Pictorial carmine'=>[0xc30b4e,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Pictorial_carmine']],
                'Piggy pink'=>[0xfddde6,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Piggy_pink']],
                'Pine green'=>[0x01796f,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Pine_green']],
                'Pink'=>[0xffc0cb,['url'=>'http://en.wikipedia.org/wiki/Pink']],
                'Pink (Pantone)'=>[0xd74894,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Pink_.28Pantone.29']],
                'Pink Flamingo'=>[0xfc74fd,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Pink lace'=>[0xffddf4,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Pink_lace']],
                'Pink lavender'=>[0xd8b2d1,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Pink_lavender']],
                'Pink pearl'=>[0xe7accf,['url'=>'http://en.wikipedia.org/wiki/Pearl']],
                'Pink raspberry'=>0x980036,
                'Pink Sherbet'=>[0xf78fa7,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors']],
                'Pistachio'=>[0x93c572,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Pistachio']],
                'Pixie Powder'=>[0x391285,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Platinum'=>[0xe5e4e2,['url'=>'http://en.wikipedia.org/wiki/Platinum_(color)']],
                'Plum'=>[0x8e4585,['url'=>'http://en.wikipedia.org/wiki/Plum_(color)']],
                'Plump Purple'=>[0x5946b2,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Polished Pine'=>[0x5da493,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Popstar'=>[0xbe4f62,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Popstar']],
                'Portland Orange'=>[0xff5a36,['url'=>'http://en.wikipedia.org/wiki/Portland_Orange']],
                'Powder blue'=>[0xb0e0e6,['url'=>'http://en.wikipedia.org/wiki/Powder_blue']],
                'Princess Perfume'=>[0xff85cf,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Princeton orange'=>[0xf58025,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Princeton_orange']],
                'Prussian blue'=>[0x003153,['url'=>'http://en.wikipedia.org/wiki/Prussian_blue']],
                'Puce'=>[0xcc8899,['url'=>'http://en.wikipedia.org/wiki/Puce']],
                'Wine'=>[0x722f37,['url'=>'http://en.wikipedia.org/wiki/Wine_(color)']],
                'Pullman Brown (UPS Brown)'=>[0x644117,['url'=>'http://en.wikipedia.org/wiki/Brown#Business']],
                'Pullman Green'=>0x3b331c,
                'Pumpkin'=>[0xff7518,['url'=>'http://en.wikipedia.org/wiki/Pumpkin_(color)']],
                'Purple (Munsell)'=>[0x9f00c5,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Purple_.28Munsell.29']],
                'Veronica'=>[0xa020f0,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Purple_.28X11_color.29_.28veronica.29']],
                'Purple Heart'=>[0x69359c,['url'=>'http://en.wikipedia.org/wiki/Purple_Heart']],
                'Purple mountain majesty'=>[0x9678b6,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Lavender_purple_.28purple_mountain_majesty.29']],
                'Purple navy'=>[0x4e5180,['url'=>'http://en.wikipedia.org/wiki/Navy_blue#Purple_navy']],
                'Purple pizzazz'=>[0xfe4eda,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Purple_pizzazz']],
                'Purple Plum'=>[0x9c51b6,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Purple taupe'=>[0x50404d,['url'=>'http://en.wikipedia.org/wiki/Taupe#Purple_taupe']],
                'Purpureus'=>[0x9a4eae,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Purpureus']],
                'Queen blue'=>[0x436b95,['url'=>'http://en.wikipedia.org/wiki/Royal_blue#Queen_blue']],
                'Queen pink'=>[0xe8ccd7,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Queen_pink']],
                'Quick Silver'=>[0xa6a6a6,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Quinacridone magenta'=>[0x8e3a59,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Quinacridone_magenta']],
                'Radical Red'=>[0xff355e,['url'=>'http://en.wikipedia.org/wiki/Amaranth_(color)#Radical_red_.28bright_amaranth_pink.29']],
                'Raisin black'=>[0x242124,['url'=>'http://en.wikipedia.org/wiki/Raisin_black']],
                'Rajah'=>[0xfbab60,['url'=>'http://en.wikipedia.org/wiki/Saffron_(color)#Rajah']],
                'Raspberry'=>[0xe30b5d,['url'=>'http://en.wikipedia.org/wiki/Raspberry_(color)']],
                'Raspberry pink'=>[0xe25098,['url'=>'http://en.wikipedia.org/wiki/Raspberry_(color)#Raspberry_pink']],
                'Raw Sienna'=>[0xd68a59,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Raw umber'=>[0x826644,['url'=>'http://en.wikipedia.org/wiki/Umber#Raw_umber']],
                'Razzle dazzle rose'=>[0xff33cc,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Razzle_dazzle_rose']],
                'Razzmatazz'=>[0xe3256b,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Razzmatazz']],
                'Razzmic Berry'=>[0x8d4e85,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Rebecca Purple'=>[0x663399,['url'=>'http://en.wikipedia.org/wiki/Eric_A._Meyer']],
                'Red'=>[0xff0000,['url'=>'http://en.wikipedia.org/wiki/Red']],
                'Red (Crayola)'=>[0xee204d,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Red_.28Crayola.29']],
                'Red (Munsell)'=>[0xf2003c,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Red_.28Munsell.29']],
                'Red (NCS)'=>[0xc40233,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Red_.28NCS.29_.28psychological_primary_red.29']],
                'Red (pigment)'=>[0xed1c24,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Pigment_red']],
                'Red (RYB)'=>[0xfe2712,['url'=>'http://en.wikipedia.org/wiki/RYB_color_model']],
                'Red devil'=>[0x860111,['url'=>'http://en.wikipedia.org/wiki/Crimson#Red_devil']],
                'Red-orange'=>[0xff5349,['url'=>'http://en.wikipedia.org/wiki/Vermilion#Red-orange']],
                'Red-purple'=>[0xe40078,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Red-purple']],
                'Red Salsa'=>[0xfd3a4a,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Redwood'=>[0xa45a52,['url'=>'http://en.wikipedia.org/wiki/Redwood_(color)']],
                'Regalia'=>[0x522d80,['url'=>'http://en.wikipedia.org/wiki/Regalia']],
                'Resolution blue'=>[0x002387,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Resolution_blue']],
                'Rhythm'=>[0x777696,['url'=>'http://en.wikipedia.org/wiki/Blue-gray#Rhythm']],
                'Rich black'=>[0x004040,['url'=>'http://en.wikipedia.org/wiki/Rich_black']],
                'Rich black (FOGRA29)'=>[0x010b13,['url'=>'http://en.wikipedia.org/wiki/Rich_black']],
                'Rich black (FOGRA39)'=>[0x010203,['url'=>'http://en.wikipedia.org/wiki/Rich_black']],
                'Rich brilliant lavender'=>[0xf1a7fe,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Rich_brilliant_lavender']],
                'Rich electric blue'=>[0x0892d0,['url'=>'http://en.wikipedia.org/wiki/Electric_blue_(color)#Rich_electric_blue']],
                'Rich lavender'=>[0xa76bcf,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Rich_Lavender_.28deep_floral_lavender.29']],
                'Rich lilac'=>[0xb666d2,['url'=>'http://en.wikipedia.org/wiki/Lilac_(color)#Rich_lilac']],
                'Rifle green'=>[0x444c38,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Rifle_green']],
                'Robin egg blue'=>[0x00cccc,['url'=>'http://en.wikipedia.org/wiki/Robin_egg_blue']],
                'Rocket metallic'=>[0x8a7f80,['url'=>'http://en.wikipedia.org/wiki/Variations_of_gray#Rocket_metallic']],
                'Roman silver'=>[0x838996,['url'=>'http://en.wikipedia.org/wiki/Silver_(color)#Roman_silver']],
                'Rose bonbon'=>[0xf9429e,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rose_bonbon']],
                'Rose Dust'=>[0x9e5e6f,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Rose ebony'=>[0x674846,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rose_ebony']],
                'Rose gold'=>[0xb76e79,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rose_Gold']],
                'Rose pink'=>[0xff66cc,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rose_pink']],
                'Rose red'=>[0xc21e56,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rose_red']],
                'Rose taupe'=>[0x905d5d,['url'=>'http://en.wikipedia.org/wiki/Taupe#Rose_taupe']],
                'Rose vale'=>[0xab4e52,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rose_vale']],
                'Rosewood'=>[0x65000b,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rosewood']],
                'Rosso corsa'=>[0xd40000,['url'=>'http://en.wikipedia.org/wiki/Rosso_corsa']],
                'Rosy brown'=>[0xbc8f8f,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Rosy_brown']],
                'Royal azure'=>[0x0038a8,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Royal_azure']],
                'Royal blue'=>[0x002366,['url'=>'http://en.wikipedia.org/wiki/Royal_blue']],
                'Royal fuchsia'=>[0xca2c92,['url'=>'http://en.wikipedia.org/wiki/Fuchsia_(color)#Royal_fuchsia']],
                'Royal purple'=>[0x7851a9,['url'=>'http://en.wikipedia.org/wiki/Shades_of_purple#Royal_purple:_1600s']],
                'Ruber'=>[0xce4676,['url'=>'http://en.wikipedia.org/wiki/Ruby_(color)#Ruber']],
                'Rubine red'=>[0xd10056,['url'=>'http://en.wikipedia.org/wiki/Ruby_(color)#Rubine_red']],
                'Ruby'=>[0xe0115f,['url'=>'http://en.wikipedia.org/wiki/Ruby_(color)']],
                'Ruby red'=>[0x9b111e,['url'=>'http://en.wikipedia.org/wiki/Ruby_(color)#Ruby_red']],
                'Ruddy'=>[0xff0028,['url'=>'http://en.wikipedia.org/wiki/Ruddy']],
                'Ruddy brown'=>[0xbb6528,['url'=>'http://en.wikipedia.org/wiki/Ruddy#Ruddy_brown']],
                'Ruddy pink'=>[0xe18e96,['url'=>'http://en.wikipedia.org/wiki/Ruddy#Ruddy_pink']],
                'Rufous'=>[0xa81c07,['url'=>'http://en.wikipedia.org/wiki/Rufous']],
                'Russet'=>[0x80461b,['url'=>'http://en.wikipedia.org/wiki/Russet_(color)']],
                'Russian green'=>[0x679267,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Russian_green']],
                'Russian violet'=>[0x32174d,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#Russian_violet']],
                'Rust'=>[0xb7410e,['url'=>'http://en.wikipedia.org/wiki/Rust_(color)']],
                'Rusty red'=>[0xda2c43,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Rusty_red']],
                'Saddle brown'=>[0x8b4513,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_name_charts']],
                'Safety orange'=>[0xff7800,['url'=>'http://en.wikipedia.org/wiki/Safety_orange']],
                'Safety orange (blaze orange)'=>[0xff6700,['url'=>'http://en.wikipedia.org/wiki/Safety_orange']],
                'Safety yellow'=>[0xeed202,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Safety_yellow']],
                'Saffron'=>[0xf4c430,['url'=>'http://en.wikipedia.org/wiki/Saffron_(color)']],
                'Sage'=>[0xbcb88a,['url'=>'http://en.wikipedia.org/wiki/Sage_(color)']],
                'St. Patrick\'s blue'=>[0x23297a,['url'=>'http://en.wikipedia.org/wiki/St._Patrick%27s_blue']],
                'Salmon'=>[0xfa8072,['url'=>'http://en.wikipedia.org/wiki/Salmon_(color)']],
                'Salmon pink'=>[0xff91a4,['url'=>'http://en.wikipedia.org/wiki/Salmon_pink']],
                'Sandstorm'=>[0xecd540,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Standard_colors']],
                'Sandy brown'=>[0xf4a460,['url'=>'http://en.wikipedia.org/wiki/Sandy_brown']],
                'Sandy Tan'=>[0xfdd9b5,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Sangria'=>[0x92000a,['url'=>'http://en.wikipedia.org/wiki/Sangria']],
                'Sap green'=>[0x507d2a,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Sap_green']],
                'Sapphire'=>[0x0f52ba,['url'=>'http://en.wikipedia.org/wiki/Sapphire_(color)#Sapphire']],
                'Sasquatch Socks'=>[0xff4681,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Satin sheen gold'=>[0xcba135,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#Satin_sheen_gold']],
                'Scarlet'=>[0xff2400,['url'=>'http://en.wikipedia.org/wiki/Scarlet_(color)']],
                'Tractor red'=>[0xfd0e35,['url'=>'http://en.wikipedia.org/wiki/Scarlet_(color)#Tractor_red']],
                'School bus yellow'=>[0xffd800,['url'=>'http://en.wikipedia.org/wiki/School_bus_yellow']],
                'Screamin\' Green'=>[0x66ff66,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Fluorescent_crayons']],
                'Sea blue'=>[0x006994,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue#Sea_blue']],
                'Sea Foam Green'=>[0x9fe2bf,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Sea green'=>[0x2e8b57,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Sea_green']],
                'Sea Serpent'=>[0x4bc7cf,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Seal brown'=>[0x59260b,['url'=>'http://en.wikipedia.org/wiki/Seal_brown']],
                'Seashell'=>[0xfff5ee,['url'=>'http://en.wikipedia.org/wiki/Seashell_(color)']],
                'Selective yellow'=>[0xffba00,['url'=>'http://en.wikipedia.org/wiki/Selective_yellow']],
                'Sepia'=>[0x704214,['url'=>'http://en.wikipedia.org/wiki/Sepia_(color)']],
                'Shadow'=>[0x8a795d,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Shadow blue'=>[0x778ba5,['url'=>'http://en.wikipedia.org/wiki/Blue-gray#Shadow_blue']],
                'Shampoo'=>[0xffcff1,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Shamrock green'=>[0x009e60,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Shamrock_green']],
                'Sheen Green'=>[0x8fd400,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Shimmering Blush'=>[0xd98695,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Metallic_FX']],
                'Shiny Shamrock'=>[0x5fa778,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Shocking pink'=>[0xfc0fc0,['url'=>'http://en.wikipedia.org/wiki/Shades_of_pink#Shocking_pink']],
                'Ultra pink'=>[0xff6fff,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Ultra_pink']],
                'Silver'=>[0xc0c0c0,['url'=>'http://en.wikipedia.org/wiki/Silver_(color)']],
                'Silver chalice'=>[0xacacac,['url'=>'http://en.wikipedia.org/wiki/Silver_(color)#Silver_chalice']],
                'Silver Lake blue'=>[0x5d89ba,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Silver_Lake_blue']],
                'Silver pink'=>[0xc4aead,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Silver_pink']],
                'Silver sand'=>[0xbfc1c2,['url'=>'http://en.wikipedia.org/wiki/Silver_(color)#Silver_sand']],
                'Sinopia'=>[0xcb410b,['url'=>'http://en.wikipedia.org/wiki/Sinopia']],
                'Sizzling Red'=>[0xff3855,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Sizzling Sunrise'=>[0xffdb00,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Extreme_Twistables_colors']],
                'Skobeloff'=>[0x007474,['url'=>'http://en.wikipedia.org/wiki/Spring_green_(color)#Skobeloff']],
                'Sky blue'=>[0x87ceeb,['url'=>'http://en.wikipedia.org/wiki/Sky_blue']],
                'Sky magenta'=>[0xcf71af,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Sky_magenta']],
                'Slate blue'=>[0x6a5acd,['url'=>'http://en.wikipedia.org/wiki/X11_color_names#Color_names']],
                'Slate gray'=>[0x708090,['url'=>'http://en.wikipedia.org/wiki/Slate_gray']],
                'Slimy Green'=>[0x299617,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Smashed Pumpkin'=>[0xff6d3a,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Smitten'=>[0xc84186,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Smitten']],
                'Smoke'=>[0x738276,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Smokey Topaz'=>[0x832a0d,['url'=>'http://en.wikipedia.org/wiki/Shades_of_brown#Smokey_topaz']],
                'Smoky black'=>[0x100c08,['url'=>'http://en.wikipedia.org/wiki/Smoky_black']],
                'Smoky Topaz'=>[0x933d41,['url'=>'http://en.wikipedia.org/wiki/Topaz']],
                'Snow'=>[0xfffafa,['url'=>'http://en.wikipedia.org/wiki/Shades_of_white#Snow']],
                'Soap'=>[0xcec8ef,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Soap']],
                'Solid pink'=>[0x893843,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Solid_pink']],
                'Sonic silver'=>[0x757575,['url'=>'http://en.wikipedia.org/wiki/Silver_(color)#Sonic_silver']],
                'Spartan Crimson'=>[0x9e1316,['url'=>'http://en.wikipedia.org/wiki/Crimson_(color)#Spartan_Crimson']],
                'Space cadet'=>[0x1d2951,['url'=>'http://en.wikipedia.org/wiki/Cadet_grey#Space_cadet']],
                'Spanish bistre'=>[0x807532,['url'=>'http://en.wikipedia.org/wiki/Bistre#Spanish_bistre']],
                'Spanish blue'=>[0x0070b8,['url'=>'http://en.wikipedia.org/wiki/Variations_of_blue#Spanish_blue']],
                'Spanish carmine'=>[0xd10047,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Spanish_carmine']],
                'Spanish crimson'=>[0xe51a4c,['url'=>'http://en.wikipedia.org/wiki/Crimson#Spanish_crimson']],
                'Spanish gray'=>[0x989898,['url'=>'http://en.wikipedia.org/wiki/Variations_of_gray#Spanish_gray']],
                'Spanish green'=>[0x009150,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Spanish_green']],
                'Spanish orange'=>[0xe86100,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Spanish_orange']],
                'Spanish pink'=>[0xf7bfbe,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Spanish_pink']],
                'Spanish red'=>[0xe60026,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red#Spanish_red']],
                'Spanish violet'=>[0x4c2882,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#Spanish_violet']],
                'Spanish viridian'=>[0x007f5c,['url'=>'http://en.wikipedia.org/wiki/Viridian#Spanish_viridian']],
                'Spicy mix'=>[0x8b5f4d,['url'=>'http://en.wikipedia.org/wiki/Spice_mix']],
                'Spiro Disco Ball'=>[0x0fc0fc,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors']],
                'Spring bud'=>[0xa7fc00,['url'=>'http://en.wikipedia.org/wiki/Spring_bud']],
                'Spring Frost'=>[0x87ff2a,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Extreme_Twistables_colors']],
                'Star command blue'=>[0x007bb8,['url'=>'http://en.wikipedia.org/wiki/Cadet_grey#Star_command_blue']],
                'Steel blue'=>[0x4682b4,['url'=>'http://en.wikipedia.org/wiki/Steel_blue']],
                'Steel pink'=>[0xcc33cc,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Steel_pink']],
                'Steel Teal'=>[0x5f8a8b,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Stormcloud'=>[0x4f666a,['url'=>'http://en.wikipedia.org/wiki/Stormcloud']],
                'Straw'=>[0xe4d96f,['url'=>'http://en.wikipedia.org/wiki/Straw_(colour)']],
                'Strawberry'=>[0xfc5a8d,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Sugar Plum'=>[0x914e75,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Sunburnt Cyclops'=>[0xff404c,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Sunglow'=>[0xffcc33,['url'=>'http://en.wikipedia.org/wiki/Sunset_(color)#Sunglow']],
                'Sunny'=>[0xf2f27a,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Pearl_Brite']],
                'Sunray'=>[0xe3ab57,['url'=>'http://en.wikipedia.org/wiki/Sunset_(color)#Sunray']],
                'Sunset orange'=>[0xfd5e53,['url'=>'http://en.wikipedia.org/wiki/Sunset_(color)#Sunset_orange']],
                'Super pink'=>[0xcf6ba9,['url'=>'http://en.wikipedia.org/wiki/Variations_of_pink#Super_pink']],
                'Sweet Brown'=>[0xa83731,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Tangelo'=>[0xf94d00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Tangelo']],
                'Tangerine'=>[0xf28500,['url'=>'http://en.wikipedia.org/wiki/Tangerine_(color)']],
                'USC Gold'=>[0xffcc00,['url'=>'http://en.wikipedia.org/wiki/University_of_Southern_California']],
                'Tart Orange'=>[0xfb4d46,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Heads_.27n_Tails']],
                'Taupe gray'=>[0x8b8589,['url'=>'http://en.wikipedia.org/wiki/Taupe#Taupe_gray']],
                'Tea green'=>[0xd0f0c0,['url'=>'http://en.wikipedia.org/wiki/Shades_of_green#Tea_green']],
                'Teal'=>[0x008080,['url'=>'http://en.wikipedia.org/wiki/Teal_(color)#Teal']],
                'Teal blue'=>[0x367588,['url'=>'http://en.wikipedia.org/wiki/Teal_(color)#Teal_blue']],
                'Teal deer'=>[0x99e6b3,['url'=>'http://en.wikipedia.org/wiki/Teal']],
                'Teal green'=>[0x00827f,['url'=>'http://en.wikipedia.org/wiki/Teal_(color)#Teal_green']],
                'Telemagenta'=>[0xcf3476,['url'=>'http://en.wikipedia.org/wiki/Variations_of_magenta#Telemagenta']],
                'Tenné'=>[0xcd5700,['url'=>'http://en.wikipedia.org/wiki/Tawny_(color)']],
                'Terra cotta'=>[0xe2725b,['url'=>'http://en.wikipedia.org/wiki/Terracotta#Color']],
                'Thistle'=>[0xd8bfd8,['url'=>'http://en.wikipedia.org/wiki/Thistle_(color)']],
                'Tickle Me Pink'=>[0xfc89ac,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Tickle_me_pink']],
                'Tiffany Blue'=>[0x0abab5,['url'=>'http://en.wikipedia.org/wiki/Tiffany_Blue#Tiffany_Blue']],
                'Tiger\'s eye'=>[0xe08d3c,['url'=>'http://en.wikipedia.org/wiki/Tiger%27s_eye']],
                'Timberwolf'=>[0xdbd7d2,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Titanium yellow'=>[0xeee600,['url'=>'http://en.wikipedia.org/wiki/Titanium_yellow']],
                'Tomato'=>[0xff6347,['url'=>'http://en.wikipedia.org/wiki/Tomato_(color)']],
                'Toolbox'=>[0x746cc0,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Standard_colors']],
                'Topaz'=>[0xffc87c,['url'=>'http://en.wikipedia.org/wiki/Topaz']],
                'Tropical rain forest'=>[0x00755e,['url'=>'http://en.wikipedia.org/wiki/Jungle_green#Tropical_rain_forest']],
                'Tropical violet'=>[0xcda4de,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors']],
                'True Blue'=>[0x0073cf,['url'=>'http://en.wikipedia.org/wiki/True_Blue_(color)']],
                'Tufts Blue'=>[0x417dc1,['url'=>'http://en.wikipedia.org/wiki/Tufts_Blue']],
                'Tulip'=>[0xff878d,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Magic_Scent']],
                'Tumbleweed'=>[0xdeaa88,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors']],
                'Turkish rose'=>[0xb57281,['url'=>'http://en.wikipedia.org/wiki/Rose_(color)#Turkish_Rose']],
                'Turquoise'=>[0x40e0d0,['url'=>'http://en.wikipedia.org/wiki/Turquoise_(color)']],
                'Turquoise blue'=>[0x00ffef,['url'=>'http://en.wikipedia.org/wiki/Turquoise_(color)#Turquoise_Blue']],
                'Turquoise green'=>[0xa0d6b4,['url'=>'http://en.wikipedia.org/wiki/Turquoise_(color)#Turquoise_Green']],
                'Turquoise Surf'=>[0x00c5cd,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_marker_colors#Standard_colors']],
                'Tuscan red'=>[0x7c4848,['url'=>'http://en.wikipedia.org/wiki/Tuscan_red']],
                'Tuscany'=>[0xc09999,['url'=>'http://en.wikipedia.org/wiki/Tuscan_red#Tuscany']],
                'Twilight lavender'=>[0x8a496b,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#Twilight_lavender']],
                'UA red'=>[0xd9004c,['url'=>'http://en.wikipedia.org/wiki/University_of_Arizona#School_colors']],
                'Ube'=>[0x8878c3,['url'=>'http://en.wikipedia.org/wiki/Lavender_(color)#.C3.9Abe']],
                'UCLA Blue'=>[0x536895,['url'=>'http://en.wikipedia.org/wiki/UCLA_Blue']],
                'UCLA Gold'=>[0xffb300,['url'=>'http://en.wikipedia.org/wiki/UCLA']],
                'UFO Green'=>[0x3cd070,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_colored_pencil_colors#Standard_colors']],
                'Ultramarine blue'=>[0x4166f5,['url'=>'http://en.wikipedia.org/wiki/Ultramarine#Ultramarine_blue']],
                'Wild watermelon'=>[0xfc6c85,['url'=>'http://en.wikipedia.org/wiki/Carmine_(color)#Ultra_red']],
                'Umber'=>[0x635147,['url'=>'http://en.wikipedia.org/wiki/Umber#Umber']],
                'Unbleached silk'=>[0xffddca,['url'=>'http://en.wikipedia.org/wiki/Beige#Unbleached_silk']],
                'United Nations blue'=>[0x5b92e5,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#United_Nations_blue']],
                'University of California Gold'=>[0xb78727,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#University_of_California_Gold']],
                'UP Maroon'=>[0x7b1113,['url'=>'http://en.wikipedia.org/wiki/Maroon_(color)#UP_maroon']],
                'Upsdell red'=>[0xae2029,['url'=>'http://en.wikipedia.org/wiki/Upsdell_red']],
                'Urobilin'=>[0xe1ad21,['url'=>'http://en.wikipedia.org/wiki/Urobilin']],
                'USAFA blue'=>[0x004f98,['url'=>'http://en.wikipedia.org/wiki/Air_Force_blue#USAFA_blue']],
                'University of Tennessee Orange'=>[0xf77f00,['url'=>'http://en.wikipedia.org/wiki/University_of_Tennessee']],
                'Utah Crimson'=>[0xd3003f,['url'=>'http://en.wikipedia.org/wiki/Crimson#Utah_crimson']],
                'Vanilla ice'=>[0xf38fa9,['url'=>'http://en.wikipedia.org/wiki/Vanilla_(color)#Vanilla_ice']],
                'Vegas gold'=>[0xc5b358,['url'=>'http://en.wikipedia.org/wiki/Gold_(color)#Vegas_gold']],
                'Venetian red'=>[0xc80815,['url'=>'http://en.wikipedia.org/wiki/Venetian_red']],
                'Verdigris'=>[0x43b3ae,['url'=>'http://en.wikipedia.org/wiki/Verdigris']],
                'Very light azure'=>[0x74bbfb,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)']],
                'Very light blue'=>[0x6666ff,['url'=>'http://en.wikipedia.org/wiki/Light_blue']],
                'Very light malachite green'=>[0x64e986,['url'=>'http://en.wikipedia.org/wiki/Malachite_green']],
                'Very light tangelo'=>[0xffb077,['url'=>'http://en.wikipedia.org/wiki/Tangelo']],
                'Very pale orange'=>[0xffdfbf,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange']],
                'Very pale yellow'=>[0xffffbf,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow']],
                'Violet (color wheel)'=>[0x7f00ff,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#Color_wheel_violet']],
                'Violet (RYB)'=>[0x8601af,['url'=>'http://en.wikipedia.org/wiki/RYB_color_model']],
                'Violet-blue'=>[0x324ab2,['url'=>'http://en.wikipedia.org/wiki/Indigo#Violet-blue']],
                'Violet-red'=>[0xf75394,['url'=>'http://en.wikipedia.org/wiki/Red-violet#Violet-red']],
                'Viridian green'=>[0x009698,['url'=>'http://en.wikipedia.org/wiki/Viridian#Viridian_green']],
                'Vista blue'=>[0x7c9ed9,['url'=>'http://en.wikipedia.org/wiki/Azure_(color)#Vista_blue']],
                'Vivid amber'=>[0xcc9900,['url'=>'http://en.wikipedia.org/wiki/Amber_(color)']],
                'Vivid auburn'=>[0x922724,['url'=>'http://en.wikipedia.org/wiki/Auburn_hair#Vivid_Auburn']],
                'Vivid burgundy'=>[0x9f1d35,['url'=>'http://en.wikipedia.org/wiki/Burgundy_(color)#Vivid_burgundy']],
                'Vivid cerise'=>[0xda1d81,['url'=>'http://en.wikipedia.org/wiki/Cerise_(color)#Vivid_cerise']],
                'Vivid cerulean'=>[0x00aaee,['url'=>'http://en.wikipedia.org/wiki/Cerulean']],
                'Vivid crimson'=>[0xcc0033,['url'=>'http://en.wikipedia.org/wiki/Crimson']],
                'Vivid gamboge'=>[0xff9900,['url'=>'http://en.wikipedia.org/wiki/Gamboge']],
                'Vivid lime green'=>[0xa6d608,['url'=>'http://en.wikipedia.org/wiki/Lime_(color)']],
                'Vivid malachite'=>0x00cc33,
                'Vivid mulberry'=>[0xb80ce3,['url'=>'http://en.wikipedia.org/wiki/Mulberry_(color)']],
                'Vivid orange'=>[0xff5f00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange']],
                'Vivid orange peel'=>[0xffa000,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange']],
                'Vivid orchid'=>[0xcc00ff,['url'=>'http://en.wikipedia.org/wiki/Orchid_(color)#Vivid_orchid']],
                'Vivid raspberry'=>[0xff006c,['url'=>'http://en.wikipedia.org/wiki/Raspberry_(color)']],
                'Vivid red'=>[0xf70d1a,['url'=>'http://en.wikipedia.org/wiki/Shades_of_red']],
                'Vivid red-tangelo'=>[0xdf6124,['url'=>'http://en.wikipedia.org/wiki/Tangelo']],
                'Vivid sky blue'=>[0x00ccff,['url'=>'http://en.wikipedia.org/wiki/Sky_blue#Vivid_sky_blue']],
                'Vivid tangelo'=>[0xf07427,['url'=>'http://en.wikipedia.org/wiki/Tangelo']],
                'Vivid tangerine'=>[0xffa089,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Vivid vermilion'=>[0xe56024,['url'=>'http://en.wikipedia.org/wiki/Vermilion']],
                'Vivid violet'=>[0x9f00ff,['url'=>'http://en.wikipedia.org/wiki/Shades_of_violet#Vivid_violet']],
                'Vivid yellow'=>[0xffe302,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow']],
                'Volt'=>[0xceff00,['url'=>'http://en.wikipedia.org/wiki/Lime_(color)#Volt']],
                'Wageningen Green'=>[0x34b233,['url'=>'http://en.wikipedia.org/wiki/Wageningen_University_and_Research_Centre']],
                'Waterspout'=>[0xa4f4f9,['url'=>'http://en.wikipedia.org/wiki/Waterspout']],
                'Weldon Blue'=>0x7c98ab,
                'Wenge'=>[0x645452,['url'=>'http://en.wikipedia.org/wiki/Wenge_(colour)']],
                'Wheat'=>[0xf5deb3,['url'=>'http://en.wikipedia.org/wiki/Wheat_(color)']],
                'White'=>[0xffffff,['url'=>'http://en.wikipedia.org/wiki/White']],
                'Wild blue yonder'=>[0xa2add0,['url'=>'http://en.wikipedia.org/wiki/Air_Force_blue#Wild_blue_yonder']],
                'Wild orchid'=>[0xd470a2,['url'=>'http://en.wikipedia.org/wiki/Orchid_(color)#Wild_orchid']],
                'Wild Strawberry'=>[0xff43a4,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Willpower orange'=>[0xfd5800,['url'=>'http://en.wikipedia.org/wiki/Shades_of_orange#Willpower_orange']],
                'Windsor tan'=>[0xa75502,['url'=>'http://en.wikipedia.org/wiki/Tan_(color)#Windsor_tan']],
                'Winter Sky'=>[0xff007c,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Extreme_Twistables_colors']],
                'Winter Wizard'=>[0xa0e6ff,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silly_Scents']],
                'Wintergreen Dream'=>[0x56887d,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Silver_Swirls']],
                'Wisteria'=>[0xc9a0dc,['url'=>'http://en.wikipedia.org/wiki/Wisteria_(color)']],
                'Yankees blue'=>[0x1c2841,['url'=>'http://en.wikipedia.org/wiki/Shades_of_blue']],
                'Yellow'=>[0xffff00,['url'=>'http://en.wikipedia.org/wiki/Yellow']],
                'Yellow (Crayola)'=>[0xfce883,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Yellow_.28Crayola.29']],
                'Yellow (Munsell)'=>[0xefcc00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Yellow_.28Munsell.29']],
                'Yellow (Pantone)'=>[0xfedf00,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow#Yellow_.28Pantone.29']],
                'Yellow (RYB)'=>[0xfefe33,['url'=>'http://en.wikipedia.org/wiki/RYB_color_model']],
                'Yellow-green'=>[0x9acd32,['url'=>'http://en.wikipedia.org/wiki/Chartreuse_(color)#Yellow-green']],
                'Yellow Orange'=>[0xffae42,['url'=>'http://en.wikipedia.org/wiki/List_of_Crayola_crayon_colors#Standard_colors']],
                'Yellow rose'=>[0xfff000,['url'=>'http://en.wikipedia.org/wiki/Shades_of_yellow']],
                'Zinnwaldite brown'=>[0x2c1608,['url'=>'http://en.wikipedia.org/wiki/Zinnwaldite']],
                'Zomp'=>[0x39a78e,['url'=>'http://en.wikipedia.org/wiki/Spring_green#Zomp']]];
    }
}

