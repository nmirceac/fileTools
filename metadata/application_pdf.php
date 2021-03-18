<?php

function application_pdf($contents, $extension)
{
    $binary = \FileTools\File::tryToGetMimeMetadataBinaryToolPath('pdfinfo');
    if(empty($binary)) {
        return null;
    }

    $filePath = tempnam('/tmp', 'mimeMetadata').'.'.$extension;
    file_put_contents($filePath, $contents);

    $excludedParams = [
        'file_size',
        'userproperties',
        'suspects',
        'javascript',
        'tagged',
    ];

    $metadata = [];

    exec($binary.' '.$filePath, $output);
    foreach($output as $line) {
        $param = str_replace(' ', '_', strtolower(trim(substr($line, 0, strpos($line, ':')))));
        $value = trim(substr($line, 1 + strpos($line, ':')));

        if(in_array($param, $excludedParams)) {
           continue;
        }

        if(strpos($param, 'date')) {
            $value = date('Y-m-d H:i:s', strtotime($value));
        }

        $metadata[$param] = $value;
    }

    if(empty($metadata)) {
        $metadata['corrupt']=true;
    }

    return $metadata;
}
