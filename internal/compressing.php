<?php

require_once 'TileResponseHandler.php';
require_once 'OrigDirectoryStructure.php';
require_once 'OrigDirectoryStructureHandler.php';
require_once 'CompressedDirectoryStructure.php';
require_once 'PngConstants.php';

function compressAll($sourceDir, $targetDir)
{
    $origDirectoryStructure = new OrigDirectoryStructure($sourceDir);
    $compressedDirectoryStructure = new CompressedDirectoryStructure($targetDir);

    $origDirectoryStructure->iterate(new class ($origDirectoryStructure, $compressedDirectoryStructure) implements OrigDirectoryStructureHandler
    {
        public function __construct($origDirectoryStructure, $compressedDirectoryStructure)
        {
            $this->origDirectoryStructure = $origDirectoryStructure;
            $this->compressedDirectoryStructure = $compressedDirectoryStructure;
        }

        public function zoomBegin($zoom)
        {
            $zoomDir = $this->compressedDirectoryStructure->getZoomDirectoryPath($zoom);
            if (!file_exists($zoomDir)) {
                mkdir($zoomDir);
            }
        }

        public function tiles($zoom, $x, $allY)
        {
            $targetFile = $this->compressedDirectoryStructure->getXFilePath($zoom, $x);
            if (file_exists($targetFile)) {
                return;
            }

            $intervals = array();

            $yFirst = null;
            $prevY = null;
            foreach ($allY as $y) {
                if ($yFirst === null) {
                    $yFirst = $y;
                } else if ($y !== $prevY + 1) {
                    $intervals[] = array("begin" => $yFirst, "end" => $prevY);
                    $yFirst = $y;
                }

                $prevY = $y;
            }

            if ($yFirst !== null) {
                $intervals[] = array("begin" => $yFirst, "end" => $prevY);
                $yFirst = null;
            }

            if (count($intervals) === 0) {
                return;
            }

            $desc = "Compressing: $zoom $x";
            foreach ($intervals as $interval) {
                $desc .= " [" . $interval["begin"] . ", " . $interval["end"] . "]";
            }

            echo $desc . "\n";

            $prevInterval = null;
            foreach ($intervals as $interval) {
                if ($prevInterval !== null) {
                    if ($interval["begin"] - $prevInterval["end"] < 10) {
                        echo "WARN: gap too small: [" . $prevInterval["end"] . ", " . $interval["begin"] . "]" . "\n";
                    }
                }
                $prevInterval = $interval;
            }

            $written = 0;

            $fp = fopen($targetFile . '.tmp', 'wb');
            fwrite($fp, pack("N", count($intervals)));
            $written += 4;

            foreach ($intervals as $interval) {
                $begin = $interval["begin"];
                $end = $interval["end"];

                fwrite($fp, pack("N", $begin));
                $written += 4;
                fwrite($fp, pack("N", $end));
                $written += 4;
            }

            $indexBegin = $written;

            foreach ($intervals as $interval) {
                $begin = $interval["begin"];
                $end = $interval["end"];

                for ($y = $begin; $y <= $end; $y++) {
                    fwrite($fp, pack("N", 0));
                    $written += 4;
                }
            }

            $hashToOffset = array();

            $offsets = array();

            foreach ($intervals as $interval) {
                $begin = $interval["begin"];
                $end = $interval["end"];

                for ($y = $begin; $y <= $end; $y++) {
                    $origFilePath = $this->origDirectoryStructure->getFilePath($zoom, $x, $y);
                    $contents = file_get_contents($origFilePath);

                    $expectedFooter = PngConstants::$iend;
                    $footer = substr($contents, strlen($contents) - strlen($expectedFooter), strlen($expectedFooter));
                    if ($footer !== $expectedFooter) {
                        $message = "Invalid footer in orig file: " . bin2hex($footer) . ", " . $origFilePath;
                        throw new Exception($message);
                    }

                    $hash = hash('sha256', $contents);

                    if (!array_key_exists($hash, $hashToOffset)) {
                        $hashToOffset[$hash] = $written;

                        /*
                        $image = new Imagick($origFilePath);
                        $image->setImageFormat('PNG');
                        $image->setInterlaceScheme(Imagick::INTERLACE_NO);
                        $image->quantizeImage(64, Imagick::COLORSPACE_RGB, 0, false, false);
                        $image->setImageType(Imagick::IMGTYPE_PALETTE);
                        $image->stripImage();
                        $image->setCompressionQuality(100);
                        //$image->setImageDepth(5);
                        */

                        $image = imagecreatefrompng($origFilePath);
                        $width = imagesx($image);
                        $height = imagesy($image);
                        $colors = imagecreatetruecolor($width, $height);
                        imagecopy($colors, $image, 0, 0, 0, 0, $width, $height);
                        imagetruecolortopalette($image, false, 64);
                        imagecolormatch($colors, $image);
                        imagedestroy($colors);
                        imagesavealpha($image, false);
                        imageinterlace($image, 0);

                        ob_start();
                        imagepng($image, null, 9);
                        $newContents = ob_get_contents();
                        ob_end_clean();
                        imagedestroy($image);

                        $pos = 0;

                        $expectedPng = PngConstants::$pngHeader;
                        $png = substr($newContents, $pos, strlen($expectedPng));
                        $pos += strlen($expectedPng);
                        if ($png !== $expectedPng) {
                            throw new Exception("Invalid PNG: " . bin2hex($png));
                        }

                        $expectedHeader_beforeBitDepth = PngConstants::$ihdr_beforeBitDepth;
                        $header_beforeBitDepth = substr($newContents, $pos, strlen($expectedHeader_beforeBitDepth));
                        $pos += strlen($expectedHeader_beforeBitDepth);
                        if ($header_beforeBitDepth !== $expectedHeader_beforeBitDepth) {
                            throw new Exception("Invalid header before bit depth: " . bin2hex($header_beforeBitDepth));
                        }

                        $bitDepth = unpack("C", substr($newContents, $pos, 1))[1];
                        $pos += 1;

                        $expectedHeader_afterBitDepth = PngConstants::$ihdr_afterBitDepth;
                        $header_afterBitDepth = substr($newContents, $pos, strlen($expectedHeader_afterBitDepth));
                        $pos += strlen($expectedHeader_afterBitDepth);
                        if ($header_afterBitDepth !== $expectedHeader_afterBitDepth) {
                            throw new Exception("Invalid header after bit depth: " . bin2hex($header_afterBitDepth));
                        }

                        // checksum
                        $pos += 4;

                        $paletteSize = unpack("N", substr($newContents, $pos, 4))[1];
                        $pos += 4;
                        if ($paletteSize > 256) {
                            throw new Exception("Invalid palette size: " . $paletteSize);
                        }

                        $plte = substr($newContents, $pos, 4);
                        $pos += 4;
                        if ($plte !== "PLTE") {
                            throw new Exception("PLTE not found");
                        }

                        $palette = substr($newContents, $pos, $paletteSize);
                        $pos += $paletteSize;
                        // checksum
                        $pos += 4;

                        $physSize = unpack("N", substr($newContents, $pos, 4))[1];
                        $pos += 4;

                        $phys = substr($newContents, $pos, 4);
                        $pos += 4;
                        if ($phys !== "pHYs") {
                            throw new Exception("pHYs not found");
                        }

                        $pos += $physSize;

                        // checksum
                        $pos += 4;

                        $dataChunks = [];

                        while (true) {
                            $dataSize = unpack("N", substr($newContents, $pos, 4))[1];
                            $pos += 4;
                            if ($dataSize > 256 * 256) {
                                throw new Exception("Invalid data size: " . $dataSize);
                            }

                            $idat = substr($newContents, $pos, 4);
                            $pos += 4;
                            if ($idat !== "IDAT") {
                                throw new Exception("IDAT not found");
                            }

                            $data = substr($newContents, $pos, $dataSize);
                            $pos += $dataSize;

                            $dataChunks[] = $data;

                            // checksum
                            $pos += 4;

                            $footer = substr($newContents, $pos, strlen($expectedFooter));
                            if ($footer === $expectedFooter) {
                                $pos += strlen($expectedFooter);
                                break;
                            }
                        }

                        if ($pos !== strlen($newContents)) {
                            throw new Exception("Whole file not read");
                        }

                        fwrite($fp, pack("C", $bitDepth));
                        $written += 1;

                        fwrite($fp, pack("C", $paletteSize));
                        $written += 1;

                        fwrite($fp, $palette);
                        $written += $paletteSize;

                        $dataChunksCount = count($dataChunks);
                        fwrite($fp, pack("C", $dataChunksCount));
                        $written += 1;

                        for ($i = 0; $i < $dataChunksCount; ++$i) {
                            $data = $dataChunks[$i];
                            $dataSize = strlen($data);

                            fwrite($fp, pack("n", $dataSize));
                            $written += 2;

                            fwrite($fp, $data);
                            $written += $dataSize;
                        }
                    }

                    $offsets[] = $hashToOffset[$hash];
                }
            }


            fseek($fp, $indexBegin);
            foreach ($offsets as $offset) {
                fwrite($fp, pack("N", $offset));
            }

            fclose($fp);

            rename($targetFile . '.tmp', $targetFile);
        }

        public function zoomEnd($zoom)
        {
        }
    });
}
