<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', function ($routes) {
    // Tüm preflight (ön kontrol) isteklerini karşılayıp CORS'u tetikleyen can kurtaran satır:
    $routes->options('(:any)', 'Home::index');

    $routes->get('fault-lines', 'MapController::getFaultLines');
    $routes->get('analyze-risk', 'MapController::analyzeRisk');

    // Mülklerim CRUD Rotaları
    $routes->get('user_locations', 'UserLocationsController::index');
    $routes->post('user_locations', 'UserLocationsController::create');
    $routes->delete('user_locations/(:num)', 'UserLocationsController::delete/$1');
});
