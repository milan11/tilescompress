<?php

interface TileResponseHandler
{
    public function notFound();
    public function begin();
    public function data($data);
    public function end();
}
