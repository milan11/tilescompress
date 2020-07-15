<?php

require_once 'TileResponseHandler.php';

class TileResponseHandler_ToFile implements TileResponseHandler
{
    private $filePath;
    private $fp;

    function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    public function notFound()
    {
        throw new Exception("Not found");
    }

    public function begin()
    {
        $this->fp = fopen($this->filePath, "wb");
    }

    public function data($data)
    {
        fwrite($this->fp, $data);
    }

    public function end()
    {
        fclose($this->fp);
    }
}
