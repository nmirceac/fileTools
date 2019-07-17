<?php namespace ColorTools;

class Histogram
{
    private $histogramData;

    public function __construct($histogramData)
    {

        try {
            $image = Image::create($histogramData);
        } catch (Exception $e) {}


        if(isset($image) and get_class($image) == 'ColorTools\Image') {
            $this->getHistogramDataFromSampledPixels($image->getAnalysis()->sampledPixels);
            return $this;
        }

        if(gettype($histogramData)=='string') {
            $array = json_decode($histogramData, true);
            if(!is_null($array)) {
                $histogramData = $array;
            } else {
                throw new Exception('This histogram string must be a valid JSON');
            }
        }

        if(gettype($histogramData)=='array')
        {
            if(isset($histogramData['r'])
                and isset($histogramData['a'])
                and isset($histogramData['r'])
                and isset($histogramData['g'])
                and isset($histogramData['b'])
                and isset($histogramData['l'])) {
                $this->histogramData = $histogramData;
            } else if(count($histogramData)>255
                and gettype($histogramData[0]) == 'object' and get_class($histogramData[0]) == 'ColorTools\Color'
                and gettype($histogramData[255]) == 'object' and get_class($histogramData[255]) == 'ColorTools\Color') {
                $this->getHistogramDataFromSampledPixels($histogramData);
            } else {
                throw new Exception('This histogram/pixels array is missing some parts');
            }
        } else if(gettype($histogramData)=='object') {
            $class = get_class($histogramData);
            switch ($class) {
                case 'ColorTools\Analyze' :
                    $this->getHistogramDataFromSampledPixels($histogramData->sampledPixels);
                    break;

                default :
                    break;
            }
        } else {
            throw new Exception('I got nothing about this histogram data... I give up, you win!');
        }

        return $this;
    }

    static function create($histogramData) {
        return new Histogram($histogramData);
    }

    public function __get($param)
    {
        $param = strtolower($param);

        if ($param == 'a') {
            return $this->histogramData['a'];
        }

        if ($param == 'r') {
            return $this->histogramData['r'];
        }

        if ($param == 'g') {
            return $this->histogramData['g'];
        }

        if ($param == 'b') {
            return $this->histogramData['b'];
        }

        if ($param == 'l') {
            return $this->histogramData['l'];
        }

        return false;
    }

    private function getHistogramDataFromSampledPixels($sampledPixels)
    {
        $histogram['a'] = array_fill(0, 256, 0);
        $histogram['r'] = array_fill(0, 256, 0);
        $histogram['g'] = array_fill(0, 256, 0);
        $histogram['b'] = array_fill(0, 256, 0);
        $histogram['l'] = array_fill(0, 256, 0);


        foreach ($sampledPixels as $color) {
            $average = round(($color -> r + $color -> g * 2 + $color -> b) / 4);
            $histogram['a'][$average]++;
            $histogram['r'][$color -> r]++;
            $histogram['g'][$color -> g]++;
            $histogram['b'][$color -> b]++;
            $histogram['l'][round($color->getLuma()*255)]++;
        }

        /*
         * Normalizing values - giving it smoother curves (which you would get naturally by sampling more pixels)
         */
        foreach ($histogram as $type => $h) {
            $maxValueMultiplier = 4;
            $average = array_sum($h) / 256;

            foreach ($h as $channel => $value) {

                $h[$channel] = min($value, $average*$maxValueMultiplier);
                if($channel>0 and $channel<255) {
                    $h[$channel] = ($h[$channel-1] + $h[$channel]*2 +  min($h[$channel+1], $average*$maxValueMultiplier))/4;
                }
            }

            $histogram[$type] = $h;
        }

        foreach ($histogram as $type => $h) {
            //smoothing edges
            $max = max(array_slice($h, 1, -1));
            $scale = 1 / $max;

            foreach ($h as $channel => $value) {
                $h[$channel] = min($scale * $value, 1);
            }

            $histogram[$type] = $h;
        }

        $this->histogramData = $histogram;
    }

    public function toArray()
    {
        return $this->histogramData;
    }

    public function serialize()
    {
        return json_encode($this->histogramData);
    }

    public function getSrc($histogram='c', $options=[])
    {
        return 'data:image/svg+xml;base64, '.base64_encode($this->buildHistogram($histogram, $options));
    }

    public function buildHistogram($histogram, $options=[])
    {
        $histogram = strtolower($histogram);
        if($histogram=='red') {
            $histogram='r';
        }

        if($histogram=='green') {
            $histogram='g';
        }

        if($histogram=='blue') {
            $histogram='b';
        }

        if($histogram=='average' or $histogram=='gray') {
            $histogram='g';
        }

        if($histogram=='composite' or $histogram=='colors') {
            $histogram='c';
        }

        if($histogram!='c' and !isset($this->histogramData[$histogram])) {
            throw new Exception('Cannot find details about this histogram type');
        }

        switch($histogram) {
            case 'r' :
                $color='#f00';
                break;

            case 'g' :
                $color='#0f0';
                break;

            case 'b' :
                $color='#00f';
                break;

            case 'a' :
                $color='#777';
                break;

            default :
                $color='#000';
                break;
        }

        if($histogram=='c') {
            $maxHeight = 100;
            $barWidth = 1;
            $content ='<?xml version="1.0" standalone="no"?>';
            $content.='<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">';
            $content.='<svg width="'.($barWidth*256).'" height="'.$maxHeight.'" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">';
            $content.='<desc>Histogram</desc>';


            foreach(['g', 'r', 'b'] as $channel) {
                $histogramArray = $this->histogramData[$channel];
                $max=max($histogramArray);
                $scale=$maxHeight / $max;
                foreach($histogramArray as $r => $val) {
                    if($channel=='r') {
                        $color='#f00';
                    } else if($channel=='g') {
                        $color='#0f0';
                    } else if($channel=='b'){
                        $color='#00f';
                    } else {
                        $color='#777';
                    }
                    $content.='<rect style="fill:'.$color.';fill-opacity:0.5;" x="'.($r * $barWidth).'" y="'.($maxHeight-$scale * $val).'" width="'.($barWidth).'" height="'.($scale * $val).'"/>';
                }
            }

            $content.='</svg>';
        } else {
            $histogramArray = $this->histogramData[$histogram];
            $maxHeight = 100;
            $barWidth = 1;
            $max=max($histogramArray);
            $scale=$maxHeight / $max;
            $content ='<?xml version="1.0" standalone="no"?>';
            $content.='<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">';
            $content.='<svg width="'.($barWidth*256).'" height="'.$maxHeight.'" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">';
            $content.='<desc>Histogram</desc>';
            foreach($histogramArray as $r => $val) {
                $content.='<rect style="fill:'.$color.';fill-opacity:0.8;" x="'.($r * $barWidth).'" y="'.($maxHeight-$scale * $val).'" width="'.($barWidth).'" height="'.($scale * $val).'"/>';
            }
            $content.='</svg>';
        }


        return $content;
    }

}

