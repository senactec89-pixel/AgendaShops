<?php
declare(strict_types=1);

// Local entry point. Route every request through this file when serving the root.
// Only these public assets may be read directly; private files are never served.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$assets = [
    '/assets/css/app.css' => ['public/assets/css/app.css', 'text/css; charset=UTF-8'],
    '/assets/js/app.js' => ['public/assets/js/app.js', 'text/javascript; charset=UTF-8'],
];
if (is_string($path) && isset($assets[$path])) {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD');
        exit;
    }
    [$file, $type] = $assets[$path];
    header('Content-Type: ' . $type);
    header('X-Content-Type-Options: nosniff');
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile(__DIR__ . '/' . $file);
    }
    exit;
}
require __DIR__ . '/public/index.php';
