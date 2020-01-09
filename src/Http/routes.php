<?php

$router->group(['middleware' => config('filetools.router.authMiddleware')], function ($router) {
    $router->get('/preview/{fileId}', ['uses' => 'FilesController@preview', 'as' => config('filetools.router.namedPrefix').'.preview']);
    $router->get('/download/{fileId}', ['uses' => 'FilesController@download', 'as' => config('filetools.router.namedPrefix').'.download']);
    $router->post('/upload', ['uses' => 'FilesController@upload', 'as' => config('filetools.router.namedPrefix').'.upload']);
    $router->post('/attach', ['uses' => 'FilesController@attach', 'as' => config('filetools.router.namedPrefix').'.attach']);
    $router->post('/delete', ['uses' => 'FilesController@delete', 'as' => config('filetools.router.namedPrefix').'.delete']);
    $router->post('/replace', ['uses' => 'FilesController@replace', 'as' => config('filetools.router.namedPrefix').'.replace']);
    $router->post('/update', ['uses' => 'FilesController@update', 'as' => config('filetools.router.namedPrefix').'.update']);
    $router->post('/reorder', ['uses' => 'FilesController@reorder', 'as' => config('filetools.router.namedPrefix').'.reorder']);
    $router->post('/associations', ['uses'=>'FilesController@associations', 'as'=>config('filetools.router.namedPrefix').'.associations']);
});
