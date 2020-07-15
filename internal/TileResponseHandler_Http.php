<?php

require_once 'TileResponseHandler.php';

class TileResponseHandler_Http implements TileResponseHandler
{
    function __construct()
    {
    }


    public function notFound()
    {
        header('Etag: v_1');
        header('Cache-Control: public, max-age=2592000');
        header('Content-Type: image/png');
        readfile("not_found.png");
    }

    public function begin()
    {
        header('Etag: v_1');
        header('Cache-Control: public');
        header('Content-Type: image/png');
    }

    public function data($data)
    {
        echo $data;
    }

    public function end()
    {
    }
}
