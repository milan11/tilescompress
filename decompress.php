<?php

require_once 'internal/error_handling.php';
require_once 'internal/decompressing.php';
require_once 'internal/TileResponseHandler_ToFile.php';

decompressOne($argv[1], (int) $argv[2], (int) $argv[3], (int) $argv[4], new TileResponseHandler_ToFile($argv[5]));
