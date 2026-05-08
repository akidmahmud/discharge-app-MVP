<?php namespace ProcessWire;

$rootPath = dirname(__DIR__);

if (!class_exists('ProcessWire\\ProcessWire', false)) {
    require_once $rootPath . '/wire/core/ProcessWire.php';
}

$config = ProcessWire::buildConfig($rootPath);
$config->external = true;
$wire = new ProcessWire($config);

if (!$wire->user->isLoggedin()) {
    $wire->session->redirect('/?unauthorized=1');
}

$page = $wire->pages->get('/dashboard/');
if (!$page->id) {
    header('HTTP/1.1 404 Not Found');
    exit('Dashboard page not found.');
}

echo $page->render();
