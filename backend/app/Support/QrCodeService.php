<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    public static function svg(string $payload, int $scale = 6): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => $scale,
            'imageBase64' => false,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($payload);
    }

    public static function png(string $payload, int $scale = 8): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => $scale,
            'imageBase64' => false,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($payload);
    }

    public static function dataUri(string $payload, int $scale = 6): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($payload, $scale));
    }

    public static function studentCardPng(string $payload, string $fullName, string $classroom, int $scale = 32): string
    {
        $qrPng = self::png($payload, $scale);
        $qrImage = imagecreatefromstring($qrPng);

        if ($qrImage === false) {
            return $qrPng;
        }

        $scaled = imagescale($qrImage, 500, 500, IMG_NEAREST_NEIGHBOUR);
        if ($scaled !== false) {
            imagedestroy($qrImage);
            $qrImage = $scaled;
        }
        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);

        $fontRegular = self::resolveFontPath(false);
        $fontBold = self::resolveFontPath(true) ?? $fontRegular;
        $name = trim($fullName) !== '' ? trim($fullName) : 'Uchenik';
        $class = trim($classroom) !== '' ? trim($classroom) : null;
        $nameFontSize = 28;
        $classFontSize = 22;
        $lineGap = 42;
        $padding = 40;

        $canvasWidth = max(560, $qrWidth + 80);
        $maxTextWidth = $canvasWidth - $padding * 2;

        $nameLines = ($fontBold !== null)
            ? self::wrapText($name, $fontBold, $nameFontSize, $maxTextWidth)
            : [$name];
        $classLines = ($class !== null && $fontRegular !== null)
            ? self::wrapText($class, $fontRegular, $classFontSize, $maxTextWidth)
            : ($class !== null ? [$class] : []);

        $totalLines = count($nameLines) + count($classLines);
        $canvasHeight = $qrHeight + 30 + $totalLines * $lineGap + 20;
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

        if ($canvas === false) {
            imagedestroy($qrImage);

            return $qrPng;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $dark = imagecolorallocate($canvas, 22, 52, 95);
        $muted = imagecolorallocate($canvas, 78, 96, 125);

        imagefill($canvas, 0, 0, $white);

        $qrOffsetX = (int) floor(($canvasWidth - $qrWidth) / 2);
        imagecopy($canvas, $qrImage, $qrOffsetX, 8, 0, 0, $qrWidth, $qrHeight);

        $y = $qrHeight + 30 + $nameFontSize;

        if ($fontRegular !== null) {
            foreach ($nameLines as $line) {
                self::drawCenteredText($canvas, $line, $fontBold ?? $fontRegular, $nameFontSize, $y, $dark, $canvasWidth);
                $y += $lineGap;
            }
            foreach ($classLines as $line) {
                self::drawCenteredText($canvas, $line, $fontRegular, $classFontSize, $y, $muted, $canvasWidth);
                $y += $lineGap;
            }
        } else {
            imagestring($canvas, 5, max(12, (int) (($canvasWidth - imagefontwidth(5) * strlen($name)) / 2)), $y - 20, $name, $dark);
            if ($class !== null) {
                imagestring($canvas, 4, max(12, (int) (($canvasWidth - imagefontwidth(4) * strlen($class)) / 2)), $y + $lineGap - 16, $class, $muted);
            }
        }

        ob_start();
        imagepng($canvas);
        $binary = (string) ob_get_clean();

        imagedestroy($qrImage);
        imagedestroy($canvas);

        return $binary;
    }

    private static function drawCenteredText($image, string $text, string $fontPath, int $fontSize, int $baselineY, int $color, int $canvasWidth): void
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text);

        if ($box === false) {
            return;
        }

        $textWidth = (int) abs($box[2] - $box[0]);
        $x = max(20, (int) floor(($canvasWidth - $textWidth) / 2));
        imagettftext($image, $fontSize, 0, $x, $baselineY, $color, $fontPath, $text);
    }

    /**
     * @return string[]
     */
    private static function wrapText(string $text, string $fontPath, int $fontSize, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            $box = imagettfbbox($fontSize, 0, $fontPath, $test);
            $width = $box !== false ? (int) abs($box[2] - $box[0]) : 0;

            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $test;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [$text];
    }

    private static function resolveFontPath(bool $bold = false): ?string
    {
        $paths = $bold
            ? [
                'C:\\Windows\\Fonts\\arialbd.ttf',
                'C:\\Windows\\Fonts\\segoeuib.ttf',
                'C:\\Windows\\Fonts\\tahomabd.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ]
            : [
                'C:\\Windows\\Fonts\\arial.ttf',
                'C:\\Windows\\Fonts\\segoeui.ttf',
                'C:\\Windows\\Fonts\\tahoma.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
