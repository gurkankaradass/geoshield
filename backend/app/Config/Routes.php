<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('api/fault-lines', 'MapController::getFaultLines');
$routes->get('api/analyze-risk', 'MapController::analyzeRisk');
