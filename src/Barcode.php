<?php namespace FileTools;

/**
 * Class Barcode
 * @package FileTools
 */
class Barcode
{
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

    /**
     * Get the binary path to the required tool
     * @return string
     * @throws \Exception
     */
    private static function getBinary()
    {
        // working with https://linuxtv.org/downloads/zbar/zbar-0.23.tar.gz
        // can easily be compiled from source
        // comes with zbar-tools on Ubuntu 20.04 LTS
        $binary = \FileTools\File::tryToGetMimeMetadataBinaryToolPath('zbarimg');
        if(empty($binary)) {
            throw new \Exception('Missing zbarimg binary for scanning barcodes - try apt install zbar-tools or build from https://linuxtv.org/downloads/zbar/zbar-0.23.tar.gz if < Ubuntu 20.04>');
        }
        return $binary;
    }

    /**
     * Gets bar codes from an image blob
     * @param string $imageContent
     * @return array
     * @throws \Exception
     */
    public static function scanBarCodes(string $imageContent)
    {
        $filePath = \FileTools\File::getMimeMetadataTemporaryFilePath('fileToolsBarcode');
        file_put_contents($filePath, $imageContent);
        $barcodes = self::scanBarCodesFromPath($filePath);
        unlink($filePath);
        return $barcodes;
    }

    /**
     * Gets bar codes from an image path
     * @param string $imagePath
     * @return array
     * @throws \Exception
     */
    public static function scanBarCodesFromPath(string $imagePath)
    {
        $barcodes = [];
        exec(self::getCommand($imagePath), $output);
        foreach($output as $barcodeResult) {
            $barcodeResult = trim($barcodeResult);
            if(empty($barcodeResult)) {
                continue;
            }
            if(!strpos($barcodeResult, ':')) {
                continue;
            }
            $type = substr($barcodeResult, 0, strpos($barcodeResult, ':'));
            $value = substr($barcodeResult, 1 + strpos($barcodeResult, ':'));
            $barcodes[] = [
                'type'=>$type,
                'value'=>$value,
            ];
        }

        return $barcodes;
    }
}
