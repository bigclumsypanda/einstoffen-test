<?php

use CodeIgniter\Router\RouteCollection;
use App\Controllers\Home;
use App\Controllers\Webhooks\Invoices;

/** @var RouteCollection $routes */
$routes->get('/', [Home::class, 'index']);

// Invoice webhook
$routes->match(
    ['post', 'get', 'put', 'patch', 'delete', 'head', 'options'],
    'webhooks/invoices',
    [Invoices::class, 'create']
);
