<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'api'], function () use ($router) {

    $router->post('/register', ['uses' => 'AuthController@register']);
    $router->post('/login', ['uses' => 'AuthController@login']);
    $router->get('/me', ['uses' => 'AuthController@me']);
    $router->get('/logout', ['uses' => 'AuthController@logout']);

    $router->group(['prefix' => 'users'], function () use ($router) {
        $router->get('/', ['uses' => 'UserController@index']);
        $router->post('/', ['uses' => 'UserController@store']);
        $router->patch('/{id}', ['uses' => 'UserController@update']);
        $router->delete('/{id}', ['uses' => 'UserController@destroy']);
    });

    $router->group(['prefix' => 'firebase'], function () use ($router) {
        $router->get('/', ['uses' => 'FirebaseController@index']);
        $router->post('/store', ['uses' => 'FirebaseController@store']);
        $router->patch('/update/{uid}', ['uses' => 'FirebaseController@update']);
        $router->delete('/delete/{uid}', ['uses' => 'FirebaseController@destroy']);
    });
    
    $router->get('/denom', ['uses' => 'FilterController@index']);
});

$router->get('/debug-sentry', function () use ($router) {
    throw new Exception('My first Sentry error!');
});
