<?php

$router->group(['middleware' => config('filetools.router.authMiddleware')], function ($router) {
    $router->get('/preview/{fileId}', ['uses' => 'FilesController@preview', 'as' => config('filetools.router.namedPrefix').'.preview']);
    $router->get('/download/{fileId}', ['uses' => 'FilesController@download', 'as' => config('filetools.router.namedPrefix').'.download']);
});