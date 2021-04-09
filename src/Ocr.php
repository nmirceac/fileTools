<?php namespace FileTools;

/**
 * Class Ocr
 * @package FileTools
 */
class Ocr
{
    // OSD stands for Orientation and Script Detection

    const PSM_OSD_ONLY = 0;
    const PSM_AUTOMATIC_PAGE_WITH_OSD = 1;
    const PSM_AUTOMATIC_PAGE_WITH_NO_OSD = 3;
    const PSM_SINGLE_COLUMN_OF_TEXT = 4;
    const PSM_SINGLE_UNIFORM_OF_VERTICALLY_ALIGNED_TEXT = 5;
    const PSM_SINGLE_UNIFORM_BLOCK_OF_TEXT = 6;
    const PSM_SINGLE_TEXT_LINE = 7;
    const PSM_SINGLE_WORD = 8;
    const PSM_SINGLE_WORD_IN_A_CIRCLE = 9;
    const PSM_SINGLE_CHARACTER = 10;
    const PSM_SPARSE_TEXT = 11;
    const PSM_SPARSE_TEXT_WITH_OSD = 12;
    const PSM_RAW_LINE = 13;


    /**
     * The binary command format
     */
    const COMMAND = '%BINARY% "%IMAGE_PATH%" 2>/dev/null';

    /**
     * Gets the command to be executed
     * @param string $imagePath
     * @return string
     * @throws \Exception
     */
    private static function getCommand(string $imagePath)
    {
        return str_replace(
            [
                '%BINARY%',
                '%IMAGE_PATH%',
            ],
            [
                self::getBinary(),
                $imagePath
            ],
            self::COMMAND
        );
    }

    public static function getTesseractInstance()
    {
        /*
         * Just make sure you have tesseract-ocr installed
         * As per https://launchpad.net/~alex-p/+archive/ubuntu/tesseract-ocr-devel
         * sudo add-apt-repository ppa:alex-p/tesseract-ocr-devel
         * sudo apt-get update
         * sudo apt-get install tesseract-ocr tesseract-ocr-eng
         * For other languages just check the support and just install tesseract-ocr-XXX where XXX is your lang
         */


        if(!class_exists(\thiagoalessio\TesseractOCR\TesseractOCR::class)) {
            throw new \Exception('Missing tesseract/ocr class - try composer require thiagoalessio/tesseract_ocr and make sure you have tesseract-ocr - https://launchpad.net/~alex-p/+archive/ubuntu/tesseract-ocr-devel');
        }

        return (new \thiagoalessio\TesseractOCR\TesseractOCR());
    }

    public static function scanFromImagePath(string $imagePath)
    {
        if(!file_exists($imagePath)) {
            throw new \Exception('File not accessible at '.$imagePath.' for OCR scanning');
        }

        $tesseract = self::getTesseractInstance();
        $tesseract->image($imagePath);
        return $tesseract;
    }

    public static function scanFromImageBlob(string $image)
    {
        $tesseract = self::getTesseractInstance();
        $tesseract->imageData($image, 0);
        return $tesseract;
    }

    public static function getHocrPreset(\thiagoalessio\TesseractOCR\TesseractOCR $tesseract, int $dpi=0, int $psm=0)
    {
        $tesseract->lang('eng', 'afr');
        if($dpi) {
            $tesseract->dpi($dpi);
        }
        if($psm) {
            $tesseract->psm($psm);
        }

        $hocrString = $tesseract->hocr()->run();

        // usual problems
        $usualMismatches = ['|'];
        $replacements = ['I'];

        $hocrString = str_replace($usualMismatches, $replacements, $hocrString);

        return $hocrString;
    }

    public static function getHocrPresetFromString(string $image, int $dpi=0, int $psm=0)
    {
        $cacheTime = (int) config('filetools.ocrHocrDataCaching');
        if($cacheTime) {
            $cacheKey = 'hocrBlockPresent-'.__FUNCTION__.'-'.md5($image).'-'.$dpi.'-'.$psm;
            $data = \Cache::get($cacheKey);
        } else {
            $data = null;
        }

        if(is_null($data)) {
            $data = self::getHocrPreset(self::scanFromImageBlob($image), $dpi, $psm);
            if($cacheTime) {
                \Cache::put($cacheKey, $data, $cacheTime);
            }
        }

        return $data;
    }

    public static function getTextVersionFromHocrString(string $hocrString)
    {
        $hocrString = str_replace([PHP_EOL, '</p>', '<span class=\'ocr_line\''], ['', PHP_EOL, PHP_EOL.'<span class=\'ocr_line\''], $hocrString);
        $hocrString = explode(PHP_EOL, trim(strip_tags($hocrString)));
        foreach($hocrString as $lineNo=>$line) {
            $hocrString[$lineNo] = trim(preg_replace('/\s+/', ' ', $line));
        }
        return implode(PHP_EOL, $hocrString);
    }

    public static function parseHocr(string $hocrString)
    {
        $xml = simplexml_load_string($hocrString);

        $data = self::parseHocrPart($xml->body);

        $data = [
            'pages'=>$data['body']['children'],
            'text'=>self::getTextVersionFromHocrString($hocrString),
        ];

        return $data;
    }

    public static function parseHocrPart(\SimpleXMLElement $hocrPart, array $referenceBbox = [])
    {
        $data = [];

        $name = $hocrPart->getName();
        $attributes = $hocrPart->attributes();

        if($hocrPart->attributes()->class) {
            $name = (string) $hocrPart->attributes()->class;
        }

        $data[$name] = [];

        $title = (string) $hocrPart->attributes()->title;

        if(in_array($name, ['ocrx_word', 'ocr_line', 'ocr_par', 'ocr_carea', 'ocr_page'])) {
            $bbox = null;
            if(strpos($title, 'bbox')!==false) {
                $bboxPosition = strpos($title, 'bbox') + 5;
                $bboxPositionEnd = strpos($title, ';', $bboxPosition+1);
                if($bboxPositionEnd) {
                    $bbox = substr($title, $bboxPosition, $bboxPositionEnd - $bboxPosition);
                } else {
                    $bbox = substr($title, $bboxPosition);
                }

            }

            $data[$name]['id'] = (string) $hocrPart->attributes()->id;
            $data[$name]['bbox'] = explode(' ', $bbox);

            if(!empty($referenceBbox)) {
                $width = $referenceBbox[2] - $referenceBbox[0];
                $height = $referenceBbox[3] - $referenceBbox[1];
                $data[$name]['position'][0] = $data[$name]['bbox'][0] / $width;
                $data[$name]['position'][1] = $data[$name]['bbox'][1] / $height;
                $data[$name]['position'][2] = ($data[$name]['bbox'][2] - $data[$name]['bbox'][0]) / $width;
                $data[$name]['position'][3] = ($data[$name]['bbox'][3] - $data[$name]['bbox'][1]) / $height;
            }

            //$data[$name]['title'] = $title;
        }

        if($name=='ocrx_word') {
            $data[$name]['content'] = (string) $hocrPart;
        }

        if($name=='ocr_par') {
            $data[$name]['lang'] = (string) $hocrPart->attributes()->lang;
        }

        if($name=='ocr_page') {
            $referenceBbox = $data[$name]['bbox'];
        }

        $children = [];
        foreach($hocrPart->children() as $child) {
            $children[] = self::parseHocrPart($child, $referenceBbox);
        }

        $data[$name]['children'] = $children;

        return $data;
    }

    public static function findWordInHocrData(array $hocrData, string $searchString, array $excludeIds = [])
    {
        if(isset($hocrData['pages'])) {
            return self::findWordInHocrData($hocrData['pages'], $searchString, $excludeIds);
        }

        if(isset($hocrData['children'])) {
            foreach($hocrData['children'] as $child) {
                $result = self::findWordInHocrData($child, $searchString, $excludeIds);
                if($result) {
                    return $result;
                }
            }
        }

        if(!isset($hocrData['children']) and !isset($hocrData['content'])) {
            $mainElement = reset($hocrData);
            $result = self::findWordInHocrData($mainElement, $searchString, $excludeIds);
            if($result) {
                return $result;
            }
        }

        if(isset($hocrData['content']) and stripos($hocrData['content'], $searchString)!==false and !in_array($hocrData['id'], $excludeIds)) {
            return $hocrData;
        } else {
            return null;
        }
    }
}
