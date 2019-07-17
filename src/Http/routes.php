<?php

$router->group(['middleware' => config('filetools.router.authMiddleware')], function ($router) {
    $router->post('/files/{any}', ['uses' => 'FilesController@vueHelper', 'as' => config('filetools.router.namedPrefix').'.helper']);
    $router->get('/files/preview/{fileId}', ['uses' => 'FilesController@preview', 'as' => config('filetools.router.namedPrefix').'.preview']);
    $router->get('/files/download/{fileId}', ['uses' => 'FilesController@download', 'as' => config('filetools.router.namedPrefix').'.download']);
});