<?php

require __DIR__ . '/vendor/autoload.php';
require_once 'internal/error_handling.php';

copy('server' . DIRECTORY_SEPARATOR . 'not_found.png', $argv[1] . DIRECTORY_SEPARATOR . 'not_found.png');

$j = new JuggleCode();
$j->masterfile = 'server' . DIRECTORY_SEPARATOR . 'tile_orig.php';
$j->outfile = $argv[1] . DIRECTORY_SEPARATOR . 'tile.php';
$j->mergeScripts = true;
$j->run();
