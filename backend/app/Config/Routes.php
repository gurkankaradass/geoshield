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
    $routes->get('properties', 'PropertyController::index');
    $routes->post('properties', 'PropertyController::create');
    $routes->delete('properties/(:num)', 'PropertyController::delete/$1');
});
