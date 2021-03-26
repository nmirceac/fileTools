<?php

function audio_mpeg($contents, $extension)
{
    $binary = \FileTools\File::tryToGetMimeMetadataBinaryToolPath('soxi');
    if(empty($binary)) {
        return null;
    }

    $filePath = \FileTools\File::getMimeMetadataTemporaryFilePath($extension);
    file_put_contents($filePath, $contents);

    $excludedParams = [
        'input_file',
        'file_size',
    ];

    $metadata = [];

    exec($binary.' '.$filePath, $output);
    unlink($filePath);
    foreach($output as $line) {
        if(empty(trim($line))) {
            continue;
        }

        $param = str_replace(' ', '_', strtolower(trim(substr($line, 0, strpos($line, ':')))));
        $value = trim(substr($line, 1 + strpos($line, ':')));

        if(empty($param) or empty($value)) {
            continue;
        }

        if(in_array($param, $excludedParams)) {
            continue;
        }

        if($param == 'duration') {
            $parts = explode('=', $value);
            $value = trim($parts[0]);
            $metadata['frames'] = trim($parts[1]);
            unset($parts);
        }

        $metadata[$param] = $value;
    }

    if(empty($metadata)) {
        $metadata['corrupt']=true;
    }

    return $metadata;
}
