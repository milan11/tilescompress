<?php

require_once 'internal/error_handling.php';
require_once 'internal/compressing.php';
require_once 'internal/decompressing.php';
require_once 'internal/TileResponseHandler_ToString.php';
require_once 'internal/OrigDirectoryStructure.php';
require_once 'internal/OrigDirectoryStructureHandler.php';
require_once 'internal/CompressedDirectoryStructure.php';
require_once 'internal/CompressedDirectoryStructureHandler.php';

function generateTestData($baseDir)
{
    $origDirectoryStructure = new OrigDirectoryStructure($baseDir);

    for ($zoom = 0; $zoom <= 3; ++$zoom) {
        generateZoomTestData($origDirectoryStructure, $zoom);
    }
}

function generateZoomTestData(OrigDirectoryStructure $origDirectoryStructure, $zoom)
{
    $zoomDir = $origDirectoryStructure->getZoomDirectoryPath($zoom);
    mkdir($zoomDir);

    for ($x = 0; $x < pow(2, $zoom); ++$x) {
        $skip = rand(0, 8) === 0;
        if (!$skip) {
            generateXTestData($origDirectoryStructure, $zoom, $x);
        }
    }
}

function generateXTestData(OrigDirectoryStructure $origDirectoryStructure, $zoom, $x)
{
    $xDir = $origDirectoryStructure->getXDirectoryPath($zoom, $x);
    mkdir($xDir);

    for ($y = 0; $y < pow(2, $zoom); ++$y) {
        $skip = rand(0, 8) === 0;
        if (!$skip) {
            generateTestImage($origDirectoryStructure, $zoom, $x, $y);
        }
    }
}



function generateTestImage(OrigDirectoryStructure $origDirectoryStructure, $zoom, $x, $y)
{
    $file = $origDirectoryStructure->getFilePath($zoom, $x, $y);

    $image = imagecreatetruecolor(256, 256);

    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);

    $textColor = imagecolorallocate($image, rand(0, 100), rand(0, 100), rand(0, 100));
    imagestring($image, 5, 11, 70, 'z: ' . $zoom, $textColor);

    $textColor = imagecolorallocate($image, rand(0, 100), rand(0, 100), rand(0, 100));
    imagestring($image, 5, 11, 140, 'x: ' . $x, $textColor);

    $textColor = imagecolorallocate($image, rand(0, 100), rand(0, 100), rand(0, 100));
    imagestring($image, 5, 11, 210, 'y: ' . $y, $textColor);

    imagesavealpha($image, false);
    imagepng($image, $file);

    imagedestroy($image);
}

function clearTestData_orig($origDir)
{
    if (!file_exists($origDir)) {
        return;
    }

    $origDirectoryStructure = new OrigDirectoryStructure($origDir);

    $origDirectoryStructure->iterate(new class ($origDirectoryStructure) implements OrigDirectoryStructureHandler
    {
        public function __construct($origDirectoryStructure)
        {
            $this->origDirectoryStructure = $origDirectoryStructure;
        }

        public function zoomBegin($zoom)
        {
        }

        public function tiles($zoom, $x, $allY)
        {
            foreach ($allY as $y) {
                unlink($this->origDirectoryStructure->getFilePath($zoom, $x, $y));
            }

            rmdir($this->origDirectoryStructure->getXDirectoryPath($zoom, $x));
        }

        public function zoomEnd($zoom)
        {
            rmdir($this->origDirectoryStructure->getZoomDirectoryPath($zoom));
        }
    });

    rmdir($origDir);
}

function clearTestData_compressed($compressedDir)
{
    if (!file_exists($compressedDir)) {
        return;
    }

    $compressedDirectoryStructure = new CompressedDirectoryStructure($compressedDir);

    $compressedDirectoryStructure->iterate(new class ($compressedDirectoryStructure) implements CompressedDirectoryStructureHandler
    {
        public function __construct($compressedDirectoryStructure)
        {
            $this->compressedDirectoryStructure = $compressedDirectoryStructure;
        }

        public function xFiles($zoom, $allX)
        {
            foreach ($allX as $x) {
                unlink($this->compressedDirectoryStructure->getXFilePath($zoom, $x));
            }

            rmdir($this->compressedDirectoryStructure->getZoomDirectoryPath($zoom));
        }
    });

    rmdir($compressedDir);
}

function clearTestData($baseDir)
{
    if (!file_exists($baseDir)) {
        return;
    }

    clearTestData_orig($baseDir . DIRECTORY_SEPARATOR . '1_orig');
    clearTestData_compressed($baseDir . DIRECTORY_SEPARATOR . '2_compressed');

    rmdir($baseDir);
}

function compareAll($origDir, $compressedDir)
{
    $origDirectoryStructure = new OrigDirectoryStructure($origDir);

    $origDirectoryStructure->iterate(new class ($origDirectoryStructure, $compressedDir) implements OrigDirectoryStructureHandler
    {
        public function __construct($origDirectoryStructure, $compressedDir)
        {
            $this->origDirectoryStructure = $origDirectoryStructure;
            $this->compressedDir = $compressedDir;
        }

        public function zoomBegin($zoom)
        {
        }

        public function tiles($zoom, $x, $allY)
        {
            foreach ($allY as $y) {
                echo "Checking: $zoom $x $y\n";
                $yFile = $this->origDirectoryStructure->getFilePath($zoom, $x, $y);

                $decompressedData = '';

                decompressOne($this->compressedDir, $zoom, $x,  $y, new TileResponseHandler_ToString($decompressedData));

                $origImage = imagecreatefrompng($yFile);
                $decompressedImage = imagecreatefromstring($decompressedData);

                for ($pixelX = 0; $pixelX < 256; ++$pixelX) {
                    for ($pixelY = 0; $pixelY < 256; ++$pixelY) {
                        $origPixel = imagecolorsforindex($origImage, imagecolorat($origImage, $pixelX, $pixelY));
                        $decompressedPixel = imagecolorsforindex($decompressedImage, imagecolorat($decompressedImage, $pixelX, $pixelY));
                        if (!colorsSimilar($origPixel, $decompressedPixel)) {
                            throw new Exception(sprintf('Invalid pixel: z=%d, x=%d, y=%d, [%d, %d], orig=%s, decompressed=%s', $zoom, $x, $y, $pixelX, $pixelY, var_export($origPixel, true), var_export($decompressedPixel, true)));
                        }
                    }
                }

                imagedestroy($origImage);
                imagedestroy($decompressedImage);
            }
        }

        public function zoomEnd($zoom)
        {
        }
    });
}

function colorsSimilar($a, $b)
{
    return
        abs($a['red'] - $b['red']) <= 1
        && abs($a['green'] - $b['green']) <= 1
        && abs($a['blue'] - $b['blue']) <= 1
        && abs($a['alpha'] - $b['alpha']) === 0;
}

$testDataDir = 'test_data';
clearTestData($testDataDir);

mkdir($testDataDir);

$origDir = $testDataDir . DIRECTORY_SEPARATOR . '1_orig';
mkdir($origDir);
generateTestData($origDir);

$compressedDir = $testDataDir . DIRECTORY_SEPARATOR . '2_compressed';
mkdir($compressedDir);
compressAll($origDir, $compressedDir);

compareAll($origDir, $compressedDir);

clearTestData($testDataDir);
