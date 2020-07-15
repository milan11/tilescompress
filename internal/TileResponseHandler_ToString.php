<?php

require_once 'TileResponseHandler.php';

class TileResponseHandler_ToString implements TileResponseHandler
{
    function __construct(&$str)
    {
        $this->str = &$str;
    }

    public function notFound()
    {
        throw new Exception("Not found");
    }

    public function begin()
    {
    }

    public function data($data)
    {
        $this->str .= $data;
    }

    public function end()
    {
    }
}
