# tilescompress

PHP script to compress OpenStreetMap tiles. Reduces both total size and count of files.

Since standalone PNG files will be merged to compressed files in a custom format, a PHP file has to be used on the server to provide the original PNG files. This will generate that server-side PHP file, too.

Please be aware that although hosting these compressed files can overcome some limitations of the web hostings (files size and files count), it can trigger another limitations (count of simultaneously running scripts on the server - because you will now run PHP script to retrieve a tile instead of providing PNG file directly).

This is a lossy compression, because it (among other methods) converts the PNG files to 64-color palette.

## Compression methods

- removing parts of PNG files which are always the same (header, IHDR chunk, IEND chunk)
- removing parts of the PNG files which can be calculated (checksums)
- converting the PNG files to 64-color palette
- not storing PNG files with the same contents more than once (a lot of tiles are the same "blue only" or "green only")
- concatenating more PNG files to one file (to avoid storing filesystem metadata for all the files)

## Dependencies

- for generate_server_code.php:
  - [codeless/jugglecode](https://github.com/codeless/JuggleCode)
  - [nikic/php-parser](https://github.com/nikic/PHP-Parser)
  - [codeless/logmore](https://github.com/codeless/LogMore)

## Usage

Compressing:

```
compress.php <original_directory> <compressed_directory>
```

Generating server code:

```
composer install --no-dev
generate_server_code.php <compressed_directory>
```

Decompressing single file:

```
decompress.php <compressed_directory> <zoom> <x> <y> <target_fie>
```

Run test (generates some tiles, compresses them, decompresses, compares):

```
test.php
```

## Compression results

| Zoom | Total size before (MiB) | Total size after (MiB) | Files count before | Files count after |
| ---- | ----------------------- | ---------------------- | ------------------ | ----------------- |
| 0    | 0.014                   | 0.006                  | 1                  | 1                 |
| 1    | 0.039                   | 0.016                  | 4                  | 2                 |
| 2    | 0.203                   | 0.072                  | 16                 | 4                 |
| 3    | 0.498                   | 0.185                  | 64                 | 8                 |
| 4    | 2.8                     | 0.855                  | 256                | 16                |
| 5    | 10.3                    | 2.8                    | 1024               | 32                |
| 6    | 28.3                    | 7.9                    | 4096               | 64                |
| 7    | 84.0                    | 23.5                   | 16384              | 128               |
| 8    | 270                     | 75.8                   | 65536              | 256               |
| 9    | 895                     | 240                    | 262144             | 512               |
| 10   | 2764                    | 697                    | 1048576            | 1024              |
| 11   | 9523                    | 2151                   | 4194304            | 2048              |
| 12   | 28774                   | 5632                   | 16777216           | 4096              |
