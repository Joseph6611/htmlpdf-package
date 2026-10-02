<?php

namespace HtmlPdf\Image;

use RuntimeException;

/**
 * A decoded image, ready to be embedded as a PDF image XObject.
 *
 * - JPEG is embedded as-is (DCTDecode passthrough): fast, no quality loss.
 * - PNG without alpha is embedded as-is (the IDAT stream is reused with a PNG
 *   predictor), so no pixel decoding is needed.
 * - PNG with an alpha channel is unfiltered in pure PHP and split into a
 *   colour stream + a soft mask (needs ext-zlib, which PHP ships by default).
 * - Anything else (GIF, WebP, BMP, interlaced or 16-bit alpha PNG) is
 *   converted to PNG through ext-gd when it is available.
 */
class Image
{
    public int $width = 0;  // pixels
    public int $height = 0; // pixels
    public string $colorSpace = '/DeviceRGB'; // PDF colour-space expression
    public int $bpc = 8;
    public string $filter = 'FlateDecode';
    public string $data = '';
    public ?string $decodeParms = null;
    public ?string $decode = null;       // e.g. "[1 0 1 0 1 0 1 0]" for Adobe CMYK JPEG
    public ?array $colorKeyMask = null;  // single-colour transparency (PNG tRNS)
    public ?string $smask = null;        // zlib-compressed 8-bit alpha plane, same size as the image
    public string $hash = '';

    public static function fromBinary(string $bin): self
    {
        if ($bin === '') {
            throw new RuntimeException('empty image data');
        }

        if (strncmp($bin, "\xFF\xD8", 2) === 0) {
            $image = self::fromJpeg($bin);
        } elseif (strncmp($bin, "\x89PNG\r\n\x1a\n", 8) === 0) {
            $image = self::fromPng($bin);
        } else {
            $image = self::viaGd($bin);
        }

        $image->hash = md5($bin);

        return $image;
    }

    // ------------------------------------------------------------------ JPEG

    private static function fromJpeg(string $bin): self
    {
        $len = strlen($bin);
        $pos = 2;

        while ($pos + 4 <= $len) {
            if ($bin[$pos] !== "\xFF") {
                $pos++;
                continue;
            }
            $marker = ord($bin[$pos + 1]);
            if ($marker === 0xFF) { // fill byte
                $pos++;
                continue;
            }
            if ($marker === 0xD8 || $marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $pos += 2; // standalone markers carry no length
                continue;
            }
            if ($marker === 0xDA) { // start of scan: no SOF found before it
                break;
            }

            $segLen = unpack('n', substr($bin, $pos + 2, 2))[1];

            // SOF0..SOF15, except DHT (C4), JPG (C8) and DAC (CC)
            if ($marker >= 0xC0 && $marker <= 0xCF && !in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                if ($marker !== 0xC0 && $marker !== 0xC1 && $marker !== 0xC2) {
                    throw new RuntimeException('unsupported JPEG variant (lossless/arithmetic coding)');
                }
                $sof = unpack('Cprecision/nheight/nwidth/Ccomponents', substr($bin, $pos + 4, 6));
                if ($sof['width'] < 1 || $sof['height'] < 1) {
                    throw new RuntimeException('invalid JPEG dimensions');
                }

                $image = new self();
                $image->width = $sof['width'];
                $image->height = $sof['height'];
                $image->bpc = $sof['precision'];
                $image->filter = 'DCTDecode';
                $image->data = $bin;

                switch ($sof['components']) {
                    case 1:
                        $image->colorSpace = '/DeviceGray';
                        break;
                    case 3:
                        $image->colorSpace = '/DeviceRGB';
                        break;
                    case 4:
                        $image->colorSpace = '/DeviceCMYK';
                        // Adobe/Photoshop CMYK JPEGs store inverted values.
                        $image->decode = '[1 0 1 0 1 0 1 0]';
                        break;
                    default:
                        throw new RuntimeException('unsupported JPEG colour components: ' . $sof['components']);
                }

                return $image;
            }

            $pos += 2 + $segLen;
        }

        throw new RuntimeException('could not read JPEG dimensions');
    }

    // ------------------------------------------------------------------- PNG

    private static function fromPng(string $bin, bool $fromGd = false): self
    {
        $len = strlen($bin);
        $pos = 8;
        $ihdr = null;
        $plte = '';
        $trns = '';
        $idat = '';

        while ($pos + 8 <= $len) {
            $chunkLen = unpack('N', substr($bin, $pos, 4))[1];
            $type = substr($bin, $pos + 4, 4);
            $body = substr($bin, $pos + 8, $chunkLen);
            $pos += 12 + $chunkLen;

            switch ($type) {
                case 'IHDR':
                    $ihdr = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', $body);
                    break;
                case 'PLTE':
                    $plte = $body;
                    break;
                case 'tRNS':
                    $trns = $body;
                    break;
                case 'IDAT':
                    $idat .= $body;
                    break;
            }
            if ($type === 'IEND') {
                break;
            }
        }

        if ($ihdr === null || $idat === '') {
            throw new RuntimeException('corrupt PNG (missing IHDR/IDAT)');
        }
        if ($ihdr['width'] < 1 || $ihdr['height'] < 1) {
            throw new RuntimeException('invalid PNG dimensions');
        }

        $color = $ihdr['color'];
        $depth = $ihdr['depth'];
        $hasAlpha = $color === 4 || $color === 6;

        // Cases we do not decode ourselves: hand them to GD (once) if we can.
        if ($ihdr['interlace'] === 1 || ($hasAlpha && $depth !== 8)) {
            if ($fromGd) {
                throw new RuntimeException('unsupported PNG variant');
            }
            return self::viaGd($bin);
        }

        $image = new self();
        $image->width = $ihdr['width'];
        $image->height = $ihdr['height'];
        $image->bpc = $depth;

        if (!$hasAlpha) {
            $colors = $color === 2 ? 3 : 1;
            $image->colorSpace = match ($color) {
                0 => '/DeviceGray',
                2 => '/DeviceRGB',
                3 => '/Indexed',
                default => throw new RuntimeException('unsupported PNG colour type ' . $color),
            };

            if ($color === 3) {
                if ($plte === '') {
                    throw new RuntimeException('PNG palette missing');
                }
                $image->colorSpace = '[/Indexed /DeviceRGB ' . (intdiv(strlen($plte), 3) - 1) . ' <' . bin2hex($plte) . '>]';
            }

            // The IDAT stream is already zlib + PNG-filtered: reuse it directly.
            $image->data = $idat;
            $image->decodeParms = "<< /Predictor 15 /Colors {$colors} /BitsPerComponent {$depth} /Columns {$image->width} >>";

            // Single-colour transparency (tRNS)
            if ($trns !== '') {
                if ($color === 0 && strlen($trns) >= 2) {
                    $v = unpack('n', $trns)[1];
                    $image->colorKeyMask = [$v, $v];
                } elseif ($color === 2 && strlen($trns) >= 6) {
                    [, $r, $g, $b] = unpack('n3', $trns) + [1 => 0, 2 => 0, 3 => 0];
                    $image->colorKeyMask = [$r, $r, $g, $g, $b, $b];
                } elseif ($color === 3) {
                    $idx = strpos($trns, "\x00"); // first fully transparent palette entry
                    if ($idx !== false) {
                        $image->colorKeyMask = [$idx, $idx];
                    }
                }
            }

            return $image;
        }

        // Alpha: decode, then split colour and alpha planes.
        if (!function_exists('gzuncompress')) {
            throw new RuntimeException('PNG transparency needs ext-zlib');
        }
        $raw = @gzuncompress($idat);
        if ($raw === false) {
            throw new RuntimeException('corrupt PNG data (zlib)');
        }

        $bpp = $color === 4 ? 2 : 4;
        $rowBytes = $image->width * $bpp;
        if (strlen($raw) < $image->height * ($rowBytes + 1)) {
            throw new RuntimeException('truncated PNG data');
        }

        $pixels = self::unfilter($raw, $image->height, $rowBytes, $bpp);

        if ($color === 6) {
            $colorPlane = preg_replace('/(...)./s', '$1', $pixels);
            $alphaPlane = preg_replace('/...(.)/s', '$1', $pixels);
            $image->colorSpace = '/DeviceRGB';
        } else {
            $colorPlane = preg_replace('/(.)./s', '$1', $pixels);
            $alphaPlane = preg_replace('/.(.)/s', '$1', $pixels);
            $image->colorSpace = '/DeviceGray';
        }
        if ($colorPlane === null || $alphaPlane === null) {
            throw new RuntimeException('could not split PNG alpha channel');
        }

        $image->data = gzcompress($colorPlane);
        $image->smask = gzcompress($alphaPlane);

        return $image;
    }

    /**
     * Reverse the PNG per-scanline filters (None/Sub/Up/Average/Paeth).
     */
    private static function unfilter(string $raw, int $height, int $rowBytes, int $bpp): string
    {
        $out = [];
        $prev = array_fill(0, $rowBytes, 0);
        $pos = 0;

        for ($y = 0; $y < $height; $y++) {
            $filter = ord($raw[$pos++]);
            $line = array_values(unpack('C*', substr($raw, $pos, $rowBytes)));
            $pos += $rowBytes;

            switch ($filter) {
                case 0:
                    break;
                case 1: // Sub
                    for ($i = $bpp; $i < $rowBytes; $i++) {
                        $line[$i] = ($line[$i] + $line[$i - $bpp]) & 255;
                    }
                    break;
                case 2: // Up
                    for ($i = 0; $i < $rowBytes; $i++) {
                        $line[$i] = ($line[$i] + $prev[$i]) & 255;
                    }
                    break;
                case 3: // Average
                    for ($i = 0; $i < $rowBytes; $i++) {
                        $left = $i >= $bpp ? $line[$i - $bpp] : 0;
                        $line[$i] = ($line[$i] + (($left + $prev[$i]) >> 1)) & 255;
                    }
                    break;
                case 4: // Paeth
                    for ($i = 0; $i < $rowBytes; $i++) {
                        $a = $i >= $bpp ? $line[$i - $bpp] : 0;
                        $b = $prev[$i];
                        $c = $i >= $bpp ? $prev[$i - $bpp] : 0;
                        $p = $a + $b - $c;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $c);
                        $pred = ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
                        $line[$i] = ($line[$i] + $pred) & 255;
                    }
                    break;
                default:
                    throw new RuntimeException('corrupt PNG (bad filter type ' . $filter . ')');
            }

            $out[] = pack('C*', ...$line);
            $prev = $line;
        }

        return implode('', $out);
    }

    // -------------------------------------------------------------------- GD

    /**
     * Last resort for formats we don't parse natively: let GD decode the image
     * and re-encode it as a plain 8-bit, non-interlaced PNG.
     */
    private static function viaGd(string $bin): self
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('unsupported image format (only JPEG and PNG work without ext-gd)');
        }

        $im = @imagecreatefromstring($bin);
        if ($im === false) {
            throw new RuntimeException('unsupported or corrupt image');
        }

        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imageinterlace($im, false);

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        return self::fromPng($png, true);
    }
}
