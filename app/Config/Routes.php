<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Dashboard::index', ['filter' => 'auth']);
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->get('otp', 'Auth::otp');
$routes->post('otp', 'Auth::verify');
$routes->post('logout', 'Auth::logout', ['filter' => 'auth']);

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('tickets', 'Tickets::index');
    $routes->get('tickets/new', 'Tickets::new', ['filter' => 'role:pemohon']);
    $routes->post('tickets', 'Tickets::create', ['filter' => 'role:pemohon']);
    $routes->get('tickets/(:num)', 'Tickets::show/$1');
    $routes->get('tickets/(:num)/edit', 'Tickets::edit/$1');
    $routes->post('tickets/(:num)/edit', 'Tickets::update/$1');
    $routes->post('tickets/(:num)/take', 'Tickets::take/$1', ['filter' => 'role:petugas,admin']);
    $routes->post('tickets/(:num)/status', 'Tickets::status/$1');
    $routes->post('tickets/(:num)/priority', 'Tickets::priority/$1', ['filter' => 'role:admin']);
    $routes->post('tickets/(:num)/delete', 'Tickets::delete/$1', ['filter' => 'role:admin']);
    $routes->post('tickets/(:num)/comments', 'Comments::create/$1');
    $routes->post('tickets/(:num)/attachments', 'Attachments::upload/$1');
    $routes->get('attachments/(:num)', 'Attachments::download/$1');
    $routes->get('notifications', 'Notifications::index');
    $routes->post('notifications/(:num)/read', 'Notifications::read/$1');
    $routes->get('api/notifications/unread', 'Notifications::unread');
    $routes->get('api/dashboard/summary', 'Dashboard::summary');
});
$routes->group('', ['filter' => 'role:petugas,admin'], static function (RouteCollection $routes): void {
    $routes->get('alerts', 'Alerts::index');
    $routes->get('api/alerts/active', 'Alerts::active');
});
$routes->group('', ['filter' => 'role:admin'], static function (RouteCollection $routes): void {
    $routes->get('monitor', 'Monitor::index');
    $routes->get('monitor/metrics', 'Monitor::metrics');
    $routes->get('reports/tickets.xlsx', 'Reports::tickets');
    $routes->get('reports/monthly.pdf', 'Reports::monthly');
    $routes->get('admin/users', 'Admin::users');
    $routes->post('admin/users', 'Admin::saveUser');
    $routes->post('admin/users/(:num)/delete', 'Admin::deleteUser/$1');
    $routes->get('admin/categories', 'Admin::categories');
    $routes->post('admin/categories', 'Admin::saveCategory');
    $routes->post('admin/categories/(:num)/delete', 'Admin::deleteCategory/$1');
    $routes->get('admin/settings', 'Admin::settings');
    $routes->post('admin/settings', 'Admin::saveSettings');
    $routes->get('admin/logs', 'Admin::logs');
    $routes->get('slideshow', 'Dashboard::slideshow');
});
