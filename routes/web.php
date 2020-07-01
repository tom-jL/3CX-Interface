<?php

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
    $router->get('contacts',  ['uses' => 'ContactController@showAllContacts']);

    $router->get('contacts/{phone}', ['uses' => 'ContactController@showOneContact']);

    $router->post('contacts', ['uses' => 'ContactController@create']);

    $router->delete('contacts/{id}', ['uses' => 'ContactController@delete']);

    $router->put('contacts/{id}', ['uses' => 'ContactController@update']);
});
