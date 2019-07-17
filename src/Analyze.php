<?php namespace ColorTools;

class Analyze
{
    const ADAPTIVE_PRECISION   =-1;
    const DEFAULT_MIN_COVERAGE = 4;

    public $palette = null;
    private $luma = null;
    private $histogram = null;
    public $precision = null;
    private $colors = null;
    private $time = null;
    public $analysisOptions = null;
    private $width = null;
    private $height = null;
    private $sampledWidth = null;
    private $sampledHeight = null;
    private $sampledPixels = [];
    private $similarColorPixels = [];

    public function __construct($analysisOptions=array())
    {
        if(!isset($analysisOptions['palette'])) {
            $analysisOptions['palette'] = Palette::PALETTE_COLOR_TOOLS;
        }

        if(!isset($analysisOptions['precision'])) {
            $analysisOptions['precision'] = Analyze::ADAPTIVE_PRECISION;
        }

        if(!isset($analysisOptions['comparisonType'])) {
            $analysisOptions['comparisonType'] = Color::COMPARE_GREAT;
        }

        if(!isset($analysisOptions['minCoverage'])) {
            $analysisOptions['minCoverage'] = Analyze::DEFAULT_MIN_COVERAGE;
        }

        if(!isset($analysisOptions['useColorsMatchTable'])) {
            $analysisOptions['useColorsMatchTable'] = true;
        }

        $this->analysisOptions = $analysisOptions;

        $this->palette = new Palette($analysisOptions['palette']);

        return $this;
    }

    public static function getAnalysis(Image $image, $analysisOptions = array())
    {
        $analysis = new Analyze($analysisOptions);
        return $analysis->doAnalysis($image);
    }

    public function __get($param)
    {
        $param = strtolower($param);

        if ($param == 'histogram') {
            return $this->getHistogram();
        }

        if ($param == 'colors') {
            return $this->getColors();
        }

        if ($param == 'luma') {
            return $this->getLuma();
        }

        if ($param == 'time') {
            return $this->time;
        }

        if ($param == 'sampledpixels') {
            return $this->sampledPixels;
        }

        if ($param == 'sampledpixelscount') {
            return count($this->sampledPixels);
        }

        if ($param == 'similarcolorpixels') {
            return $this->getSimilarColorPixels();
        }

        return false;
    }

    public function doAnalysis(Image $image)
    {
        $timeStart = microtime(true);

        $pixels = $image->width * $image->height;
        $this->width = $image->width;
        $this->height = $image->height;

        $precision = $this->analysisOptions['precision'];

        if ($precision == Analyze::ADAPTIVE_PRECISION) {
            $precision = intval(ceil(sqrt($pixels) / 300));
        }
        $this->precision = $precision;

        $this->sampledWidth = floor($image->width / $precision);
        $this->sampledHeight = floor($image->height / $precision);

        for ($y = 0; $y <= ($image->height - $precision); $y += $precision) {
            for ($x = 0; $x <= ($image->width - $precision); $x += $precision) {
                $this->sampledPixels[]=Color::create($image->getImageObject(), $x, $y);
            }
        }

        $this->time['pixelSampling'] = microtime(true) - $timeStart;
        return $this;
    }

    public function getHistogram()
    {
        if(is_null($this->histogram)) {
            $timeStart = microtime(true);

            $this->histogram = new Histogram($this);
            $this->time['histogram'] = microtime(true) - $timeStart;
        }

        return $this->histogram;
    }

    public function getColors()
    {
        if(is_null($this->colors)) {
            $timeStart = microtime(true);

            $paletteQuantities = [];
            $matchedColors = [];

            foreach ($this->sampledPixels as $color) {
                if($this->analysisOptions['useColorsMatchTable']) {
                    /*
                     * Building and using a lookup table by reducing the maximum numbers of colors from over
                     * 16 million to about 4096. Will make things much faster but a little bit less precise.
                     * If you have the time, and enough CPU, don't use it.
                     */
                    $safe = $color->safe;
                    if(!isset($matchedColors[$safe])) {
                        $matchedColors[$safe] = $color->findSimilar($this->analysisOptions['comparisonType'], $this->palette->collection, true);
                    }
                    $color = $matchedColors[$safe];
                } else {
                    $color = $color->findSimilar($this->analysisOptions['comparisonType'], $this->palette->collection, true);
                }

                $this->similarColorPixels[] = $color;
                $color = $color->hex;
                if (!isset($paletteQuantities[$color])) {
                    $paletteQuantities[$color] = pow($this->precision, 2);
                } else {
                    $paletteQuantities[$color] += pow($this->precision, 2);
                }
            }

            asort($paletteQuantities, SORT_NUMERIC);
            $paletteQuantities = array_reverse($paletteQuantities);

            foreach ($paletteQuantities as $hex => $value) {
                $paletteQuantities[$hex] = round($value / $this->width / $this->height * 100, 2);
                //convert the coverage to % out of total number of pixels

                if ($this->analysisOptions['minCoverage'] and $paletteQuantities[$hex] < $this->analysisOptions['minCoverage']) {
                    unset($paletteQuantities[$hex]);
                }
            }

            $this->colors = $paletteQuantities;
            $this->time['gettingColors'] = microtime(true) - $timeStart;
        }

        return $this->colors;
    }

    public function getLuma()
    {
        if(is_null($this->luma)) {
            $timeStart = microtime(true);

            $luma = 0;

            foreach ($this->sampledPixels as $color) {
                $luma += $color->getLuma();
            }

            $luma /= $this->sampledWidth * $this->sampledHeight;

            $this->luma = $luma;
            $this->time['gettingLuma'] = microtime(true) - $timeStart;
        }

        return $this->luma;
    }

    public function getSampledPixels()
    {
        return $this->sampledPixels;
    }

    public function getSampledPixelsImage()
    {
        return Image::createFromColors($this->sampledPixels, $this->sampledWidth, $this->sampledHeight);
    }

    public function getSimilarColorPixels()
    {
        if(empty($this->similarColorPixels)) {
            $this->getColors();
        }
        return $this->similarColorPixels;
    }

    public function getSimilarColorImage()
    {
        return Image::createFromColors($this->getSimilarColorPixels(), $this->sampledWidth, $this->sampledHeight);
    }
}