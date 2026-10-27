<?php
declare(strict_types=1);

// When public/ is the document root, PHP can serve only these known static assets.
if (PHP_SAPI === 'cli-server' && in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/assets/css/app.css', '/assets/js/app.js'], true)) {
    return false;
}

// Direct requests to index.php open the same home page as /.
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/index.php') {
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    $_SERVER['REQUEST_URI'] = '/' . ($query !== null ? '?' . $query : '');
}

require dirname(__DIR__) . '/config/bootstrap.php';

use AgendaShops\Http\Application;

$response = (new Application())->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
http_response_code($response->status);
foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    echo $response->body;
}
