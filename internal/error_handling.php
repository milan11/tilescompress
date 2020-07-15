<?php

function errHandle($errNo, $errStr, $errFile, $errLine)
{
    $msg = "$errStr in $errFile on line $errLine";
    echo $msg;
    throw new ErrorException($msg, $errNo);
}

set_error_handler('errHandle');
