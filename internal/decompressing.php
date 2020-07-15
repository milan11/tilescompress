<?php

require_once 'CompressedDirectoryStructure.php';
require_once 'PngConstants.php';

function decompressOne($dir, $zoom, $x, $y, TileResponseHandler $response)
{
    $compressedDirectoryStructure = new CompressedDirectoryStructure($dir);
    $fileName = $compressedDirectoryStructure->getXFilePath($zoom, $x);

    if (!file_exists($fileName)) {
        $response->notFound();
        return;
    }
    $fp = fopen($fileName, 'rb');

    $intervalsCount = unpack("N", readFull($fp, 4))[1];

    $entriesCount = 0;

    $indexEntryPos = null;

    for ($i = 0; $i < $intervalsCount; ++$i) {
        $begin = unpack("N", readFull($fp, 4))[1];
        $end = unpack("N", readFull($fp, 4))[1];

        if ($y >= $begin && $y <= $end) {
            $indexEntryPos = 4 + $intervalsCount * 8 + ($entriesCount + ($y - $begin)) * 4;
            break;
        }

        $entriesCount += ($end - $begin + 1);
    }

    if ($indexEntryPos === null) {
        $response->notFound();
        return;
    }

    fseek($fp, $indexEntryPos);

    $filePos = unpack("N", readFull($fp, 4))[1];

    fseek($fp, $filePos);

    $response->begin();
    $response->data(PngConstants::$pngHeader);

    $bitDepth = unpack("C", readFull($fp, 1))[1];
    $header = PngConstants::$ihdr_beforeBitDepth . pack("C", $bitDepth) . PngConstants::$ihdr_afterBitDepth;
    $response->data($header);
    $response->data(pack("N", crc32(substr($header, 4))));

    $paletteSize = unpack("C", readFull($fp, 1))[1];
    $response->data(pack("N", $paletteSize));
    $response->data("PLTE");
    $palette = readFull($fp, $paletteSize);
    $response->data($palette);
    $response->data(pack("N", crc32("PLTE" . $palette)));

    $dataChunksCount = unpack("C", readFull($fp, 1))[1];

    for ($i = 0; $i < $dataChunksCount; ++$i) {
        $dataSize = unpack("n", readFull($fp, 2))[1];
        $response->data(pack("N", $dataSize));
        $response->data("IDAT");
        $data = readFull($fp, $dataSize);
        $response->data($data);
        $response->data(pack("N", crc32("IDAT" . $data)));
    }

    $response->data(PngConstants::$iend);

    $response->end();

    fclose($fp);
}

function readFull($fp, $length)
{
    $result = '';

    while (strlen($result) < $length) {
        $result .= fread($fp, $length - strlen($result));
    }

    return $result;
}
