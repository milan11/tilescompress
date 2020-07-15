<?php

require_once 'internal/error_handling.php';
require_once 'internal/compressing.php';

compressAll($argv[1], $argv[2]);
