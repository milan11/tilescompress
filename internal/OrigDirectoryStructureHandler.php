<?php

interface OrigDirectoryStructureHandler
{
    public function zoomBegin($zoom);
    public function tiles($zoom, $x, $allY);
    public function zoomEnd($zoom);
}
