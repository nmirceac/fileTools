<?php

$router->get('/{image}', ['uses'=>'ImageController@index', 'as'=>config('colortools.router.namedPrefix').'.get']);

$router->group(['middleware' => config('colortools.router.authMiddleware')], function ($router) {
//    $router->get('/{image}', ['uses'=>'ImageController@index', 'as'=>config('colortools.router.namedPrefix').'.get']);
});