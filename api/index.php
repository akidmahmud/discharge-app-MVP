<?php
// API router — bootstraps ProcessWire and includes the appropriate handler
// Accessed via .htaccess rewrite for /api/* URLs

$rootPath = dirname(__DIR__);
if (DIRECTORY_SEPARATOR != '/') $rootPath = str_replace(DIRECTORY_SEPARATOR, '/', $rootPath);

if (!defined("PROCESSWIRE")) define("PROCESSWIRE", 302);

$composerAutoloader = $rootPath . '/vendor/autoload.php';
if (file_exists($composerAutoloader)) require_once($composerAutoloader);

if (!class_exists("ProcessWire\\ProcessWire", false)) {
    require_once($rootPath . "/wire/core/ProcessWire.php");
}

$config = \ProcessWire\ProcessWire::buildConfig($rootPath);

if (!$config->dbName) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ProcessWire not configured']);
    exit;
}

$wire = new \ProcessWire\ProcessWire($config);

extract($wire->wire('all')->getArray());

$endpoint = isset($_GET['endpoint']) ? preg_replace('/[^a-z0-9-]/', '', $_GET['endpoint']) : '';

$apiRoutes = [
    'upload' => 'upload.php',
    'search' => 'search.php',
    'admin-setup' => 'admin-setup.php',
    'admin-pw-setup' => 'admin-pw-setup.php',
];

$templateFile = isset($apiRoutes[$endpoint]) ? $apiRoutes[$endpoint] : null;
if (!$templateFile) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown API endpoint']);
    exit;
}

$filePath = $config->paths->templates . 'api/' . $templateFile;
if (!file_exists($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint file not found']);
    exit;
}

include $filePath;