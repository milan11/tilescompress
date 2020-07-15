<?php

require_once 'OrigDirectoryStructureHandler.php';

class OrigDirectoryStructure
{
    function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function getZoomDirectoryPath($zoom)
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $zoom;
    }

    public function getXDirectoryPath($zoom, $x)
    {
        return $this->getZoomDirectoryPath($zoom) . DIRECTORY_SEPARATOR . $x;
    }

    public function getFilePath($zoom, $x, $y)
    {
        return $this->getXDirectoryPath($zoom, $x) . DIRECTORY_SEPARATOR . $y . '.png';
    }

    public function iterate(OrigDirectoryStructureHandler $handler)
    {
        $zoomDirs = glob($this->baseDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        $allZoom = array();
        foreach ($zoomDirs as $zoomDir) {
            $allZoom[] = (int) basename($zoomDir);
        }
        sort($allZoom);

        foreach ($allZoom as $zoom) {
            $this->iterateZoomDir($handler, $zoom);
        }
    }

    private function iterateZoomDir(OrigDirectoryStructureHandler $handler, $zoom)
    {

        $xDirs = glob($this->getZoomDirectoryPath($zoom) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        $allX = array();
        foreach ($xDirs as $xDir) {
            $allX[] = (int) basename($xDir);
        }
        sort($allX);

        $handler->zoomBegin($zoom);

        foreach ($allX as $x) {
            $this->iterateXDir($handler, $zoom, $x);
        }

        $handler->zoomEnd($zoom);
    }

    private function iterateXDir(OrigDirectoryStructureHandler $handler, $zoom, $x)
    {
        $yFiles = glob($this->getXDirectoryPath($zoom, $x) . DIRECTORY_SEPARATOR . '*.png');

        $allY = array();
        foreach ($yFiles as $yFile) {
            $allY[] = (int) basename($yFile, ".png");
        }
        sort($allY);

        $handler->tiles($zoom, $x, $allY);
    }
}
