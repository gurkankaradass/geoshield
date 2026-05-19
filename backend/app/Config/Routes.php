<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', function ($routes) {
    $routes->get('fault-lines', 'MapController::getFaultLines');
    $routes->get('analyze-risk', 'MapController::analyzeRisk');

    // Mülklerim CRUD Rotaları
    $routes->get('user_locations', 'UserLocationsController::index');
    $routes->post('user_locations', 'UserLocationsController::create');
    $routes->delete('user_locations/(:num)', 'UserLocationsController::delete/$1');
});
