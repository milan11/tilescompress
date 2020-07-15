<?php

class CompressedDirectoryStructure
{
    function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function getZoomDirectoryPath($zoom)
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $zoom;
    }

    public function getXFilePath($zoom, $x)
    {
        return $this->getZoomDirectoryPath($zoom) . DIRECTORY_SEPARATOR . $zoom . "_" . $x;
    }

    public function iterate(CompressedDirectoryStructureHandler $handler)
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

    private function iterateZoomDir(CompressedDirectoryStructureHandler $handler, $zoom)
    {
        $xFiles = glob($this->getZoomDirectoryPath($zoom) . DIRECTORY_SEPARATOR . '*_*');

        $allX = array();
        foreach ($xFiles as $xFile) {
            $allX[] = (int) explode('_', basename($xFile))[1];
        }
        sort($allX);

        $handler->xFiles($zoom, $allX);
    }
}
