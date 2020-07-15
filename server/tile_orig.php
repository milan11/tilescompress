<?php

$etagHeader = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '');

if ($etagHeader === 'v_1') {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

require_once '../internal/error_handling.php';
require_once '../internal/decompressing.php';
require_once '../internal/TileResponseHandler_Http.php';

decompressOne('.', (int) $_GET['z'], (int) $_GET['x'], (int) $_GET['y'], new TileResponseHandler_Http());
