<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Sub-path (mis. https://supportfkip.unsil.ac.id/alias): bila reverse proxy meneruskan awalan jalur apa adanya,
// buang awalan itu agar rute aplikasi (yang tidak berawalan) cocok. Bila proxy sudah membuangnya, tidak ada yang berubah.
$awalan = getenv('ALIAS_BASE_PATH') ?: (parse_url((string) getenv('APP_URL'), PHP_URL_PATH) ?: '');
$awalan = rtrim((string) $awalan, '/');
if ($awalan !== '' && isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    if ($uri === $awalan || str_starts_with($uri, $awalan.'/') || str_starts_with($uri, $awalan.'?')) {
        $_SERVER['REQUEST_URI'] = substr($uri, strlen($awalan)) ?: '/';
        if ($_SERVER['REQUEST_URI'][0] === '?') {
            $_SERVER['REQUEST_URI'] = '/'.$_SERVER['REQUEST_URI'];
        }
    }
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
